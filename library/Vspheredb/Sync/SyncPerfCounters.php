<?php

namespace Icinga\Module\Vspheredb\Sync;

use Icinga\Module\Vspheredb\DbObject\VCenter;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Strategy:
 * - fetch vms in chunks
 * - fetch existing counters for those
 * - fetch current counters
 * - remove (really) outdated counters with no values
 * - update existing counters
 * - insert missing counters
 * - eventually fetch former values for missing counters in case [..]
 *
 *
 * Hint: this is currently UNUSED, see SyncRunner.
 */
class SyncPerfCounters
{
    use LoggerAwareTrait;

    /** @var VCenter */
    protected $vCenter;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $dba;

    protected $table = 'counter_300x5';

    public function __construct(VCenter $vCenter, LoggerInterface $logger)
    {
        $this->vCenter = $vCenter;
        $this->dba = $vCenter->getDb();
        $this->setLogger($logger);
    }

    protected function listVirtualMachines()
    {
        $type = 'VirtualMachine';

        $query = $this->dba->select()
            ->from('object', 'moref')
            ->where('object_type = ?', $type)
            ->order('moref');

        return $this->dba->fetchCol($query);
    }

    public function run()
    {
        $type = 'VirtualMachine';

        $vCenter = $this->vCenter;
        $api = $vCenter->getApi($this->logger);
        $uuid = $vCenter->getUuid();
        $vms = $this->listVirtualMachines();
        $db = $this->dba;
        $chunkSize = 100;

        foreach (array_chunk($vms, $chunkSize) as $objects) {
            $currentTs = floor(time() / 300) * 300 * 1000;
            // $keys = ['value_last'];
            $keys = ['value_minus4', 'value_minus3', 'value_minus2', 'value_minus1', 'value_last'];
            $perf = $api->perfManager()->oldTestQueryPerf($objects, $type, 300, count($keys));
            $db->beginTransaction();
            $count = 0;
            foreach ($perf as $p) {
                $entity = $p->entity->_;
                foreach ($p->value as $val) {
                    $count++;
                    $values = array_combine($keys, preg_split('/,/', $val->value));
                    $db->insert($this->table, $values + [
                        'vcenter_uuid' => $uuid,
                        'counter_key'  => $val->id->counterId,
                        'instance'     => $val->id->instance,
                        'object_uuid'  => $vCenter->makeBinaryGlobalUuid($entity),
                        'ts_last'      => $currentTs,
                    ]);
                }
            }
            $db->commit();
            $this->logger->debug("Stored $count instances");
        }

        $currentTs = floor(time() / 300) * 300 * 1000;
        $outdated = $currentTs - (5 * 300 * 1000);
        $db->delete($this->table, $db->quoteInto('ts_last < ?', $outdated));
    }

    protected function updateValue($properties)
    {
        $currentTs = $properties['ts_last'];
        $step1 = $currentTs - (1 * 300 * 1000);
        $step2 = $currentTs - (2 * 300 * 1000);
        $step3 = $currentTs - (3 * 300 * 1000);
        $step4 = $currentTs - (4 * 300 * 1000);
        $rep1 = "(CASE WHEN ts_last = $step4 THEN value_last ELSE NULL END)";
        $rep2 = "(CASE WHEN ts_last = $step3 THEN value_minus1 ELSE $rep1 END)";
        $rep3 = "(CASE WHEN ts_last = $step2 THEN value_minus2 ELSE $rep2 END)";
        $rep4 = "(CASE WHEN ts_last = $step1 THEN value_minus3 ELSE $rep3 END)";
        return $this->dba->update($this->table, [
            'value_minus4' => $rep4,
            'value_minus3' => $rep3,
            'value_minus2' => $rep2,
            'value_minus1' => $rep1,
        ] + $properties);
    }
}
