<?php

namespace Icinga\Module\Vspheredb\Sync;

use Exception;
use Icinga\Module\Vspheredb\Exception\DuplicateKeyException;
use Icinga\Module\Vspheredb\DbObject\ManagedObject;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\SelectSet\FullSelectSet;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class SyncManagedObjectReferences implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var VCenter */
    private $vCenter;

    /**
     * SyncManagedObjectReferences constructor.
     * @param VCenter $vCenter
     */
    public function __construct(VCenter $vCenter)
    {
        $this->vCenter = $vCenter;
        $this->setLogger(new NullLogger());
    }

    /**
     * @return $this
     * @throws DuplicateKeyException
     * @throws \Zend_Db_Exception
     */
    public function sync()
    {
        $vCenter = $this->vCenter;
        $this->logger->debug('Ready to fetch id/name/parent list');
        $all = $this->fetchNames();
        $this->logger->debug(sprintf('Got id/name/parent for %d objects', count($all)));
        $db = $this->vCenter->getConnection();

        /** @var ManagedObject[] $objects */
        $objects = ManagedObject::loadAllForVCenter($vCenter);
        $fetched = [];
        $nameUuids = [];
        $idToParent = [];
        $vCenterUuid = $vCenter->get('uuid');
        $vmUuidsWithNoParent = [];
        foreach ($all as $obj) {
            $moRef = $obj->id;
            $name = $obj->name;
            if (! isset($obj->overallStatus)) {
                $obj->overallStatus = 'gray';
            }
            $uuid = $vCenter->makeBinaryGlobalUuid($moRef);
            $fetched[$uuid] = $name;
            $nameUuids[$moRef] = $uuid;
            if (array_key_exists($uuid, $objects)) {
                $object = $objects[$uuid];
                $object->set('moref', $moRef);
                $object->set('vcenter_uuid', $vCenterUuid);
                $object->set('object_name', $name);
                $object->set('object_type', $obj->type);
                $object->set('overall_status', $obj->overallStatus);
            } else {
                $objects[$uuid] = ManagedObject::create([
                    'uuid'           => $uuid,
                    'vcenter_uuid'   => $vCenterUuid,
                    'moref'          => $moRef,
                    'object_name'    => $name,
                    'object_type'    => $obj->type,
                    'overall_status' => $obj->overallStatus,
                ], $db);
            }
            if (property_exists($obj, 'parent')) {
                $idToParent[$uuid] = $obj->parent->_;
            } elseif ($obj->type === 'VirtualMachine') {
                $vmUuidsWithNoParent[] = $uuid;
            }
        }

        if (! empty($vmUuidsWithNoParent)) {
            $this->logger->debug(\sprintf(
                'There are %d VMs without parent',
                \count($vmUuidsWithNoParent)
            ));
        }

        foreach ($idToParent as $uuid => $parentName) {
            if (array_key_exists($parentName, $nameUuids)) {
                $objects[$uuid]->setParent(
                    $objects[$nameUuids[$parentName]]
                );
            } else {
                $this->logger->error(sprintf(
                    "Could not find parent $parentName for %s",
                    $fetched[$uuid]
                ));
            }
        }

        $this->logger->debug('Storing object tree to DB');
        $dba = $this->vCenter->getDb();
        $dba->beginTransaction();
        try {
            $new = $same = $del = $mod = [];
            foreach ($objects as $uuid => $object) {
                $name = $object->get('object_name');
                if ($object->hasBeenLoadedFromDb()) {
                    if ($object->hasBeenModified()) {
                        $mod[$uuid] = $name;
                    } elseif (array_key_exists($uuid, $fetched)) {
                        $same[$uuid] = $name;
                    } else {
                        $del[$uuid] = $name;
                    }
                } else {
                    $new[$uuid] = $name;
                }
            }

            /*
            // Debug only:
            printf("%d new: %s\n", count($new), implode(', ', $new));
            printf("%d mod: %s\n", count($mod), implode(', ', $mod));
            foreach ($mod as $id => $name) {
                printf("%s has been modified:\n", $name);
                foreach ($objects[$id]->getModifiedProperties() as $prop => $newVal) {
                    printf(
                        "%s changed from %s to %s\n",
                        $prop,
                        $objects[$id]->getOriginalProperty($prop),
                        $newVal
                    );
                }
            }
            printf("%d del: %s\n", count($del), implode(', ', $del));
            printf("%d unmodified\n", count($same));
            */

            if (! empty($del)) {
                $dba->update(
                    'object',
                    ['parent_uuid' => null],
                    $dba->quoteInto('parent_uuid IN (?)', array_keys($del))
                );
                $dba->delete(
                    'object',
                    $dba->quoteInto('uuid IN (?)', array_keys($del))
                );
            }

            foreach ($objects as $object) {
                $object->store();
            }
            $dba->commit();
        } catch (Exception $error) {
            try {
                $dba->rollBack();
            } catch (Exception $e) {
                // There is nothing we can do.
            }
            throw $error;
        }

        if (count($new) + count($mod) + count($del) === 0) {
            $this->logger->debug('Managed Objects have not been changed');
        } else {
            $this->logger->debug(sprintf(
                'Created %d Managed Objects, %d modified, %d deleted',
                count($new),
                count($mod),
                count($del)
            ));
        }

        return $this;
    }

    protected function fetchNames()
    {
        return $this->vCenter->getApi($this->logger)->propertyCollector()->collectProperties(
            $this->prepareNameSpecSet()
        );
    }

    protected function prepareNameSpecSet()
    {
        $types = [
            'Datacenter',
            'Datastore',
            'Folder',
            'ResourcePool',
            'HostSystem',
            'ComputeResource',
            'ClusterComputeResource',
            'StoragePod',
            'VirtualMachine',
            'VirtualApp',
            'Network',
            'DistributedVirtualSwitch',
            'DistributedVirtualPortgroup',
        ];
        $pathSet = ['name', 'parent', 'overallStatus'];

        $propSet = [];
        foreach ($types as $type) {
            $propSet[] = [
                'type' => $type,
                'all' => 0,
                'pathSet' => $pathSet
            ];
        }

        return [
            'propSet' => $propSet,
            'objectSet' => [
                'obj'  => $this->vCenter->getApi($this->logger)->getServiceInstance()->rootFolder,
                'skip' => false,
                'selectSet' => (new FullSelectSet())->toArray(),
            ]
        ];
    }
}
