<?php

namespace Icinga\Module\Vspheredb\PerformanceData;

use Clue\React\Buzz\Message\ResponseException;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\MappedClass\PerfEntityMetricCSV;
use Icinga\Module\Vspheredb\PerformanceData\InfluxDb\AsyncInfluxDbWriter;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSet;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSets;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;

class InfluxDbStreamer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var VCenter */
    protected $vCenter;

    /** @var LoopInterface $loop */
    protected $loop;

    /** @var AsyncInfluxDbWriter */
    protected $influx;

    protected $idle = true;

    protected $baseUrl;

    protected $dbName;

    protected $fetchedMetrics = 0;

    protected $sentLines = 0;

    protected $linesWaitingForInflux = 0;

    protected $pendingLines = 0;

    protected $maxPendingLines = 5000;

    protected $queue = [];

    public function __construct(VCenter $vCenter, LoopInterface $loop)
    {
        $this->vCenter = $vCenter;
        $this->loop = $loop;
        $this->setLogger(new NullLogger());
    }

    /**
     * @param $baseUrl
     * @param $dbName
     */
    public function streamTo($baseUrl, $dbName)
    {
        $this->logger->info("Streaming to $baseUrl");
        if ($this->influx !== null) {
            // throw new \RuntimeException('Cannot start to stream while streaming');
        }
        $this->influx = new AsyncInfluxDbWriter($baseUrl, $this->loop);
        $this->idle = false;

        foreach (PerformanceSets::listAvailableSets() as $class) {
            $this->loop->futureTick(function () use ($class, $dbName) {
                $this->streamSet(new $class($this->vCenter), $dbName);
            });
        }
    }

    /**
     * @param PerformanceSet $performanceSet
     * @param $dbName
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     */
    public function streamSet(PerformanceSet $performanceSet, $dbName)
    {
        $this->logger->info('Starting to stream set: ' . $performanceSet->getMeasurementName());
        $this->loop->addPeriodicTimer(3, function () use ($dbName) {
            $this->logger->debug('New 3sec timer');
            $this->sendNextBatch($dbName);
        });
        $counters = $performanceSet->getCounters();
        $mapper = new PerfMetricMapper($counters);
        /** @var PerfEntityMetricCSV $metric */
        $tags = $performanceSet->fetchObjectTags();
        $metrics = $performanceSet->fetch();
        while ($this->pendingLines < $this->maxPendingLines && $metrics->valid()) {
            $metric = $metrics->current();
            $this->fetchedMetrics += count($metric->value);
            $this->queue[] = $mapper->makeInfluxDataPoints(
                $metric,
                $performanceSet->getMeasurementName(),
                $tags
            );
            $metrics->next();
        }

        // $this->flushQueue($dbName, true);
    }

    protected function sendNextBatch($dbName)
    {
        if (empty($this->queue)) {
            return;
        }

        $batch = \array_shift($this->queue);
        // $lines = [];
        // $lines = array_merge($lines, $batch);

        $linesWaitingForInflux = \count($batch);
        $this->influx->send($dbName, $batch)->then(function () use (&$linesWaitingForInflux, $dbName) {
            $this->logger->info(sprintf(
                'Sent %d lines to InfluxDB',
                $linesWaitingForInflux
            ));
            $this->loop->futureTick(function () use ($dbName) {
                $this->sendNextBatch($dbName);
            });
        })->otherwise(function (\Exception $e) use (&$linesWaitingForInflux) {
            $this->logger->error(sprintf(
                'Failed to send %d lines to InfluxDB: %s',
                $linesWaitingForInflux,
                $e->getMessage()
            ));
            if ($e instanceof ResponseException) {
                $this->logger->error($e->getResponse()->getBody());
            }
        })->always(function () use (&$linesWaitingForInflux) {
            $linesWaitingForInflux = 0;
            $this->idle = true;
        });
        // $this->linesWaitingForInflux = $this->pendingLines;
        // $this->pendingLines = 0;
    }

    protected function flushQueue($dbName, $force = false)
    {
        if (empty($this->queue)) {
            return;
        }
        if ($force || $this->pendingLines >= $this->maxPendingLines) {
            $batch = [];
            foreach ($this->queue as $p1) {
                foreach ($p1 as $p2) {
                    $batch[] = $p2;
                }
            }
            $this->linesWaitingForInflux = count($batch);
            $this->influx->send($dbName, $batch)->then(function () {
                $this->logger->debug(sprintf(
                    'Sent %d lines to InfluxDB',
                    $this->linesWaitingForInflux
                ));
            })->otherwise(function (\Exception $e) {
                $this->logger->error(sprintf(
                    'Failed to send %d lines to InfluxDB: %s',
                    $this->linesWaitingForInflux,
                    $e->getMessage()
                ));
            })->always(function () {
                $this->linesWaitingForInflux = 0;
            });
            $this->queue = [];
            $this->linesWaitingForInflux = $this->pendingLines;
            $this->pendingLines = 0;
        }
    }

    public function isIdle()
    {
        return $this->idle;
    }
}
