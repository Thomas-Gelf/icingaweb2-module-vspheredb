<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Evenement\EventEmitterTrait;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\PacketHandler;
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

    protected $servers;

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
        $this->logger->notice('GOT SERVERS');
        $this->servers = $servers;
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
        }

        return null;
    }
}
