<?php

namespace Icinga\Module\Vspheredb;

use Icinga\Exception\AuthenticationException;
use Icinga\Module\Vspheredb\MappedClass\PerfEntityMetricCSV;
use Icinga\Module\Vspheredb\MappedClass\PerfMetricId;
use Icinga\Module\Vspheredb\MappedClass\PerformanceManager;
use Icinga\Module\Vspheredb\MappedClass\PerfQuerySpec;
use Icinga\Module\Vspheredb\PropertySet\PropertySet;
use Icinga\Module\Vspheredb\SelectSet\SelectSet;
use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

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

    public function collectPerfdata($specSet)
    {
        $specSet = [
            '_this'   => $this->obj,
            'specSet' => $specSet
        ];

        // TODO: test whether this works
        return $this->api->soapCall('RetrieveProperties', $specSet);

        // return $this->makeNiceResult(
        //     $this->api->soapCall('RetrieveProperties', $specSet)
        // );
    }

    /**
     * @param PropertySet $propSet
     * @param SelectSet $selectSet
     * @return mixed
     * @throws \Icinga\Exception\AuthenticationException
     */
    public function collectObjectPerfdata(PropertySet $propSet, SelectSet $selectSet)
    {
        $result = $this->collectPerfdata(
            $this->api->makePropertyFilterSpec($propSet, $selectSet)
        );

        return $result;
    }

    /**
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

        // returnval => {
        //   entity => { _ => host-326963, type => HostSystem }
        //   currentSupported => 1
        //   summarySupported => 1
        //   refreshRate => 20
        // }

        return $this->api->soapCall('QueryPerfProviderSummary', $specSet);
    }

    // $api->queryAvailablePerfMetric('datacenter-21', 'Datacenter', 20));
    // $api->queryAvailablePerfMetric('host-326963', 'HostSystem', 20) );
    // $api->queryAvailablePerfMetric('vm-844012', 'VirtualMachine', 20));
    public function queryAvailablePerfMetric($name, $type, $intervalId)
    {
        $specSet = [
            '_this'  => $this->obj,
            'entity' => $this->makeEntity($name, $type),
            'interval' => $intervalId // intervalId?? beginTime, endTime
        ];

        return $this->api->soapCall('QueryAvailablePerfMetric', $specSet);
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
            throw new \RuntimeException(sprintf(
                'Exactly one PerformanceManager object expected, got %d',
                count($result->objects)
            ));
        }

        $object = $result->objects[0];

        if ($object->hasMissingProperties()) {
            if ($object->reportsNotAuthenticated()) {
                throw new AuthenticationException('Not authenticated');
            } else {
                // TODO: no permission, throw error message!
                throw new \RuntimeException('Got no result');
            }
        } else {
            return $object->toNewObject();
        }
    }

    protected function makeDateTime($timestamp)
    {
        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
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
            $this->logger->error('no returnval');
            return [];
        }
    }

    public function makeEntity($name, $type)
    {
        return ['_' => $name, 'type' => $type];
    }

    // Might be obsolete, if not please unify with the one in the PropertyCollector
    protected function makeNiceResult($result)
    {
        if (! property_exists($result, 'returnval')) {
            return [];
        }

        $knownRefs = [
            'parent',
            'runtime.host',
        ];

        $nice = [];
        foreach ($result->returnval as $row) {
            $data = [
                'id'   => $row->obj->_,
                'type' => $row->obj->type
            ];
            foreach ($row->propSet as $prop) {
                $val = $prop->val;
                if (in_array($prop->name, $knownRefs)) {
                    // [parent] => stdClass Object (
                    //    [_] => group-v123456
                    //    [type] => Folder, HostSystem etc
                    // )
                    $data[$prop->name] = $val->_;
                } else {
                    if (is_object($val) && property_exists($val, 'ManagedObjectReference')) {
                        $val = $this->flattenReference($val->ManagedObjectReference);
                    }
                    $data[$prop->name] = $val;
                }
            }
            $nice[$row->obj->_] = (object) $data;
        }

        return $nice;
    }

    protected function flattenReference($ref)
    {
        $res = [];
        foreach ($ref as $r) {
            $res[] = $r->_;
        }

        return $res;
    }
}
