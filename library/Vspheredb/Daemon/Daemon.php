<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Exception;
use gipfl\LinuxHealth\Memory;
use gipfl\Log\Logger;
use gipfl\SystemD\NotifySystemD;
use gipfl\IcingaCliDaemon\RetryUnless;
use Icinga\Application\Platform;
use Icinga\Data\ConfigObject;
use Icinga\Module\Vspheredb\CliUtil;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\Db\Migrations;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Icinga\Module\Vspheredb\Rpc\LogProxy;
use Icinga\Module\Vspheredb\Util;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use RuntimeException;

class Daemon
{
    use StateMachine;

    /** @var LoopInterface */
    private $loop;

    /** @var array|null */
    protected $dbConfig;

    /** @var Db */
    protected $connection;

    /** @var object */
    protected $processInfo;

    /** @var ServerRunner[] */
    protected $running = [];

    /** @var TaskRunner */
    protected $worker;

    /** @var array [VCenterServer->get('id') => true] */
    protected $blackListed = [];

    protected $delayOnFailed = 5;

    /** @var NotifySystemD|boolean */
    protected $systemd;

    protected $lastCliTitle;

    /** @var Logger */
    protected $logger;

    /** @var DbLogger */
    protected $dbLogger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->detectProcessInfo();
        $this->dbLogger = new DbLogger(
            $this->processInfo->instance_uuid,
            $this->processInfo->fqdn,
            $this->processInfo->pid
        );
        $this->dbLogger->setLogger($logger);
        $logger->addWriter($this->dbLogger);
    }

    protected function detectProcessInfo()
    {
        $this->processInfo = (object) [
            'instance_uuid' => Uuid::uuid4()->getBytes(),
            // 'ts_startup'      => Util::currentTimestamp(),
            'pid'           => posix_getpid(),
            'fqdn'          => Platform::getFqdn(),
            'username'      => Platform::getPhpUser(),
            'php_version'   => Platform::getPhpVersion(),
        ];
    }

    public function run(LoopInterface $loop = null)
    {
        CliUtil::setTitle('Icinga::vSphereDB::main');
        $ownLoop = $loop === null;
        if ($ownLoop) {
            $loop = Loop::create();
        }

        $this->loop = $loop;
        $this->registerSignalHandlers();
        $this->systemd = NotifySystemD::ifRequired($loop);
        $this->makeReady();
        $this->worker = new TaskRunner($this->logger);
        $logProxy = new LogProxy($this->logger);
        $logProxy->setPrefix("[worker] ");
        $this->worker->forwardLog($logProxy);
        $this->worker->run($loop);
        $refresh = function () {
            $this->refreshConfiguredServers();
            $this->refreshCliTitle();
        };
        $loop->addPeriodicTimer(3, $refresh);
        $loop->futureTick($refresh);

        if ($ownLoop) {
            $loop->run();
        }
    }

    protected function refreshCliTitle()
    {
        $title = sprintf(
            'Icinga::vSphereDB::main: %d active runner%s',
            count($this->running),
            count($this->running) === 1 ? '' : 's'
        );

        if ($title !== $this->lastCliTitle) {
            CliUtil::setTitle($title);
            $this->lastCliTitle = $title;
        }
    }

    protected function onConnected()
    {
        $this->setDaemonStatus('Connected to the database', 'notice');
        $this->worker->setDbConnection($this->connection);

        $fail = function (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->setState('failed');
        };
        $refresh = function () {
            $this->refreshMyState();
        };
        try {
            if ($this->hasSchema()) {
                $this->runDbCleanup();
            } else {
                $this->setDaemonStatus('DB has no schema', 'error');
                $fail(new Exception('DB has no schema'));
            }
        } catch (Exception $e) {
            $fail($e);
        }

        RetryUnless::failing($refresh)
            ->setInterval(5)
            ->run($this->loop)
            ->then($fail);
    }

    protected function hasSchema()
    {
        return (new Migrations($this->connection))->hasSchema();
    }

    protected function runDbCleanup()
    {
        $this->setDaemonStatus('Running DB cleanup (this could take some time)', 'notice');
        $db = $this->connection->getDbAdapter();
        // Delete all damon entries older than the two most recently running daemons
        $where = <<<WHERE
        ts_last_refresh < (
          SELECT ts_last_refresh FROM (
            SELECT ts_last_refresh
              FROM vspheredb_daemon
             ORDER BY ts_last_refresh DESC
              LIMIT 2
          ) toptwo ORDER BY ts_last_refresh ASC LIMIT 1
        )
WHERE;
        $result = $db->delete('vspheredb_daemon', $where);
        if ($result > 0) {
            $this->setDaemonStatus(
                "Removed information related to $result formerly running daemon instance(s)",
                'info'
            );
        }
        $db->query('OPTIMIZE TABLE vspheredb_daemon')->execute();
        $where = <<<QUERY
        NOT EXISTS (
          SELECT 1 FROM vspheredb_daemon d
           WHERE d.instance_uuid = vspheredb_daemonlog.instance_uuid
        )
QUERY;
        $result = $db->delete('vspheredb_daemonlog', $where);
        if ($result > 0) {
            $this->setDaemonStatus(
                "Removed $result outdated daemon log lines, optimizing table",
                'info'
            );
        }
        $db->query('OPTIMIZE TABLE vspheredb_daemonlog')->execute();
        $this->setDaemonStatus('DB has been cleaned up', 'notice');
    }

    protected function setDaemonStatus($status, $logLevel = null, $sendReady = false)
    {
        if ($this->logger && $logLevel !== null) {
            $this->logger->$logLevel($status);
        }
        if ($this->systemd) {
            if ($sendReady) {
                $this->systemd->setReady($status);
            } else {
                $this->systemd->setStatus($status);
            }
        }
    }

    protected function onDisconnected()
    {
        $this->setDaemonStatus('Database connection has been closed', 'error');
        // state cannot be shutdown
        $this->worker->setDbConnection(null);
        if ($this->getState() !== 'shutdown') {
            $this->reconnectToDb();
        }
    }

    protected function makeReady()
    {
        // Hint: currently disconnected isn't triggered.
        $this->onTransition(['started', 'failed', 'disconnected'], 'connected', function () {
            $this->onConnected();
        })->onTransition('connected', 'disconnected', function () {
            $this->onDisconnected();
        })->onTransition([
            'started',
            'connected',
            'disconnected',
            'failed',
        ], 'shutdown', function () {
            $this->shutdown();
        })->onTransition(['connected', 'started'], 'failed', function () {
            $this->logger->error('Failed. Will try to reconnect to the Database');
            $this->setDaemonStatus('Failed. Will try to reconnect to the Database');
            $this->eventuallyDisconnectFromDb();
        })->onTransition(['started', 'connected', 'disconnected'], 'failed', function () {
            $this->onFailed();
        })->onTransition('failed', 'disconnected', function () {
        });

        // External events:
        $this->runConfigWatch();
        $this->setDaemonStatus('Ready to run', 'notice', true);
        $this->initializeStateMachine('started');
    }

    protected function stopRunners()
    {
        // There is some redundancy here to really make sure they're gone
        if (! empty($this->running)) {
            $this->logger->info('Stopping remaining child processes');
        }
        foreach ($this->running as $serverRunner) {
            $serverRunner->stop();
        }

        foreach (array_keys($this->running) as $id) {
            unset($this->running[$id]);
        }
        $this->running = [];
        $this->loop->addTimer(1, function () {
            gc_collect_cycles();
        });
    }

    protected function onFailed()
    {
        $delay = $this->delayOnFailed;
        $this->logger->warning("Failed. Reconnecting in ${delay}s");
        $this->loop->addTimer($delay, function () {
            $this->reconnectToDb();
        });
        $this->stopRunners();
    }

    protected function reconnectToDb()
    {
        $this->setDaemonStatus('Reconnecting to DB', 'notice');
        $this->eventuallyDisconnectFromDb();
        RetryUnless::succeeding(function () {
            if ($this->dbConfig === null) {
                throw new RuntimeException('Got no valid DB configuration');
            }

            return $this->connectToDb($this->dbConfig);
        })->slowDownAfter(5, 15)->run($this->loop)->then(function (Db $connection) {
            $this->connection = $connection;
            $this->setState('connected');
        });
    }

    protected function connectToDb($config)
    {
        $connection = new Db(new ConfigObject($config));
        $connection->getDbAdapter()->getConnection();
        $this->dbLogger->setDb($connection);

        return $connection;
    }

    protected function eventuallyDisconnectFromDb()
    {
        if ($this->connection !== null) {
            try {
                $this->refreshMyState();
                $this->connection->getDbAdapter()->closeConnection();
                if (! in_array($this->getState(), ['disconnected', 'shutdown'])) {
                    $this->setState('disconnected');
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'Ignored an error while closing the DB connection: '
                    . $e->getMessage()
                );
            }
            $this->connection = null;
            $this->dbLogger->setDb(null);
        }
    }

    protected function runConfigWatch()
    {
        $config = new ConfigWatch();
        $config->on('dbConfig', function ($config) {
            if ($config === null) {
                if ($this->dbConfig === null) {
                    $this->setDaemonStatus('Got no valid DB configuration', 'error');
                } else {
                    $this->setDaemonStatus('There is no longer a valid DB configuration', 'error');
                    $this->dbConfig = $config;
                    $this->stopRunners();
                    $this->reconnectToDb();
                }
            } else {
                $this->setDaemonStatus('DB configuration loaded', 'info');
                $this->dbConfig = $config;
                $this->stopRunners();
                $this->reconnectToDb();
            }
        });
        $config->run($this->loop);
    }

    protected function registerSignalHandlers()
    {
        $func = function ($signal) use (&$func) {
            $this->shutdownWithSignal($signal, $func);
        };
        $this->loop->addSignal(SIGINT, $func);
        $this->loop->addSignal(SIGTERM, $func);
    }

    protected function shutdownWithSignal($signal, &$func)
    {
        $this->loop->removeSignal($signal, $func);
        $this->setState('shutdown');
    }

    protected function shutdown()
    {
        try {
            $this->setDaemonStatus('Shutting down', 'notice');
            $this->eventuallyDisconnectFromDb();
        } catch (Exception $e) {
            if ($this->systemd) {
                $this->systemd->setError(sprintf(
                    'Failed to safely shutdown, stopping anyways: %s',
                    $e->getMessage()
                ));
            }
            $this->logger->error(sprintf(
                'Failed to safely shutdown, stopping anyways: %s',
                $e->getMessage()
            ));
        }
        $this->loop->stop();
    }

    protected function refreshMyState()
    {
        if ($this->connection === null) {
            return;
        }
        try {
            $db = $this->connection->getDbAdapter();
            $updated = $db->update('vspheredb_daemon', [
                'instance_uuid' => $this->processInfo->instance_uuid,
                'ts_last_refresh' => Util::currentTimestamp(),
                'process_info' => json_encode($this->getProcessInfo()),
            ], $db->quoteInto('instance_uuid = ?', $this->processInfo->instance_uuid));

            if (!$updated) {
                $this->insertMyState($db);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->eventuallyDisconnectFromDb();
        }
    }

    /**
     * @param \Zend_Db_Adapter_Abstract $db
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function insertMyState(\Zend_Db_Adapter_Abstract $db)
    {
        $db->insert('vspheredb_daemon', [
            'instance_uuid' => $this->processInfo->instance_uuid,
            'ts_last_refresh' => Util::currentTimestamp(),
            'process_info'    => json_encode($this->getProcessInfo()),
        ] + (array) $this->processInfo);
    }

    protected function truncateDaemonLog(\Zend_Db_Adapter_Abstract $db)
    {
        // TODO.
    }

    protected function getProcessInfo()
    {
        global $argv;
        $pid = $this->processInfo->pid;
        $info = (object) [$pid => (object) [
            'command' => implode(' ', $argv),
            'running' => true,
            'memory'  => Memory::getUsageForPid($pid)
        ]];

        foreach ($this->running as $runner) {
            foreach ($runner->getProcessInfo() as $pid => $details) {
                $info->$pid = $details;
            }
        }

        return $info;
    }

    /**
     * @param VCenter[] $vCenters
     * @param VCenterServer[] $vServers
     * @return bool
     */
    protected function checkRequiredProcesses($vCenters, $vServers)
    {
        $required = [];
        $candidates = [];
        foreach ($vServers as $id => $server) {
            $id = (int) $id;
            if (isset($this->blackListed[$id])) {
                continue;
            }
            if ($server->get('enabled') !== 'y') {
                continue;
            }
            $vCenterId = $server->get('vcenter_id');
            if ($vCenterId === null) {
                $required[$id] = $server;
            } else {
                $candidates[$vCenterId][$id] = $server;
            }
        }

        foreach ($vCenters as $id => $vCenter) {
            if (isset($candidates[$id])) {
                // Pick the first one. TODO: mark failing ones, try others
                foreach ($candidates[$id] as $serverId => $server) {
                    $required[$serverId] = $server;
                    break;
                }
            } else {
                // Logger::info(sprintf('vCenter ID=%s has no Server', $id));
            }
        }

        $changed = false;
        foreach ($required as $id => $vServer) {
            if (! isset($this->running[$id])) {
                $changed = true;
                $this->logger->info("vCenter ID=$id is now starting");
                $this->running[$id] = $this->runServer($vServer);
            }
        }

        foreach ($this->running as $id => $serverRunner) {
            if (! isset($required[$id])) {
                $changed = true;
                $this->running[$id]->stop();
                unset($this->running[$id]);
            }
        }
        $this->refreshCliTitle();
        gc_collect_cycles();

        return $changed;
    }

    /**
     * @param VCenterServer $server
     * @return ServerRunner
     */
    protected function runServer(VCenterServer $server)
    {
        $vCenterId = $server->get('vcenter_id');
        if ($vCenterId) {
            $vCenter = VCenter::loadWithAutoIncId($vCenterId, $this->connection);
            $label = $vCenter->get('name');
        } else {
            $vCenter = null;
            $label = $server->get('host');
        }
        $runner = new ServerRunner($server, $this->logger);
        $serverId = $server->get('id');
        $this->logger->info("Starting for $label");
        $logProxy = new LogProxy($this->logger);
        $logProxy->setPrefix("[$label] ");
        if ($vCenter) {
            $logProxy->setVCenter($vCenter);
        }
        $logProxy->setServer($server);
        $runner->forwardLog($logProxy);
        $runner->run($this->loop)->otherwise(function () use ($label, $serverId) {
            $this->pauseFailedRunner($label, $serverId);
        });
        $runner->on('processStopped', function ($pid) use ($label, $serverId) {
            $this->logger->debug("Pid $pid stopped for $label");
            $this->refreshMyState();
        });
        $runner->on('failed', function ($pid) use ($label, $serverId) {
            $this->pauseFailedRunner($label, $serverId, $pid);
        });

        return $runner;
    }

    protected function pauseFailedRunner($label, $serverId, $pid = null)
    {
        if (! isset($this->running[$serverId])) {
            $this->logger->error("Server for vCenterID=$label failed, there is no related runner");
            return;
        }
        if ($pid === null) {
            $pidInfo = '';
        } else {
            $pidInfo = " (PID $pid)";
        }
        $this->logger->error("Server for vCenterID=$label failed$pidInfo, will try again in 30 seconds");
        $this->running[$serverId]->stop();
        unset($this->running[$serverId]);
        $this->loop->addTimer(2, function () {
            gc_collect_cycles();
        });
        $this->blackListed[$serverId] = true;
        $this->loop->addTimer(30, function () use ($serverId) {
            unset($this->blackListed[$serverId]);
        });
    }

    protected function refreshConfiguredServers()
    {
        try {
            if ($this->getState() === 'connected') {
                $vCenters = VCenter::loadAll($this->connection, null, 'id');
                $vServers = VCenterServer::loadAll($this->connection, null, 'id');
                if ($this->checkRequiredProcesses($vCenters, $vServers)) {
                    $this->worker->refreshServerList($vServers);
                }
            } else {
                $this->stopRunners();
            }
        } catch (Exception $e) {
            $this->setDaemonStatus('Failed to refresh server list', 'error');
            $this->logger->error($e->getMessage());
            $this->setState('failed');
        }
    }
}
