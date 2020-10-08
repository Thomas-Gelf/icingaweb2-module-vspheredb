<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Evenement\EventEmitterTrait;
use Exception;
use Icinga\Module\Vspheredb\DbObject\ComputeResource;
use Icinga\Module\Vspheredb\DbObject\Datastore;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\DbObject\StoragePod;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use Icinga\Module\Vspheredb\EventManager;
use Icinga\Module\Vspheredb\Sync\SyncHostHardware;
use Icinga\Module\Vspheredb\Sync\SyncHostNetwork;
use Icinga\Module\Vspheredb\Sync\SyncHostSensors;
use Icinga\Module\Vspheredb\Sync\SyncManagedObjectReferences;
use Icinga\Module\Vspheredb\Sync\SyncPerfCounterInfo;
use Icinga\Module\Vspheredb\Sync\SyncPerfCounters;
use Icinga\Module\Vspheredb\Sync\SyncQuickStats;
use Icinga\Module\Vspheredb\Sync\SyncVmDatastoreUsage;
use Icinga\Module\Vspheredb\Sync\SyncVmDiskUsage;
use Icinga\Module\Vspheredb\Sync\SyncVmHardware;
use Icinga\Module\Vspheredb\Sync\SyncVmSnapshots;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Zend_Db_Exception as ZfDbException;

class SyncRunner
{
    use EventEmitterTrait;
    use LoggerAwareTrait;

    /** @var VCenter */
    protected $vCenter;

    /** @var EventManager */
    protected $eventManager;

    /** @var LoopInterface */
    protected $loop;

    protected $availableTasks = [];

    protected $immediateTasks = [];

    /** @var Deferred */
    protected $deferred;

    /** @var bool */
    protected $showTrace = false;

    protected $taskNames = [
        'customFields'     => 'Custom Fields Inventory',
        'moRefs'           => 'Managed Object References',
        'quickStats'       => 'Quick Stats',
        'computeResources' => 'Compute Resources',
        'hostSystems'      => 'Host Systems',
        'virtualMachines'  => 'Virtual Machines',
        'storagePods'      => 'Storage Pods',
        'dataStores'       => 'Data Stores',
        'hostHardware'     => 'Host Hardware',
        'hostSensors'      => 'Host Sensors',
        'hostNetwork'      => 'Host Network',
        'vmHardware'       => 'VM Hardware',
        'vmDiskUsage'      => 'VM Disk Usage',
        'vmDatastoreUsage' => 'VM DataStore Usage',
        'vmSnapshots'      => 'VM Snapshots',
        'eventStream'      => 'Event Stream',
        'perfCounters'     => 'Performance Counters',
        'perfCounterInfo'  => 'Performance Counter Information',
    ];

    /**
     * SyncRunner constructor.
     * @param VCenter $vCenter
     * @param LoggerInterface $logger
     */
    public function __construct(VCenter $vCenter, LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->vCenter = $vCenter;
        $this->availableTasks = [
            'moRefs' => function () {
                (new SyncManagedObjectReferences($this->vCenter))->sync();
            },
            'quickStats' => function () {
                (new SyncQuickStats($this->vCenter))->run();
            },
            'computeResources' => function () {
                ComputeResource::syncFromApi($this->vCenter, $this->logger);
            },
            'hostSystems' => function () {
                HostSystem::syncFromApi($this->vCenter, $this->logger);
            },
            'virtualMachines' => function () {
                VirtualMachine::syncFromApi($this->vCenter, $this->logger);
            },
            'storagePods' => function () {
                StoragePod::syncFromApi($this->vCenter, $this->logger);
            },
            'dataStores' => function () {
                Datastore::syncFromApi($this->vCenter, $this->logger);
            },
            'hostHardware' => function () {
                (new SyncHostHardware($this->vCenter, $this->logger))->run();
            },
            'hostSensors' => function () {
                (new SyncHostSensors($this->vCenter, $this->logger))->run();
            },
            'hostNetwork' => function () {
                (new SyncHostNetwork($this->vCenter, $this->logger))->run();
            },
            'vmHardware' => function () {
                (new SyncVmHardware($this->vCenter, $this->logger))->run();
            },
            'vmDiskUsage' => function () {
                (new SyncVmDiskUsage($this->vCenter, $this->logger))->run();
            },
            'vmDatastoreUsage' => function () {
                (new SyncVmDatastoreUsage($this->vCenter, $this->logger))->run();
            },
            'vmSnapshots' => function () {
                (new SyncVmSnapshots($this->vCenter, $this->logger))->run();
            },
            'eventStream' => function () {
                $this->streamEvents();
            },
            'perfCounters' => function () {
                // Currently unused.
                (new SyncPerfCounters($this->vCenter, $this->logger))->run();
            },
            'perfCounterInfo' => function () {
                (new SyncPerfCounterInfo($this->vCenter, $this->logger))->run();
            },
        ];
    }

    /**
     * @param LoopInterface $loop
     * @return \React\Promise\Promise
     */
    public function run(LoopInterface $loop)
    {
        $this->deferred = new Deferred();
        $this->loop = $loop;
        $initialSync = [
            'moRefs',
            'computeResources',
            'hostSystems',
            'virtualMachines',
            'quickStats',
            'perfCounterInfo',
            'hostNetwork',
            'storagePods',
            'dataStores',
            'vmDatastoreUsage',
            'vmDiskUsage',
            'vmSnapshots',
            'vmHardware',
            'hostHardware',
            'hostSensors',
        ];

        $schedule = [
            [2, ['eventStream']],
            [900, [
                'moRefs',
                'hostSystems',
                'virtualMachines',
                'computeResources',
            ]],
            [1800, [
                'vmSnapshots',
                'vmHardware',
            ]],
            [1800, [
                'hostNetwork',
            ]],
            [7200, [
                'hostHardware',
                'hostSensors',
            ]],
            [300, [
                'vmDatastoreUsage',
                'vmDiskUsage',
            ]],
            [90, [
                'storagePods',
                'quickStats',
            ]],
        ];

        $this->runTasks($initialSync);
        $loop->addTimer(0.1, function () {
            $this->runNextImmediateTask();
        });
        foreach ($schedule as $pair) {
            $loop->addPeriodicTimer($pair[0], $this->callRunTasks($pair[1]));
        }

        return $this->deferred->promise();
    }

    public function runNextImmediateTask()
    {
        $task = array_shift($this->immediateTasks);
        if ($task) {
            $func = $this->availableTasks[$task];
            try {
                gc_collect_cycles();
                gc_disable();
                $this->emit('beginTask', [$this->taskNames[$task]]);
                $func();
                $this->emit('endTask', [$this->taskNames[$task]]);
                gc_collect_cycles();
                gc_enable();
                $this->loop->addTimer(0.1, function () {
                    $this->runNextImmediateTask();
                });
            } catch (Exception $e) {
                $this->logger->error("Task $task failed: " . $e->getMessage());
                if ($this->showTrace) {
                    $this->logger->error($e->getTraceAsString());
                }
                if ($e instanceof ZfDbException) {
                    $this->emit('dbError', [$e]);
                    return;
                }
                $this->loop->addTimer(0.5, function () {
                    $this->deferred->reject();
                });
                $this->loop->addTimer(5, function () {
                    $this->runNextImmediateTask();
                });
            }
        } else {
            $this->loop->addTimer(1, function () {
                $this->runNextImmediateTask();
            });
        }
    }

    /**
     * @param bool $show
     * @return $this
     */
    public function showTrace($show = true)
    {
        $this->showTrace = (bool) $show;

        return $this;
    }

    protected function callRunTasks($tasks)
    {
        return function () use ($tasks) {
            $this->runTasks($tasks);
        };
    }

    protected function runTasks($tasks)
    {
        foreach ($tasks as $task) {
            $this->immediateTasks[$task] = $task;
        }
    }

    /**
     * @return EventManager
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Zend_Db_Select_Exception
     */
    protected function eventManager()
    {
        if ($this->eventManager === null) {
            $this->eventManager = $this->vCenter->getApi($this->logger)->eventManager()
                ->persistFor($this->vCenter);
        }

        return $this->eventManager;
    }

    /**
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Zend_Db_Exception
     * @throws \Zend_Db_Select_Exception
     */
    public function streamEvents()
    {
        $cnt = $this->eventManager()->streamToDb();
        if ($cnt < 1000) {
            if ($cnt > 0) {
                $this->logger->debug(sprintf('Got %d events', $cnt));
            }
        } else {
            $this->logger->debug(sprintf('Got %d event(s), there might be more', $cnt));
        }
    }
}
