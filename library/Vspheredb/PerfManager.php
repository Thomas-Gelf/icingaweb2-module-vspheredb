<?php

namespace Icinga\Module\Vspheredb;

use Icinga\Exception\AuthenticationException;
use Icinga\Module\Vspheredb\MappedClass\PerfEntityMetricCSV;
use Icinga\Module\Vspheredb\MappedClass\PerformanceManager;
use Icinga\Module\Vspheredb\MappedClass\PerfQuerySpec;
use Icinga\Module\Vspheredb\PropertySet\PropertySet;
use Icinga\Module\Vspheredb\SelectSet\SelectSet;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use RuntimeException;

class PerfManager
{
    use LoggerAwareTrait;

    /** @var Api */
    protected $api;

    protected $obj;

    public function __construct(Api $api, LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->api = $api;
        $this->obj = $api->getServiceInstance()->perfManager;
    }

    /**
     * @param PerfQuerySpec[] $spec
     * @return PerfEntityMetricCSV[]
     * @throws AuthenticationException
     */
    public function queryPerf($spec)
    {
        $api = $this->api;

        $specSet = [
            '_this'     => $api->getServiceInstance()->perfManager,
            'querySpec' => $spec
        ];

        $result = $api->soapCall('QueryPerf', $specSet);
        if (isset($result->returnval)) {
            return $result->returnval;
        } else {
            // TODO: Check whether there is error information in the result
            $this->logger->error('Got no QueryPerf returnval');
            return [];
        }
    }

    /**
     * @return PerformanceManager
     * @throws \Icinga\Exception\AuthenticationException
     */
    public function getPerformanceManager()
    {
        $result = $this->api->propertyCollector()->collectPropertiesEx([
            'propSet' => [
                'type' => 'PerformanceManager',
                'all'  => true
            ],
            'objectSet' => [
                'obj'  => $this->obj,
                'skip' => false
            ]
        ]);

        if (count($result->objects) !== 1) {
            throw new RuntimeException(sprintf(
                'Exactly one PerformanceManager object expected, got %d',
                count($result->objects)
            ));
        }

        $object = $result->objects[0];

        if ($object->hasMissingProperties()) {
            if ($object->reportsNotAuthenticated()) {
                throw new AuthenticationException('Not authenticated');
            } else {
                // TODO: give more detais in the error message
                throw new RuntimeException('Got invalid result, object has missing properties');
            }
        } else {
            return $object->toNewObject();
        }
    }

    /**
     * Currently unused
     *
     * @param PropertySet $propSet
     * @param SelectSet $selectSet
     * @return mixed
     * @throws \Icinga\Exception\AuthenticationException
     */
    public function collectObjectPerfdata(PropertySet $propSet, SelectSet $selectSet)
    {
        $specSet = [
            '_this'   => $this->obj,
            'specSet' => $this->api->makePropertyFilterSpec($propSet, $selectSet)
        ];

        // TODO: test whether this works
        return $this->api->soapCall('RetrieveProperties', $specSet);
    }

    /**
     * Currently unused
     *
     * returnval => {
     *   entity => { _ => host-326963, type => HostSystem }
     *   currentSupported => 1
     *   summarySupported => 1
     *   refreshRate => 20
     * }
     *
     * @param $name
     * @param $type
     * @return mixed
     * @throws \Icinga\Exception\AuthenticationException
     */
    public function queryPerfProviderSummary($name, $type)
    {
        $specSet = [
            '_this'  => $this->obj,
            'entity' => $this->makeEntity($name, $type),
        ];

        return $this->api->soapCall('QueryPerfProviderSummary', $specSet);
    }

    /**
     * Currently unused
     *
     * queryAvailablePerfMetric('datacenter-21', 'Datacenter', 20));
     * queryAvailablePerfMetric('host-326963', 'HostSystem', 20) );
     * queryAvailablePerfMetric('vm-844012', 'VirtualMachine', 20));
     *
     * @param string $name
     * @param string $type
     * @param int $intervalId
     * @return mixed
     * @throws AuthenticationException
     */
    public function queryAvailablePerfMetric($name, $type, $intervalId)
    {
        $specSet = [
            '_this'    => $this->obj,
            'entity'   => $this->makeEntity($name, $type),
            'interval' => $intervalId // intervalId?? beginTime, endTime
        ];

        return $this->api->soapCall('QueryAvailablePerfMetric', $specSet);
    }

    protected function makeEntity($name, $type)
    {
        return ['_' => $name, 'type' => $type];
    }
}
