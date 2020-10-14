<?php

namespace Icinga\Module\Vspheredb\PerformanceData;

use Icinga\Module\Vspheredb\Api;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceQuerySpecHelper;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSet;
use Psr\Log\LoggerInterface;
use function array_chunk;
use function count;

abstract class ChunkedPerfdataReader
{
    /**
     * @param PerformanceSet $performanceSet
     * @param Api $api
     * @param LoggerInterface $logger
     * @return \Generator
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function fetchSet(PerformanceSet $performanceSet, Api $api, LoggerInterface $logger)
    {
        $perf = $api->perfManager();
        $setName = $performanceSet->getMeasurementName();
        $vms = $performanceSet->getRequiredInstances();
        $logger->info("Fetching $setName for " . count($vms) . ' VMs');
        $counters = $performanceSet->getCounters();
        foreach (array_chunk($vms, 100, true) as $chunk) {
            $specs = PerformanceQuerySpecHelper::prepareQuerySpec(
                $performanceSet->getObjectType(),
                $counters,
                $chunk
            );
            $logger->info(sprintf(
                'Fetching a chunk with %d objects (%d instances) for %s',
                count($chunk),
                count($specs),
                $setName
            ));
            $res = $perf->queryPerf($specs);
            if (empty($res)) {
                // TODO: This happens. Why? Inspect set?
                $logger->warning("Got an EMPTY result for $setName");
                continue;
            }
            $logger->debug('Got ' . count($res) . " results for $setName");
            foreach ($res as $r) {
                yield CompactEntityMetrics::process($r, $setName, $counters);
            }
        }
    }
}
