<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

// https://www.vmware.com/support/developer/converter-sdk/conv61_apireference/virtual_disk_counters.html
class VmDisks extends PerformanceSet
{
    protected $counters = [
        // 'busResets', -> not per instance
        // 'commandsAborted',  -> not per instance
        'numberReadAveraged', // rate, average
        'numberWriteAveraged',
        'readLatencyUS', // rate, latest, microseconds
        'writeLatencyUS',
        'totalReadLatency', // absolute, average, milliseconds
        'totalWriteLatency',

        // small is better, less than 64 LBNs are skipped in order to access the requested data
        'smallSeeks',  // absolute, latest
        'mediumSeeks', // Number of seeks during the interval that were between 64 and 8192 LBNs apart
        'largeSeeks',  // Number of seeks during the interval that were greater than 8192 LBNs apart

        'read', // rate, kiloBytesPerSecond, average
        'write',

        // Average number of outstanding read (write) requests:
        'readOIO', // absolute, latest
        'writeOIO',
        // commands, usage
    ];

    // 'maxTotalLatency', // > 20ms BAD -> ist nicht hier

    protected $countersGroup = 'virtualDisk';

    protected $objectType = 'VirtualMachine';

    public function getMeasurementName()
    {
        return 'VirtualDisk';
    }

    public function prepareInstancesQuery()
    {
        return $this
            ->prepareBaseQuery()
            ->columns([
                'o.moref',
                "GROUP_CONCAT(CASE WHEN vmhw.label LIKE 'IDE %' THEN 'ide' ELSE 'scsi' END"
                . " || vmhc.bus_number || ':' || vmhw.unit_number SEPARATOR ',')",
            ])
            ->group('vm.uuid');
    }

    protected function prepareBaseQuery()
    {
        return $this->db->select()->from(['o' => 'object'], [])
            ->join(['vm' => 'virtual_machine'], 'o.uuid = vm.uuid', [])
            ->join(['vmd' => 'vm_disk'], 'vm.uuid = vmd.vm_uuid', [])
            ->join(
                ['vmhw' => 'vm_hardware'],
                'vmd.vm_uuid = vmhw.vm_uuid AND vmd.hardware_key = vmhw.hardware_key',
                []
            )
            ->join(
                ['vmhc' => 'vm_hardware'],
                'vmhw.vm_uuid = vmhc.vm_uuid AND vmhw.controller_key = vmhc.hardware_key',
                []
            )
            ->where('o.vcenter_uuid = ?', $this->vCenter->getUuid())
            ->where("vmhc.label LIKE 'SCSI controller %' OR vmhc.label LIKE 'IDE %'")
            ->order('vm.runtime_host_uuid')
            ->order('vmd.hardware_key');
    }

    public function fetchObjectTags()
    {
        return (new VmDiskTagHelper($this->vCenter))->fetchVmTags();
    }
}
