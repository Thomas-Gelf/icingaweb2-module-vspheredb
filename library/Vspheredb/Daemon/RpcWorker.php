<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Evenement\EventEmitterTrait;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\PacketHandler;
use gipfl\Protocol\JsonRpc\Request;
use Icinga\Module\Vspheredb\Api;
use Icinga\Module\Vspheredb\MappedClass\PerfQuerySpec;
use Icinga\Module\Vspheredb\PerformanceData\CompactEntityMetrics;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceQuerySpecHelper;
use Icinga\Module\Vspheredb\Polling\PerfDataSet;
use Icinga\Module\Vspheredb\Polling\RequiredPerfData;
use Icinga\Module\Vspheredb\Polling\ServerSet;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use function React\Promise\resolve;

class RpcWorker implements PacketHandler
{
    use EventEmitterTrait;

    /** @var LoopInterface */
    protected $loop;

    /** @var LoggerInterface  */
    protected $logger;

    /** @var Connection */
    protected $rpc;

    /** @var ServerSet */
    protected $servers;

    /** @var RequiredPerfData */
    protected $requiredPerfData;

    /** @var Api[] */
    protected $apis = [];

    protected $queue = [];

    public function __construct(Connection $rpc, LoggerInterface $logger, LoopInterface $loop)
    {
        $this->logger = $logger;
        $this->loop = $loop;
        $this->rpc = $rpc;
        $rpc->setHandler($this, 'task');
        $rpc->setHandler($this, 'vspheredb');
        $logger->notice('RPC Worker is ready');
    }

    public function run()
    {
        $health = function () {
            $this->reportHealth();
            $this->logger->notice('RPC Worker health');
        };
        $this->logger->notice('RPC Worker is running');
        $this->loop->addPeriodicTimer(5, $health);
        $this->loop->addPeriodicTimer(1, function () {
            // Check for missing perf data
        });

        $this->loop->futureTick($health);
    }

    /**
     * @param PerfDataSet $set
     * @param PerfQuerySpec[] $specs
     * @return Promise
     */
    protected function fetchSpecs(PerfDataSet $set, array $specs)
    {
        if (! isset($this->apis[$set->getVCenterId()])) {
            $this->scheduleNext();
            return resolve([]);
        }

        $deferred = new Deferred();
        $manager = $this->apis[$set->getVCenterId()]->perfManager();
        $this->loop->futureTick(function () use ($deferred, $manager, $specs, $set) {
            $setName = sprintf(
                '%s (vCenterId=%s)',
                $set->getMeasurementName(),
                $set->getVCenterId()
            );
            $res = $manager->queryPerf($specs);
            if (empty($res)) {
                // TODO: This happens. Why? Inspect set?
                $this->logger->warning("Got an EMPTY result for $setName");
                $deferred->resolve([]);
                return;
            }
            $this->logger->debug('Got ' . count($res) . " results for $setName");
            $result = [];
            foreach ($res as $r) {
                $result[] = CompactEntityMetrics::process($r, $setName, $set->getCounters());
            }

            $deferred->resolve($result);
        });

        return $deferred->promise();
    }

    protected function fetchNext()
    {
        if (empty($this->queue)) {
            return;
        }

        /** @var PerfDataSet $set */
        /** @var PerfQuerySpec[] $specs */
        list($set, $specs) = array_shift($this->queue);
        $this
            ->fetchSpecs($set, $specs)
            ->then(function ($result) {
                $this->logger->notice('Got perf result');
                /** @var CompactEntityMetrics $metrics */
                foreach ($result as $metrics) {
                    $this->rpc->notification('perfData.result', $metrics);
                }
            }, function (\Exception $e) {
                $this->logger->error($e->getMessage());
            })->always(function () {
                $this->scheduleNext();
            });
    }

    protected function scheduleNext()
    {
        $this->loop->futureTick(function () {
            $this->fetchNext();
        });
    }

    protected function fillQueue()
    {
        foreach ($this->requiredPerfData->getSets() as $set) {
            $this->logger->notice(sprintf(
                'Fetching %s from vCenter %s',
                $set->getMeasurementName(),
                $set->getVCenterId()
            ));
            $objects = $set->getRequiredInstances();
            $counters = $set->getCounters();
            $setName = $set->getMeasurementName();

            foreach (array_chunk($objects, 100, true) as $chunk) {
                $specs = PerformanceQuerySpecHelper::prepareQuerySpec(
                    $set->getObjectType(),
                    $counters,
                    $chunk
                );
                $this->queue[] = [
                    $set,
                    $specs
                ];
                $instanceCount = 0;
                foreach ($set->getRequiredInstances() as $object => $instances) {
                    $instanceCount += count($instances);
                }
                $this->logger->debug(sprintf(
                    'Scheduling a chunk with %d objects (%d instances) for %s',
                    count($chunk),
                    $instanceCount,
                    $setName
                ));
            }
        }

        $this->scheduleNext();
    }

    protected function runTask($name, $params)
    {
        $deferred = new Deferred();
        $this->loop->addTimer(3, function () use ($deferred, $name) {
            $deferred->resolve(["Task finished: $name"]);
        });

        return $deferred->promise();
    }

    protected function reportHealth()
    {
        $this->rpc->notification('worker.stats', [
            'cnt' => 0
        ]);
    }

    protected function setServers($servers)
    {
        $this->servers = ServerSet::fromPlainObject($servers);
        foreach ($this->servers->getServers() as $server) {
            $id = $server->get('vcenter_id');
            if (! isset($this->apis[$id])) {
                $this->apis[$id] = Api::forServer($server, $this->logger);
            }
        }

        return true;
    }

    protected function setRequiredPerfData($perfData)
    {
        $this->requiredPerfData = RequiredPerfData::fromPlainObject($perfData);
        $this->fillQueue();

        return true;
    }

    public function handle(Notification $notification)
    {
        try {
            switch ($notification->getMethod()) {
                case 'task.run':
                    return $this->runTask(
                        $notification->getParam('name'),
                        $notification->getParam('params')
                    );
                case 'vspheredb.setServers':
                    return $this->setServers($notification->getParams());
                case 'vspheredb.setRequiredPerfData':
                    return $this->setRequiredPerfData($notification->getParams());
            }
        } catch (\Exception $e) {
            if ($notification instanceof Request) {
                return $e;
            } else {
                $this->logger->error($e->getMessage());
            }
        }

        return null;
    }
}
