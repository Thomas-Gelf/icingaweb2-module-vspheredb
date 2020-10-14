<?php

namespace Icinga\Module\Vspheredb\PerformanceData;

use Icinga\Module\Vspheredb\Api;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceQuerySpecHelper;
use Icinga\Module\Vspheredb\Polling\PerfDataSet;
use Psr\Log\LoggerInterface;
use function array_chunk;
use function count;

abstract class ChunkedPerfdataReader
{
    /**
     * @param PerfDataSet $set
     * @param Api $api
     * @param LoggerInterface $logger
     * @return \Generator
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function fetchSet(PerfDataSet $set, Api $api, LoggerInterface $logger)
    {
        $perf = $api->perfManager();
        $setName = $set->getMeasurementName();
        $objects = $set->getRequiredInstances();
        $logger->info("Fetching $setName for " . count($objects) . ' Objects');
        $counters = $set->getCounters();
        foreach (array_chunk($objects, 100, true) as $chunk) {
            $specs = PerformanceQuerySpecHelper::prepareQuerySpec(
                $set->getObjectType(),
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
