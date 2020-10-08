<?php

namespace Icinga\Module\Vspheredb;

use Exception;
use Icinga\Exception\AuthenticationException;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\MappedClass\BaseMigrationEvent;
use Icinga\Module\Vspheredb\MappedClass\KnownEvent;
use Icinga\Module\Vspheredb\MappedClass\SessionEvent;
use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Zend_Db_Select as ZfSelect;

class EventManager
{
    use LoggerAwareTrait;

    /** @var Api */
    protected $api;

    /** @var ManagedObjectReference */
    protected $obj;

    /** @var ManagedObjectReference */
    protected $collector;

    protected $lastEventTimestamp;

    /** @var int */
    protected $lastEventKey;

    /** @var VCenter */
    protected $vCenter;

    /**
     * EventManager constructor.
     * @param Api $api
     */
    public function __construct(Api $api, LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->api = $api;
        $this->obj = $api->getServiceInstance()->eventManager;
    }

    /**
     * @param VCenter $vCenter
     * @return $this
     * @throws \Zend_Db_Select_Exception
     */
    public function persistFor(VCenter $vCenter)
    {
        // TODO: move persistence elsewhere
        $this->vCenter = $vCenter;
        $this->lastEventKey = $this->getLastEventKey();
        $this->lastEventTimestamp = $this->getLastEventTimeStamp();

        return $this;
    }

    /**
     * @return ManagedObjectReference
     * @throws AuthenticationException
     * @throws \SoapFault
     */
    private function createEventCollector()
    {
        $result = $this->api->soapCall('CreateCollectorForEvents', [
            '_this' => $this->obj
        ] + $this->createSpecSet());
        if (property_exists($result, 'returnval')) {
            // This moref looks like this:
            // returnval => {
            //     _ => "session[52dd54f1-28a1-4b84-6bd4-fc45fd9f3b78]52fc6d14-1c07-ffcd-107c-7132b2d263b0",
            //     type => "EventHistoryCollector"

            return $result->returnval;
        } else {
            throw new AuthenticationException('Unable to create event collector, please check session');
        }
    }

    /**
     * @throws AuthenticationException
     * @throws \SoapFault
     */
    public function rewindCollector()
    {
        $this->call('RewindCollector');
    }

    /**
     * @return BaseMigrationEvent[]
     * @throws AuthenticationException
     * @throws \SoapFault
     */
    public function collectFromCollector()
    {
        $result = $this->call('ReadNextEvents', [
            'maxCount' => 1000,
        ]);

        if (property_exists($result, 'returnval')) {
            return $result->returnval;
        } else {
            return [];
        }
    }

    /**
     * @return int
     * @throws AuthenticationException
     * @throws Exception
     */
    public function streamToDb()
    {
        $events = $this->collectFromCollector();
        if (empty($events)) {
            return 0;
        }

        $db = $this->vCenter->getDb();
        $db->beginTransaction();
        $skipped = 0;
        try {
            foreach ($events as $key => $event) {
                // printf("%s <= %s\n", $event->key, $this->lastEventKey);
                if (! method_exists($event, 'getTimestampMs')) {
                    throw new RuntimeException('This is not a know event: ' . var_export($event, 1));
                }
                if ($this->lastEventKey
                    && $event->getTimestampMs() <= $this->lastEventTimestamp
                    && $event->key <= $this->lastEventKey
                ) {
                    $this->logger->debug(sprintf(
                        '%s <= %s & %s <= %s skipped',
                        $event->getTimestampMs(),
                        $this->lastEventTimestamp,
                        $event->key,
                        $this->lastEventKey
                    ));
                    $skipped++;
                    continue;
                }

                if ($event instanceof SessionEvent) {
                    // not yet
                } elseif ($event instanceof KnownEvent) {
                    $event->store($db, $this->vCenter);
                }/* else {
                    $dom = simplexml_load_string($vCenter->getApi()->curl()->getLastResponse());
                    $dom->formatOutput = true;
                    echo $dom->saveXML();

                    print_r($event);
                    exit;
                }*/
            }
            $db->commit();
        } catch (Exception $error) {
            try {
                $db->rollBack();
            } catch (Exception $e) {
                // There is nothing we can do.
            }

            throw $error;
        }

        if ($skipped > 0) {
            $this->logger->debug(sprintf('Fetched %d events to skip', $skipped));
        }

        return count($events);
    }

    /**
     * @return int
     * @throws \Zend_Db_Select_Exception
     */
    protected function getLastEventTimeStamp()
    {
        $db = $this->vCenter->getDb();
        $uuid = $this->vCenter->getUuid();

        $union = $db->select()->union([
            'vmeh' => $db->select()->from(
                'vm_event_history',
                ['ts_event_ms' => 'MAX(ts_event_ms)']
            )->where('vcenter_uuid = ?', $uuid),
            'ah' => $db->select()->from(
                'alarm_history',
                ['ts_event_ms' => 'MAX(ts_event_ms)']
            )->where('vcenter_uuid = ?', $uuid),
        ], ZfSelect::SQL_UNION_ALL);

        return (int) $db->fetchOne(
            $db->select()->from(['u' => $union], 'MAX(ts_event_ms)')
        );
    }

    /**
     * @return int
     * @throws \Zend_Db_Select_Exception
     */
    protected function getLastEventKey()
    {
        $db = $this->vCenter->getDb();
        $uuid = $this->vCenter->getUuid();

        $union = $db->select()->union([
            'vmeh' => $db->select()->from(
                'vm_event_history',
                ['event_key' => 'MAX(event_key)']
            )->where('vcenter_uuid = ?', $uuid),
            'ah' => $db->select()->from(
                'alarm_history',
                ['event_key' => 'MAX(event_key)']
            )->where('vcenter_uuid = ?', $uuid),
        ], ZfSelect::SQL_UNION_ALL);

        return (int) $db->fetchOne(
            $db->select()->from(['u' => $union], 'MAX(event_key)')
        );
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws AuthenticationException
     * @throws \SoapFault
     */
    protected function call($method, $params = [])
    {
        try {
            return $this->api->soapCall($method, [
                    '_this'   => $this->collector(),
                ] + $params);
        } catch (\SoapFault $e) {
            $this->checkForFailedEventCollector($e);
            throw $e;
        }
    }

    /**
     * In case we failed with ManagedObjectNotFound, forget about failing EventCollector
     *
     * TODO: We might eventually check that the error really refers our collector object.
     *
     * @param \SoapFault $fault
     */
    protected function checkForFailedEventCollector(\SoapFault $fault)
    {
        if (isset($fault->detail) && current($fault->detail)->enc_stype === 'ManagedObjectNotFound') {
            $this->collector = null;
            $this->logger->warning('Dropping formerly known EventCollector: ' . $fault->getMessage());
        }
    }

    protected function getEventTypes()
    {
        return [
            'AlarmAcknowledgedEvent',
            'AlarmClearedEvent',
            'AlarmCreatedEvent',
            'AlarmReconfiguredEvent',
            'AlarmRemovedEvent',
            'AlarmStatusChangedEvent',

            'VmBeingMigratedEvent',
            'VmBeingHotMigratedEvent',
            'VmEmigratingEvent',
            'VmFailedMigrateEvent',
            'VmMigratedEvent',
            'DrsVmMigratedEvent',
//
            'VmBeingCreatedEvent',
            'VmCreatedEvent',
            'VmStartingEvent',
            'VmPoweredOnEvent',
            'VmPoweredOffEvent',
            'VmResettingEvent',
            'VmSuspendedEvent',

            'VmStoppingEvent',

            'VmBeingDeployedEvent',
            'VmReconfiguredEvent',

            'VmBeingClonedEvent',
            'VmBeingClonedNoFolderEvent',
            'VmClonedEvent',
            'VmCloneFailedEvent',
        ];
    }

    /**
     * Just for tests, not used at runtime
     *
     * @return BaseMigrationEvent[]
     * @throws AuthenticationException
     */
    public function queryEvents()
    {
        $result = $this->api->soapCall('QueryEvents', $this->createSpecSet());
        if (property_exists($result, 'returnval')) {
            return $result->returnval;
        } else {
            return [];
        }
    }

    /**
     * @return array
     */
    protected function createSpecSet()
    {
        $filters = ['type' => $this->getEventTypes()];

        if ($this->lastEventTimestamp) {
            $filters['time'] = [
                'beginTime' => $this->makeDateTime((int) floor($this->lastEventTimestamp / 1000))
            ];
        }

        return [
            'filter' => $filters,
        ];
    }

    /**
     * UNUSED
     *
     * @param $vmRef
     * @return array
     */
    protected function createSpecSetForVm($vmRef)
    {
        // Cloned -> sourceVm
        return [
            '_this'   => $this->obj,
            'filter' => [
                'entity' => [
                    'entity' => [
                        '_'    => $vmRef,
                        'type' => 'VirtualMachine',
                    ],
                    'recursion' => 'self'
                ],
            ]
        ];
    }

    protected function makeDateTime($timestamp)
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    /**
     * Lazy access to eventCollector
     *
     * @return ManagedObjectReference
     * @throws AuthenticationException
     * @throws \SoapFault
     */
    protected function collector()
    {
        if ($this->collector === null) {
            $this->collector = $this->createEventCollector();
            $this->rewindCollector();
        }

        return $this->collector;
    }
}
