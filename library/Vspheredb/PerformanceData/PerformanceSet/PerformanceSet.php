<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\MappedClass\PerfMetricId;
use Icinga\Module\Vspheredb\MappedClass\PerfQuerySpec;
use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;
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

    abstract public function getMeasurementName();

    abstract  public function getRequiredMetrics();

    abstract public function fetchObjectTags();

    protected function fetchCounters()
    {
        $db = $this->vCenter->getDb();
        $query = $db->select()->from('performance_counter', [
            'v' => 'counter_key',
            'k' => 'name',
        ])
            ->where('vcenter_uuid = ?', $this->vCenter->getUuid())
            ->where('group_name = ?', $this->countersGroup)
            ->where('rollup_type NOT IN (?)', ['maximum', 'minimum'])
            ->where('name IN (?)', $this->counters);

        return $db->fetchPairs($query);
    }

    public function getDb()
    {
        return $this->db;
    }

    public function getCounters()
    {
        return $this->fetchCounters();
    }

    protected function makeDateTime($timestamp)
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function fetch()
    {
        $perf = $this->vCenter->getApi($this->logger)->perfManager();
        $vms = $this->getRequiredMetrics();
        $this->logger->info('Fetching ' . $this->getMeasurementName() . ' for ' . count($vms) . ' VMs');
        foreach (array_chunk($vms, 100, true) as $set) {
            $this->logger->info('Fetching ' . count($set) . ' chunks for ' . $this->getMeasurementName());
            $spec = $this->prepareQuerySpec($set);
            $res = $perf->queryPerf($spec);
            $this->logger->info('Got result');
            if (empty($res)) {
                // TODO: This happens. Why? Inspect set?
                $this->logger->warning('Got EMPTY result');
                continue;
            }
            $this->logger->debug('Got ' . count($res) . ' results for ' . $this->getMeasurementName());
            foreach ($res as $r) {
                yield $r;
            }
        }
    }

    /**
     * @return PerfQuerySpec[]
     */
    public function prepareQuerySpec($objects)
    {
        // 20s -> liegt für 1h vor, also 3600 / 20 = 180
        $count = 180;
        $interval = 20; // "realtime"
        $duration = $interval * ($count);
        $now = floor(time() / $interval) * $interval;
        $start = $this->makeDateTime($now - $duration);
        $end = $this->makeDateTime($now);
        $counters = $this->getCounters();

        $specs = [];
        foreach ($objects as $moref => $instances) {
            $instances = preg_split('/,/', $instances);
            $metrics = [];
            foreach ($counters as $k => $n) {
                foreach ($instances as $instance) {
                    $metrics[] = new PerfMetricId($k, $instance);
                }
            }

            $spec = new PerfQuerySpec();
            $spec->entity = new ManagedObjectReference($this->objectType, $moref);
            $spec->startTime  = $start;
            $spec->endTime    = $end; //'2017-12-13T18:10:00Z'
            // $spec->maxSample = $count;
            $spec->metricId   = $metrics;
            $spec->intervalId = $interval;
            $spec->format     = 'csv';
            $specs[] = $spec;
        }

        return $specs;
    }

    public function __destruct()
    {
        unset($this->vCenter);
        unset($this->db);
    }
}
