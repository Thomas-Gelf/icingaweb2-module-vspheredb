<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Exception;
use Evenement\EventEmitterTrait;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\PerfManager;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;

class PerfDataRunner
{
    use EventEmitterTrait;
    use LoggerAwareTrait;

    /** @var VCenter */
    protected $vCenter;

    /** @var PerfManager */
    protected $perfManager;

    /** @var LoopInterface */
    protected $loop;

    protected $availableTasks = [];

    protected $immediateTasks = [];

    /** @var Deferred */
    protected $deferred;

    protected $taskNames = [
        'test' => 'Just playing around',
    ];

    /**
     * SyncRunner constructor.
     * @param VCenter $vCenter
     */
    public function __construct(VCenter $vCenter, LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->vCenter = $vCenter;
        $this->availableTasks = [
            'test' => function () {
                $api = $this->vCenter->getApi($this->logger);
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
            'test',
        ];

        $schedule = [
            [2, ['test']],
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
            } catch (Exception $e) {
                $this->logger->error("Task $task failed: " . $e->getMessage());
                $this->loop->addTimer(0.5, function () {
                    $this->deferred->reject();
                });
                $this->loop->addTimer(5, function () {
                    $this->runNextImmediateTask();
                });
            }
            $this->loop->addTimer(0.1, function () {
                $this->runNextImmediateTask();
            });
        } else {
            $this->loop->addTimer(1, function () {
                $this->runNextImmediateTask();
            });
        }
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
     * @return PerfManager
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function perfManager()
    {
        if ($this->perfManager === null) {
            $this->perfManager = $this->vCenter->getApi($this->logger)->perfManager()
                ->persistFor($this->vCenter);
        }

        return $this->perfManager;
    }

    /**
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Zend_Db_Exception
     * @throws \Zend_Db_Select_Exception
     */
    public function streamPerfData()
    {
        $cnt = $this->perfManager()->streamToDb();
        if ($cnt < 1000) {
            if ($cnt > 0) {
                $this->logger->debug(sprintf('Got %d events', $cnt));
            }
        } else {
            $this->logger->debug(sprintf('Got %d event(s), there might be more', $cnt));
        }
    }
}
