<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Exception;
use Evenement\EventEmitterTrait;
use gipfl\IcingaCliDaemon\RetryUnless;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Icinga\Module\Vspheredb\LinuxUtils;
use Icinga\Module\Vspheredb\Rpc\LogProxy;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

class ServerRunner
{
    use EventEmitterTrait;
    use LoggerAwareTrait;

    /** @var IcingaCliRunner */
    protected $icingaCli;

    /** @var VCenterServer */
    protected $server;

    /** @var LoopInterface */
    protected $loop;

    /** @var Process[] */
    protected $running = [];

    /** @var LogProxy */
    protected $logProxy;

    protected $health;

    protected $timer;

    public function __construct(VCenterServer $server, LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->logger->info('Constructing ServerRunner');
        $this->health = (object) [];
        $this->server = $server;
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

        return RetryUnless::succeeding(function () {
            return $this->initializeServer();
        })->run($loop)->then(function ($vCenter) {
            $this->sync($vCenter);
        });
    }

    public function stop()
    {
        if ($this->timer !== null) {
            $this->loop->cancelTimer($this->timer);
            $this->timer = null;
        }
        $this->stopRunningServers();
        $this->server = null;
        $this->icingaCli = null;
        $this->logProxy = null;
        $this->loop = null;
    }

    protected function initializeServer()
    {
        $server = $this->server;
        $hostName = $server->get('host');
        $this->logger->info(sprintf('Initializing vCenter for %s', $hostName));

        $command = $this->prepareCliCommand($this->prepareCliTaskParams('initialize', [
            '--serverId',
            $server->get('id')
        ]));
        $command->on('start', function (Process $process) {
            $this->onProcessStarted($process);
        });

        return $command->run($this->loop)->then(function () use ($server) {
            $connection = $server->getConnection();
            $server = VCenterServer::load($server->get('id'), $connection);
            $db = $connection->getDbAdapter();
            $uuid = $db->fetchOne(
                $db->select()
                    ->from('vcenter', 'instance_uuid')
                    ->where('id = ?', $server->get('vcenter_id'))
            );

            return VCenter::load($uuid, $connection);
        });
    }

    protected function sync(VCenter $vCenter)
    {
        $id = $vCenter->get('id');
        // TODO: log Host?
        $this->logger->info(sprintf('Running vCenter Sync for ID=%s', $id));
        $command = $this->prepareCliCommand($this->prepareCliTaskParams('sync', [
            '--vCenterId',
            $id
        ]));
        $command->on('start', function (Process $process) {
            $this->onProcessStarted($process, true);
        });

        return $command->run($this->loop);
    }

    protected function prepareCliTaskParams($task, $params)
    {
        $result = [
            'vspheredb',
            'task',
            $task,
            '--rpc',
            // TODO: Forward current Log Level?
            // '--verbose',
            '--debug',
        ];

        return \array_merge($result, $params);
    }

    protected function onProcessStarted(Process $process, $mustRun = false)
    {
        $pid = $process->getPid();
        $this->running[$pid] = $process;
        $this->checkRunningProcessHealth();
        $this->emit('processStarted', [$pid]);
        $process->on('exit', function () use ($pid, $mustRun) {
            unset($this->running[$pid]);
            $this->checkRunningProcessHealth();
            $this->emit('processStopped', [$pid]);
            if ($mustRun) {
                $this->emit('failed', [$pid]);
            }
        });
    }

    public function getProcessInfo()
    {
        return $this->health;
    }

    protected function checkRunningProcessHealth()
    {
        $info = [];

        foreach ($this->running as $pid => $process) {
            $info[$pid] = (object) [
                'command' => preg_replace('/^exec /', '', $process->getCommand()),
                'running' => $process->isRunning(),
                'memory'  => LinuxUtils::getMemoryUsageForPid($pid)
            ];
        }

        $this->health = $info;
    }

    protected function prepareCliCommand($arguments = null)
    {
        if ($this->icingaCli === null) {
            $this->icingaCli = IcingaCliRunner::forArgv();
        }

        $command = new IcingaCliRpc($this->icingaCli);

        $command->on('error', function (Exception $e) {
            $this->logger->error(rtrim($e->getMessage()));
            $this->stop();
        });
        if ($arguments) {
            $command->setArguments($arguments);
        }
        if ($this->logProxy) {
            $command->rpc()->setHandler($this->logProxy, 'logger');
        }

        return $command;
    }

    protected function stopRunningServers()
    {
        if (! empty($this->running) && $this->loop === null) {
            $this->logger->warning('Stopping while there is no more loop');
            return;
        }
        foreach ($this->running as $pid => $process) {
            $process->terminate(SIGTERM);
            $this->loop->addTimer(5, function () use ($process, $pid) {
                if ($process->isRunning()) {
                    $this->logger->error("Process $pid is still running, sending SIGKILL");
                    $process->terminate(SIGKILL);
                }
            });
        }
    }

    public function __destruct()
    {
        $this->stop();
    }
}
