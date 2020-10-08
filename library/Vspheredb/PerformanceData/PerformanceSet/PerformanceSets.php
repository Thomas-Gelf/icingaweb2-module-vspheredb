<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

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
}
