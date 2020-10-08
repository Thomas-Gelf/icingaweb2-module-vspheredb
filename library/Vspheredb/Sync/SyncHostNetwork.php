<?php

namespace Icinga\Module\Vspheredb\Sync;

use Icinga\Module\Vspheredb\DbObject\HostPhysicalNic;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\DbObject\HostVirtualNic;
use Icinga\Module\Vspheredb\MappedClass\HostNetworkInfo;
use Icinga\Module\Vspheredb\PropertySet\PropertySet;

class SyncHostNetwork
{
    use SyncHelper;

    public function run()
    {
        $hostNetProperty = 'config.network';
        $vCenter = $this->vCenter;
        $result = $vCenter->getApi($this->logger)->propertyCollector()->collectObjectProperties(
            new PropertySet('HostSystem', [$hostNetProperty]),
            HostSystem::getSelectSet()
        );

        $this->logger->debug(sprintf(
            'Got %d HostSystems with config.network',
            \count($result)
        ));

        $connection = $vCenter->getConnection();
        /*
        $hardware = VmHardware::loadAllForVCenter($vCenter);
        Logger::debug(
            'Got %d vm_hardware objects from DB',
            count($hardware)
        );
        $disks = VmDisk::loadAllForVCenter($vCenter);
        Logger::debug(
            'Got %d vm_disk objects from DB',
            count($disks)
        );
        */
        $pNics = HostPhysicalNic::loadAllForVCenter($vCenter);
        $this->logger->debug(sprintf(
            'Got %d host_physical_nic objects from DB',
            count($pNics)
        ));
        $vNics = HostVirtualNic::loadAllForVCenter($vCenter);
        $this->logger->debug(sprintf(
            'Got %d host_virtual_nic objects from DB',
            count($vNics)
        ));

        $seen = [];
        $vSeen = [];

        foreach ($result as $host) {
            $uuid = $vCenter->makeBinaryGlobalUuid($host->id);
            if (! isset($host->{$hostNetProperty})) {
                continue;
            }
            /** @var HostNetworkInfo $networkInfo */
            $networkInfo = $host->{$hostNetProperty};
            foreach ($networkInfo->pnic as $vNic) {
                $key = $vNic->key;
                $idx = "$uuid$key";
                $seen[$idx] = $idx;
                if (! array_key_exists($idx, $pNics)) {
                    $pNics[$idx] = HostPhysicalNic::create([
                        'host_uuid' => $uuid,
                        'nic_key'   => $key
                    ], $connection);
                }
                $pNics[$idx]->setMapped($vNic, $vCenter);
            }
            foreach ($networkInfo->vnic as $vNic) {
                $key = $vNic->key;
                $idx = "$uuid$key";
                $vSeen[$idx] = $idx;
                if (! array_key_exists($idx, $vNics)) {
                    $vNics[$idx] = HostVirtualNic::create([
                        'host_uuid' => $uuid,
                        'nic_key'   => $key
                    ], $connection);
                }
                $vNics[$idx]->setMapped($vNic, $vCenter);
            }
        }

        $this->storeObjects($vCenter->getDb(), $pNics, $seen);
        $this->storeObjects($vCenter->getDb(), $vNics, $vSeen);
    }
}
