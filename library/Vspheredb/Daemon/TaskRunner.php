<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Exception;
use Evenement\EventEmitterTrait;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\PacketHandler;
use Icinga\Module\Vspheredb\LinuxUtils;
use Icinga\Module\Vspheredb\PerformanceData\CompactEntityMetrics;
use Icinga\Module\Vspheredb\Rpc\LogProxy;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class TaskRunner implements PacketHandler
{
    use EventEmitterTrait;
    use LoggerAwareTrait;

    /** @var LoopInterface */
    protected $loop;

    /** @var LogProxy */
    protected $logProxy;

    protected $health;

    protected $timer;

    /** @var Process */
    protected $process;

    protected $pid;

    /** @var Connection */
    protected $rpc;

    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->health = (object) [];
    }

    public function forwardLog(LogProxy $logProxy)
    {
        $this->logProxy = $logProxy;

        return $this;
    }

    /**
     * @param LoopInterface $loop
     * @return \React\Promise\Promise
     */
    public function run(LoopInterface $loop)
    {
        $this->loop = $loop;
        $health = function () {
            $this->checkRunningProcessHealth();
        };
        $this->timer = $loop->addPeriodicTimer(1, $health);

        $command = new IcingaCliRpc();
        $command->setArguments(['vspheredb', 'task', 'worker', '--rpc', '--debug']);
        $command->on('start', function (Process $process) {
            $this->onProcessStarted($process, true);
        });
        $command->on('error', function (Exception $e) {
            $this->logger->error(rtrim($e->getMessage()));
            $this->stop();
        });
        if ($this->logProxy) {
            $command->rpc()->setHandler($this->logProxy, 'logger');
        }
        $command->rpc()->setHandler($this, 'perfData');
        $this->rpc = $command->rpc();

        return $command->run($this->loop);
    }

    /**
     * @return Connection
     */
    public function rpc()
    {
        return $this->rpc;
    }

    public function stop()
    {
        if ($this->timer !== null) {
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
        $this->stopRunningServers();
        $this->logProxy = null;
        $this->loop = null;
    }

    protected function onProcessStarted(Process $process, $mustRun = false)
    {
        $this->pid = $process->getPid();
        $this->process = $process;
        $this->checkRunningProcessHealth();
        $this->emit('processStarted', [$this->pid]);
        $process->on('exit', function () use ($mustRun) {
            $this->process = null;
            $this->pid = null;
            $this->checkRunningProcessHealth();
            $this->emit('processStopped', [$this->pid]);
            if ($mustRun) {
                $this->emit('failed', [$this->pid]);
            }
        });
    }

    public function getProcessInfo()
    {
        return $this->health;
    }

    protected function checkRunningProcessHealth()
    {
        if ($this->process === null) {
            $this->health = [];
            return;
        }

        $info = [
            $this->pid => (object) [
                'command' => preg_replace('/^exec /', '', $this->process->getCommand()),
                'running' => $this->process->isRunning(),
                'memory'  => LinuxUtils::getMemoryUsageForPid($this->pid)
            ]
        ];

        $this->health = $info;
    }

    protected function stopRunningServers()
    {
        if ($this->process === null) {
            return;
        }
        $process = $this->process;
        $pid = $this->pid;
        $process->terminate(SIGTERM);
        $this->loop->addTimer(5, function () use ($process, $pid) {
            if ($process->isRunning()) {
                $this->logger->error("Process $pid is still running, sending SIGKILL");
                $process->terminate(SIGKILL);
            }
        });
    }


    public function handle(Notification $notification)
    {
        switch ($notification->getMethod()) {
            case 'perfData.result':
                $metrics = new CompactEntityMetrics($notification->getParams());
                $this->logger->notice('Perfdata: ' . print_r(, 1));
                break;
        }

        return null;
    }

    public function __destruct()
    {
        $this->stop();
    }
}
