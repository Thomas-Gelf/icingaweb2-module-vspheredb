<?php

namespace Icinga\Module\Vspheredb\PerformanceData;

use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceQuerySpecHelper;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSet;
use Psr\Log\LoggerInterface;

abstract class ChunkedPerfdataReader
{
    /**
     * @param PerformanceSet $performanceSet
     * @param VCenter $vCenter
     * @param LoggerInterface $logger
     * @return \Generator
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function fetchSet(PerformanceSet $performanceSet, VCenter $vCenter, LoggerInterface $logger)
    {
        $perf = $vCenter->getApi($logger)->perfManager();
        $setName = $performanceSet->getMeasurementName();
        $vms = $performanceSet->getRequiredMetrics();
        $logger->info("Fetching $setName for " . count($vms) . ' VMs');
        $counters = $performanceSet->getCounters();
        foreach (array_chunk($vms, 100, true) as $chunk) {
            $logger->info('Fetching ' . count($chunk) . " chunks for $setName");
            $spec = PerformanceQuerySpecHelper::prepareQuerySpec(
                $performanceSet->getObjectType(),
                $counters,
                $chunk
            );
            $res = $perf->queryPerf($spec);
            $logger->info('Got result');
            if (empty($res)) {
                // TODO: This happens. Why? Inspect set?
                $logger->warning('Got EMPTY result');
                continue;
            }
            $logger->debug('Got ' . count($res) . " results for $setName");
            foreach ($res as $r) {
                yield CompactEntityMetrics::process($r, $setName, $counters);
            }
        }
    }
}
