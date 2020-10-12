<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

use Icinga\Module\Vspheredb\DbObject\VCenter;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

abstract class PerformanceSet implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var VCenter */
    protected $vCenter;

    /** @var \Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var string HostSystem, VirtualMachine... */
    protected $objectType;

    protected $counters = [];

    protected $countersGroup;

    public function __construct(VCenter $vCenter)
    {
        $this->vCenter = $vCenter;
        $this->db = $vCenter->getDb();
        $this->logger = new NullLogger();
    }

    public function getObjectType()
    {
        return $this->objectType;
    }

    public function getCounters()
    {
        return $this->fetchCounters();
    }

    public function getRequiredMetrics()
    {
        return $this->explodeInstances($this->getDb()->fetchPairs($this->prepareInstancesQuery()));
    }

    abstract public function getMeasurementName();

    abstract public function fetchObjectTags();

    abstract public function prepareInstancesQuery();

    protected function fetchCounters()
    {
        $db = $this->vCenter->getDb();
        $query = $db
            ->select()
            ->from('performance_counter', [
                'v' => 'counter_key',
                'k' => 'name',
            ])
            ->where('vcenter_uuid = ?', $this->vCenter->getUuid())
            ->where('group_name = ?', $this->countersGroup)
            ->where('rollup_type NOT IN (?)', ['maximum', 'minimum'])
            ->where('name IN (?)', $this->counters);

        return $db->fetchPairs($query);
    }

    protected function explodeInstances($queryResult)
    {
        $result = [];

        foreach ($queryResult as $key => $value) {
            $result[$key] = preg_split('/,/', $value);
        }

        return $result;
    }

    protected function getDb()
    {
        return $this->db;
    }

    public function __destruct()
    {
        unset($this->vCenter);
        unset($this->db);
    }
}
