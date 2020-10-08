<?php

namespace Icinga\Module\Vspheredb\Sync;

use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use Icinga\Module\Vspheredb\DbObject\VmDiskUsage;
use Icinga\Module\Vspheredb\PropertySet\PropertySet;

class SyncVmDiskUsage
{
    use SyncHelper;

    public function run()
    {
        $vCenter = $this->vCenter;
        $vCenterUuid = $vCenter->getUuid();
        $result = $vCenter->getApi($this->logger)->propertyCollector()->collectObjectProperties(
            new PropertySet('VirtualMachine', ['guest.disk']),
            VirtualMachine::getSelectSet()
        );
        $this->logger->debug(sprintf(
            'Got %d VirtualMachines with guest.disk',
            count($result)
        ));

        $connection = $vCenter->getConnection();
        $usage = VmDiskUsage::loadAllForVCenter($vCenter);
        $this->logger->debug(sprintf(
            'Got %d vm_disk_usage objects from DB',
            count($usage)
        ));

        $seen = [];
        foreach ($result as $vm) {
            $uuid = $vCenter->makeBinaryGlobalUuid($vm->id);
            if (! property_exists($vm->{'guest.disk'}, 'GuestDiskInfo')) {
                // Should we preserve them? Flag outdated?
                continue;
            }
            $root = null;
            $var = null;
            // $lastPath = '';
            // $lastCapacity = null;
            // $lastFree = null;
            foreach ($vm->{'guest.disk'}->GuestDiskInfo as $info) {
                $path = $info->diskPath;

                // Workaround for phantom partitions seen by open-vm-tools
                // run by systemd with PrivateTmp=true
                if ($path === '/') {
                    $root = $info;
                } elseif ($path === '/var') {
                    $var = $info;
                } elseif (is_object($root) && in_array($path, ['/tmp', '/var/tmp'])) {
                    if ($path === '/var/tmp' && is_object($var)) {
                        $base = $var;
                    } else {
                        $base = $root;
                    }

                    /** @var \stdClass $base */
                    if ($info->capacity === $base->capacity
                        && $info->freeSpace === $base->freeSpace
                    ) {
                        continue;
                    }
                }
                // End of workaround

                // Skip nested partitions with same capacity and free space
                // Just an idea, not sure about this. e.g. /vsnap/vpoolX has many
                // subpartitions like /vsnap/vpoolX/fs000, not all the same capacity,
                // different free space
                /*
                if (\substr($path, 0, \strlen($lastPath)) === $lastPath
                    && $lastFree === $info->freeSpace
                    && $lastCapacity === $info->capacity
                ) {
                    continue;
                }
                $lastFree = $info->freeSpace;
                $lastCapacity = $info->capacity;
                $lastPath = $path;
                */

                $idx = "$uuid$path";
                $seen[$idx] = $idx;
                if (array_key_exists($idx, $usage)) {
                    $usage[$idx]->set('capacity', $info->capacity);
                    $usage[$idx]->set('free_space', $info->freeSpace);
                } else {
                    $usage[$idx] = VmDiskUsage::create([
                        'vm_uuid'      => $uuid,
                        'vcenter_uuid' => $vCenterUuid,
                        'disk_path'    => $path,
                        'capacity'     => $info->capacity,
                        'free_space'   => $info->freeSpace,
                    ], $connection);
                }
            }
        }

        // $this->logger->debug('Ready to prepare for InfluxDB');
        // $writer = new InfluxDbWriter($this->vCenter);
        // $writer->sendVmDiskUsage($usage);
        $this->storeObjects($vCenter->getDb(), $usage, $seen);
    }
}
