<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

use Icinga\Module\Vspheredb\MappedClass\PerfMetricId;
use Icinga\Module\Vspheredb\MappedClass\PerfQuerySpec;
use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;
use function gmdate;

abstract class PerformanceQuerySpecHelper
{
    /**
     * @param string $objectType 'HostSystem', 'VirtualMachine'...
     * @param array $counters  [counterKey => name, ...]
     * @param $objectWithInstances [vm-123 => [scsi0:0, ...], ...]. To test: * would be all instances
     * @param int $count Defaults to 180. We have 1h in a 20s interval. 3600 / 20 = 180
     * @param int $interval Defaults to 20s, "realtime"
     * @return PerfQuerySpec[]
     */
    public static function prepareQuerySpec(
        $objectType,
        $counters,
        $objectWithInstances,
        $count = 180,
        $interval = 20
    ) {
        $duration = $interval * ($count);
        $now = floor(time() / $interval) * $interval;
        $start = static::makeDateTime($now - $duration);
        $end = static::makeDateTime($now);

        $specs = [];
        foreach ($objectWithInstances as $moref => $instances) {
            $metrics = [];
            foreach ($counters as $k => $n) {
                foreach ($instances as $instance) {
                    $metrics[] = new PerfMetricId($k, $instance);
                }
            }

            $spec = new PerfQuerySpec();
            $spec->entity = new ManagedObjectReference($objectType, $moref);
            $spec->startTime  = $start;
            $spec->endTime    = $end;
            // $spec->maxSample = $count;
            $spec->metricId   = $metrics;
            $spec->intervalId = $interval;
            $spec->format     = 'csv';
            $specs[] = $spec;
        }

        return $specs;
    }

    /**
     * @param int $timestamp
     * @return string '2017-12-13T18:10:00Z'
     */
    protected static function makeDateTime($timestamp)
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}
