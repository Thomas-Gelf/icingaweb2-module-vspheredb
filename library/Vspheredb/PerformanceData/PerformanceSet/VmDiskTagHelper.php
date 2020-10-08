<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

use Icinga\Module\Vspheredb\DbObject\VCenter;
use Zend_Db_Adapter_Pdo_Abstract as DbAdapter;

class VmDiskTagHelper
{
    /** @var DbAdapter */
    protected $db;

    /** @var VCenter */
    protected $vCenter;

    public function __construct(VCenter $vCenter)
    {
        $this->vCenter = $vCenter;
        $this->db = $vCenter->getDb();
    }

    protected function prepareVmDisksQuery()
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

    public function fetchVmTags()
    {
        $result = [];
        $query = $this->prepareVmDisksQuery()->columns([
            'vm_uuid'             => 'LOWER(HEX(o.uuid))',
            'vm_name'             => 'o.object_name',
            'vm_guest_host_name'  => 'vm.guest_host_name',
            'vm_moref'            => 'o.moref',
            'disk_hardware_key'   => "(CASE WHEN vmhw.label LIKE 'IDE %' THEN 'ide' ELSE 'scsi' END"
            . " || vmhc.bus_number || ':' || vmhw.unit_number)",
            'disk_hardware_label' => 'vmhw.label',
        ]);

        foreach ($this->db->fetchAll($query) as $row) {
            $result[$row->vm_moref . '/' . $row->disk_hardware_key] = (array) $row;
        }

        return $result;
    }
}
