<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

class VmNetwork extends PerformanceSet
{
    protected $counters = [
        'bytesRx', // rate / average / kiloBytesPerSecond
        'bytesTx',
        'packetsRx',
        'packetsTx',
        'broadcastRx',
        'broadcastTx',
        'multicastRx',
        'multicastTx',
        'droppedRx',
        'droppedTx',
    ];

    protected $countersGroup = 'net';

    protected $objectType = 'VirtualMachine';

    public function getMeasurementName()
    {
        return 'VirtualNetworkAdapter';
    }

    public function prepareInstancesQuery()
    {
        return $this
            ->prepareBaseQuery()
            ->columns([
                'o.moref',
                'hardware_key' => "GROUP_CONCAT(vna.hardware_key SEPARATOR ',')",
            ])
            ->group('vm.uuid')
            ->order('vm.runtime_host_uuid')
            ->order('vna.hardware_key');
    }

    public function fetchObjectTags()
    {
        $result = [];
        $query = $this->prepareBaseQuery()->columns([
            'vm_moref'               => 'o.moref',
            'vm_name'                => 'o.object_name',
            'vm_guest_host_name'     => "COALESCE(vm.guest_host_name, '(null)')",
            'interface_hardware_key' => 'vna.hardware_key',
            // 'parent_name'     => 'po.object_name',
            'interface_label'        => 'vh.label',
            // 'portgroup_name'  => 'pgo.object_name',
        ])
        // ->join(['po' => 'object'], 'po.uuid = o.parent_uuid', [])
        ->join(
            ['vh' => 'vm_hardware'],
            'vh.vm_uuid = vna.vm_uuid AND vh.hardware_key = vna.hardware_key',
            []
        );
        foreach ($this->getDb()->fetchAll($query) as $row) {
            $result[$row->vm_moref . '/' . $row->interface_hardware_key] = (array) $row;
        }

        return $result;
    }

    protected function prepareBaseQuery()
    {
        return $this->getDb()->select()->from(['o' => 'object'], [])
            ->join(['vm' => 'virtual_machine'], 'o.uuid = vm.uuid', [])
            ->join(['vna' => 'vm_network_adapter'], 'vna.vm_uuid = vm.uuid', [])
            ->where('o.vcenter_uuid = ?', $this->vCenter->getUuid());
    }
}
