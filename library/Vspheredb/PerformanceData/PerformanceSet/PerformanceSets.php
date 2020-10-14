<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

use Icinga\Module\Vspheredb\DbObject\VCenter;
use function mt;

class PerformanceSets
{
    public static function enumAvailableSets()
    {
        return [
            VmDisks::class   => mt('vspheredb', 'Virtual Disks (I/O, Seeks, Latency)'),
            VmNetwork::class => mt('vspheredb', 'Virtual Network Adapters (Octets, Packets)'),
            HostNetwork::class => mt('vspheredb', 'Host Physical Network Adapters (Octets, Packets)'),
        ];
    }

    public static function listAvailableSets()
    {
        return [
            VmDisks::class,
            VmNetwork::class,
            HostNetwork::class,
        ];
    }

    public static function createInstanceByMeasurementName($name, VCenter $vCenter)
    {
        foreach (static::createInstancesForVCenter($vCenter) as $instance) {
            if ($instance->getMeasurementName() === $name) {
                return $instance;
            }
        }

        throw new \InvalidArgumentException("There is no such PerformanceSet: $name");
    }

    /**
     * @param VCenter $vCenter
     * @return PerformanceSet[]
     */
    public static function createInstancesForVCenter(VCenter $vCenter)
    {
        $sets = [];
        foreach (static::listAvailableSets() as $class) {
            $set = new $class($vCenter);
            assert($set instanceof PerformanceSet);
            $sets[] = $set;
        }

        return $sets;
    }
}
