<?php

namespace Icinga\Module\Vspheredb\Sync;

use Exception;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use Icinga\Module\Vspheredb\PropertySet\PropertySet;
use Icinga\Module\Vspheredb\Util;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class SyncVmDatastoreUsage
{
    use LoggerAwareTrait;

    /** @var VCenter */
    protected $vCenter;

    public function __construct(VCenter $vCenter, LoggerInterface $logger)
    {
        $this->vCenter = $vCenter;
        $this->setLogger($logger);
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws Exception
     */
    public function run()
    {
        $vCenter = $this->vCenter;
        $db = $vCenter->getDb();

        // Hint/TODO: well... this will always trigger on the very first run
        $this->refreshOutdatedDatastores();

        $result = $vCenter->getApi($this->logger)->propertyCollector()->collectObjectProperties(
            new PropertySet('VirtualMachine', ['storage.perDatastoreUsage', 'storage.timestamp']),
            VirtualMachine::getSelectSet()
        );
        $this->logger->debug('Got VirtualMachine perDatastoreUsage');

        $vCenterUuid = $vCenter->get('uuid');
        $table = 'vm_datastore_usage';
        $existing = $db->fetchCol($db->select()->from(
            $table,
            "(vm_uuid || datastore_uuid)"
        )->where('vcenter_uuid = ?', $vCenterUuid));
        $existing = array_combine($existing, $existing);
        $seen = [];

        $db->beginTransaction();
        $insert = 0;
        $update = 0;
        $delete = 0;

        try {
            foreach ($result as $map) {
                $moRef = $map->id;
                $vmUuid = $vCenter->makeBinaryGlobalUuid($moRef);
                if (! isset($map->{'storage.perDatastoreUsage'}->{'VirtualMachineUsageOnDatastore'})) {
                    continue;
                }
                if (isset($map->{'storage.timestamp'})) {
                    $timestamp = Util::timeStringToUnixMs($map->{'storage.timestamp'});
                } else {
                    $timestamp = null;
                }
                foreach ($map->{'storage.perDatastoreUsage'}->{'VirtualMachineUsageOnDatastore'} as $usage) {
                    $dsMoid = $usage->datastore->_;
                    $dsUuid = $vCenter->makeBinaryGlobalUuid($dsMoid);
                    $key = "$vmUuid$dsUuid";
                    $usage = [
                        'committed'   => $usage->committed,
                        'uncommitted' => $usage->uncommitted,
                        'unshared'    => $usage->unshared,
                        'ts_updated'  => $timestamp,
                    ];
                    $seen[$key] = $key;
                    if (array_key_exists($key, $existing)) {
                        $res = $db->update(
                            $table,
                            $usage,
                            $this->makeWhere($db, $vmUuid, $dsUuid)
                        );
                        if ($res) {
                            $update++;
                        }
                    } else {
                        $usage['vcenter_uuid'] = $vCenterUuid;
                        $usage['vm_uuid'] = $vmUuid;
                        $usage['datastore_uuid'] = $dsUuid;
                        $db->insert($table, $usage);
                        $insert++;
                    }
                }
            }

            foreach (array_diff($existing, $seen) as $key) {
                $vmUuid = substr($key, 0, 20);
                $dsUuid = substr($key, 20);
                $db->delete($table, $this->makeWhere($db, $vmUuid, $dsUuid));
                $delete++;
            }

            $db->commit();
            $this->logger->debug("$insert created, $update changed, $delete deleted");
        } catch (Exception $error) {
            try {
                $db->rollBack();
            } catch (Exception $e) {
                // There is nothing we can do.
            }

            throw $error;
        }

        $this->refreshOutdatedVms();
    }

    protected function refreshOutdatedDatastores()
    {
        $vCenter = $this->vCenter;
        $db = $vCenter->getDb();
        $vCenterUuid = $vCenter->get('uuid');

        // Updated 1800s ago? Outdated.
        $tsExpiredMs = (time() - 1800) * 1000;
        $subQuery = $db->select()->from(['du' => 'vm_datastore_usage'], [
            'ts_updated'     => 'MIN(ts_updated)',
            'datastore_uuid' => 'datastore_uuid',
        ])->join(
            ['vm' => 'virtual_machine'],
            "vm.uuid = du.vm_uuid AND vm.runtime_power_state = 'poweredOn'"
        )->group('datastore_uuid');
        $query = $db->select()->from(['o' => 'object'], [
            'moref'       => 'o.moref',
            'object_name' => 'o.object_name',
        ])->join(
            ['ds' => 'datastore'],
            'ds.uuid = o.uuid',
            []
        )->join(
            ['vdu' => $subQuery],
            'vdu.datastore_uuid = o.uuid',
            []
        )->where(
            'o.vcenter_uuid = ?',
            $vCenterUuid
        )->where(
            'vdu.ts_updated < ?',
            $tsExpiredMs
        )->where(
            '(ds.ts_last_forced_refresh IS NULL OR ds.ts_last_forced_refresh < ?)',
            $tsExpiredMs
        );
        $dataStores = $db->fetchPairs($query);

        if (empty($dataStores)) {
            return;
        }

        $this->logger->info(sprintf(
            'Calling RefreshDatastoreStorageInfo on %d outdated Datastore(s)',
            count($dataStores)
        ));

        $api = $vCenter->getApi($this->logger);
        foreach (array_keys($dataStores) as $moref) {
            $api->soapCall('RefreshDatastoreStorageInfo', [
                '_this'   => $moref,
            ]);
        }
    }

    protected function refreshOutdatedVms()
    {
        $vCenter = $this->vCenter;
        $db = $vCenter->getDb();
        $vCenterUuid = $vCenter->get('uuid');

        // Updated 1800s ago? Outdated.
        $tsExpiredMs = (time() - 1800) * 1000;

        $query = $db->select()->from(['o' => 'object'], [
            'moref'       => 'o.moref',
            'object_name' => 'o.object_name',
        ])->join(
            ['vm' => 'virtual_machine'],
            "vm.uuid = o.uuid AND vm.template = 'n'",
            []
        )->join(
            ['vdu' => 'vm_datastore_usage'],
            'vm.uuid = vdu.vm_uuid',
            []
        )->where(
            'o.vcenter_uuid = ?',
            $vCenterUuid
        )->where(
            'vdu.ts_updated < ?',
            $tsExpiredMs
        )->group('o.uuid');
        $vms = $db->fetchPairs($query);

        if (empty($vms)) {
            return;
        }

        $this->logger->info(sprintf(
            'Calling RefreshStorageInfo on %d outdated VirtualMachine(s)',
            count($vms)
        ));

        $api = $vCenter->getApi($this->logger);
        foreach (array_keys($vms) as $moref) {
            $api->soapCall('RefreshStorageInfo', [
                '_this'   => $moref,
            ]);
        }
    }

    protected function makeWhere(\Zend_Db_Adapter_Abstract $db, $vmUuid, $dsUuid)
    {
        return $db->quoteInto('vm_uuid = ?', $vmUuid)
            . $db->quoteInto(' AND datastore_uuid = ?', $dsUuid);
    }
}
