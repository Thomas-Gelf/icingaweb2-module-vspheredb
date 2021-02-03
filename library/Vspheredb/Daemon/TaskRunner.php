<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Exception;
use Evenement\EventEmitterTrait;
use gipfl\LinuxHealth\Memory;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\PacketHandler;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Icinga\Module\Vspheredb\PerformanceData\CompactEntityMetrics;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSets;
use Icinga\Module\Vspheredb\Polling\RequiredPerfData;
use Icinga\Module\Vspheredb\Polling\ServerInfo;
use Icinga\Module\Vspheredb\Polling\ServerSet;
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

    /** @var Db|null */
    protected $db;

    public function __construct(LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->health = (object) [];
    }

    public function setDbConnection(Db $db = null)
    {
        $this->db = $db;

        return $this;
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
     * @param VCenterServer[] $vServers
     */
    public function refreshServerList($vServers)
    {
        $this->refreshRemoteServers($vServers);
        $this->refreshRequiredPerfData(); // Not here
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
                'memory'  => Memory::getUsageForPid($this->pid)
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
                $metrics = new CompactEntityMetrics($notification->getParam('metrics'));
                $this->enrichDataPoints($notification->getParam('vCenterId'), $metrics);
                break;
        }

        return null;
    }

    protected function enrichDataPoints($vCenterId, CompactEntityMetrics $metrics)
    {
        if (! $this->db) {
            $this->logger->warning('Cannot enrich data points, got no DB');
            // TODO: Queue?
            return;
        }
        try {
            $perfSet = PerformanceSets::createInstanceByMeasurementName(
                $metrics->getMeasurementName(),
                VCenter::loadWithAutoIncId($vCenterId, $this->db)
            );
            // Hint: loading all of them might be too much, but WHERE IN (1000 objects)
            //       probably wouldn't be faster
            $tags = $perfSet->fetchObjectTags();
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage() . $e->getTraceAsString());
            return;
        }

        try {
            foreach ($metrics->getDataPoints() as $dataPoint) {
                $instance = $dataPoint->getTag('instance');
                if (isset($tags[$instance])) {
                    $dataPoint->addTags($tags[$instance]);
                    $this->logger->notice(rtrim((string) $dataPoint));
                } else {
                    $this->logger->error("No tags for $instance");
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage() . $e->getTraceAsString());
            return;
        }
    }

    protected function refreshRemoteServers($vServers)
    {
        $set = new ServerSet();
        foreach ($vServers as $server) {
            if (isset($this->running[$server->get('id')])) {
                $set->addServer(ServerInfo::fromServer($server));
            }
        }

        $this->rpc
            ->request('vspheredb.setServers', $set)
            ->then(function ($result) {
                $this->logger->notice('Worker confirmed new server list');
            }, function (Exception $e) {
                $this->logger->error(
                    'Failed to update worker server list' . $e->getMessage()
                );
            });
    }

    protected function refreshRequiredPerfData()
    {
        if (! $this->db) {
            return;
        }

        try {
            $required = RequiredPerfData::fromDb($this->db);
            $this->rpc
                ->request('vspheredb.setRequiredPerfData', $required)
                ->then(function ($result) {
                    $this->logger->notice('Worker confirmed new required PerfData');
                }, function (Exception $e) {
                    $this->logger->error(
                        'Failed to refresh required PerfData: ' . $e->getMessage()
                    );
                });

        } catch (Exception $e) {
            $this->logger->error(
                'Failed to initiate PerfData refresh: ' . $e->getMessage()
            );
        }
    }

    public function __destruct()
    {
        $this->stop();
    }
}
