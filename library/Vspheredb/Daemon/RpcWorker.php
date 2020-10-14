<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Evenement\EventEmitterTrait;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\PacketHandler;
use Icinga\Module\Vspheredb\Api;
use Icinga\Module\Vspheredb\PerformanceData\ChunkedPerfdataReader;
use Icinga\Module\Vspheredb\PerformanceData\CompactEntityMetrics;
use Icinga\Module\Vspheredb\Polling\RequiredPerfData;
use Icinga\Module\Vspheredb\Polling\ServerSet;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

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
        $this->loop->futureTick($health);
    }

    protected function fetchOnce()
    {
        foreach ($this->requiredPerfData->getSets() as $set) {
            $api = $this->apis[$set->getVCenterId()];
            $this->logger->notice(sprintf(
                'Fetching %s from vCenter %s',
                $set->getMeasurementName(),
                $set->getVCenterId()
            ));
            $metrics = ChunkedPerfdataReader::fetchSet($set, $api, $this->logger);
            /** @var CompactEntityMetrics $metric */
            foreach ($metrics as $metric) {
                $this->rpc->notification('perfData.result', $metric);
            }
            $this->logger->notice(sprintf(
                'Done with %s from vCenter %s',
                $set->getMeasurementName(),
                $set->getVCenterId()
            ));
        }
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
            $id = $server->get('id');
            if (! isset($this->apis[$id])) {
                $this->apis[$id] = Api::forServer($server, $this->logger);
            }
        }

        return true;
    }

    protected function setRequiredPerfData($perfData)
    {
        $this->requiredPerfData = RequiredPerfData::fromPlainObject($perfData);
        $this->fetchOnce();
        return true;
    }

    public function handle(Notification $notification)
    {
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

        return null;
    }
}
