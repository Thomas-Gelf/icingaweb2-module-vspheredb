<?php

namespace Icinga\Module\Vspheredb\Clicommands;

use Exception;
use Icinga\Module\Vspheredb\CliUtil;
use Icinga\Module\Vspheredb\Daemon\PerfDataRunner;
use Icinga\Module\Vspheredb\Daemon\SyncRunner;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\VmDiskTagHelper;
use Icinga\Module\Vspheredb\Sync\VCenterInitialization;

/**
 * Sync a vCenter or ESXi host
 */
class TaskCommand extends Command
{
    /**
     * Connect to a vCenter, create/update it's base definition
     *
     * USAGE
     *
     * icingacli vsphere task initialize --serverId <id> [--rpc]
     */
    public function initializeAction()
    {
        $this->loop()->futureTick(function () {
            $hostname = null;
            try {
                CliUtil::setTitle('Icinga::vSphereDB::initialize');
                $server = $this->requireVCenterServer();
                $hostname = $server->get('host');
                CliUtil::setTitle(sprintf('Icinga::vSphereDB::initialize (%s)', $hostname));
                VCenterInitialization::initializeFromServer($server, $this->logger);
                $this->loop()->stop();
            } catch (Exception $e) {
                $this->failFriendly('initialize', $e, $hostname ?: '-');
            }
        });
        $this->loop()->run();
    }

    /**
     * Sync all objects
     *
     * Still a prototype
     *
     * USAGE
     *
     * icingacli vsphere task sync --vCenterId <id> [--rpc]
     */
    public function syncAction()
    {
        $this->loop()->futureTick(function () {
            $subject = null;
            try {
                CliUtil::setTitle('Icinga::vSphereDB::sync');
                $vCenter = $this->requireVCenter();
                $subject = $vCenter->get('name');
                if ($subject === null) {
                    $subject = 'unknown';
                }
                CliUtil::setTitle(sprintf('Icinga::vSphereDB::sync (%s)', $subject));
                $time = microtime(true);
                (new SyncRunner($vCenter, $this->logger))
                    ->showTrace($this->showTrace())
                    ->on('beginTask', function ($taskName) use ($subject, &$time) {
                        CliUtil::setTitle(sprintf('Icinga::vSphereDB::sync (%s: %s)', $subject, $taskName));
                        $time = microtime(true);
                    })
                    ->on('endTask', function ($taskName) use ($subject, &$time) {
                        CliUtil::setTitle(sprintf('Icinga::vSphereDB::sync (%s)', $subject));
                        $duration = microtime(true) - $time;
                        $this->logger->debug(sprintf(
                            'Task "%s" took %.2Fms on %s',
                            $taskName,
                            ($duration * 1000),
                            $subject
                        ));
                    })
                    ->on('dbError', function (\Zend_Db_Exception $e) use ($subject) {
                        CliUtil::setTitle(sprintf('Icinga::vSphereDB::sync (%s: FAILED)', $subject));
                        $this->failFriendly('sync', $e, $subject);
                    })
                    ->run($this->loop())
                    ->then(function () use ($subject) {
                        $this->failFriendly('sync', 'Sync stopped. Should not happen', $subject);
                    })->otherwise(function ($reason = null) use ($subject) {
                        $this->failFriendly('sync', $reason, $subject);
                    });
            } catch (Exception $e) {
                $this->failFriendly('sync', $e, $subject);
            }
        });
        $this->loop()->run();
    }

    /**
     * Sync all objects
     *
     * Still a prototype
     *
     * USAGE
     *
     * icingacli vsphere task perfdata --vCenterId <id> [--rpc]
     */
    public function perfdataActionx()
    {
        $this->loop()->futureTick(function () {
            $subject = null;
            try {
                CliUtil::setTitle('Icinga::vSphereDB::perfdata');
                $vCenter = $this->requireVCenter();
                $subject = $vCenter->get('name');
                CliUtil::setTitle(sprintf('Icinga::vSphereDB::perfdata (%s)', $subject));
                $time = microtime(true);
                (new PerfDataRunner($vCenter, $this->logger))
                    ->on('beginTask', function ($taskName) use ($subject, &$time) {
                        CliUtil::setTitle(sprintf('Icinga::vSphereDB::perfdata (%s: %s)', $subject, $taskName));
                        $time = microtime(true);
                    })
                    ->on('endTask', function ($taskName) use ($subject, &$time) {
                        CliUtil::setTitle(sprintf('Icinga::vSphereDB::perfdata (%s)', $subject));
                        $duration = microtime(true) - $time;
                        $this->logger->debug(sprintf(
                            'Task "%s" took %.2Fms on %s',
                            $taskName,
                            ($duration * 1000),
                            $subject
                        ));
                    })
                    ->on('dbError', function (\Zend_Db_Exception $e) use ($subject) {
                        CliUtil::setTitle(sprintf('Icinga::vSphereDB::perfdata (%s: FAILED)', $subject));
                        $this->failFriendly('perfdata', $e, $subject);
                    })
                    ->run($this->loop())
                    ->then(function () use ($subject) {
                        $this->failFriendly('perfdata', 'Runner stopped. Should not happen', $subject);
                    })->otherwise(function ($reason = null) use ($subject) {
                        $this->failFriendly('perfdata', $reason, $subject);
                    });
            } catch (Exception $e) {
                $this->failFriendly('perfdata', $e, $subject);
            }
        });
        $this->loop()->run();
    }

    public function demoActionx()
    {
        $vCenter = $this->requireVCenter();
        $helper = new VmDiskTagHelper($vCenter);
        print_r($helper->fetchVmTags());
    }

    protected function requireVCenter()
    {
        return VCenter::loadWithAutoIncId(
            $this->requiredParam('vCenterId'),
            Db::newConfiguredInstance()
        );
    }

    protected function requireVCenterServer()
    {
        return VCenterServer::loadWithAutoIncId(
            $this->requiredParam('serverId'),
            Db::newConfiguredInstance()
        );
    }
}
