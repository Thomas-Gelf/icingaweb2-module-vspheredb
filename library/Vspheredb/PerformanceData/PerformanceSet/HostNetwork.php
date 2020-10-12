<?php

namespace Icinga\Module\Vspheredb\PerformanceData\PerformanceSet;

class HostNetwork extends PerformanceSet
{
    protected $counters = [
        // averaged alternative: received, transmitted, usage
        'bytesRx',
        'bytesTx',
        'packetsRx',
        'packetsTx',
        'broadcastRx',
        'broadcastTx',
        'multicastRx',
        'multicastTx',
        'droppedRx',
        'droppedTx',
        'errorsRx',
        'errorsTx',
    ];
    // net.usage

    protected $countersGroup = 'net';

    protected $objectType = 'HostSystem';

    public function getMeasurementName()
    {
        return 'HostNetworkAdapter';
    }

    /*
        // Host and VM:
        cpu.usage
        cpu.ready > 20% is bad
        cpu.swapwait

        memory -> swapin, swapout
        memory -> active, usage, consumed,
        instance = '*' -> all instances, instance = '' -> aggregated
    */
    public function prepareInstancesQuery()
    {
        return $this
            ->prepareBaseQuery()
            ->columns([
                'o.moref',
                'device' => "GROUP_CONCAT(hpn.device SEPARATOR ',')",
            ])
            ->group('hs.uuid')
            ->order('hs.uuid')
            ->order('hpn.device');
    }

    public function fetchObjectTags()
    {
        $result = [];
        $query = $this->prepareBaseQuery()->columns([
            'host_moref'   => 'o.moref',
            'host_name'    => 'o.object_name',
            'pnic_key'     => 'hpn.nic_key',
            'device_label' => 'hpn.device',
        ]);
        foreach ($this->getDb()->fetchAll($query) as $row) {
            $result[$row->host_moref . '/' . $row->device_label] = (array) $row;
        }

        return $result;
    }

    protected function prepareBaseQuery()
    {
        return $this->getDb()->select()->from(['o' => 'object'], [])
            ->join(['hs' => 'host_system'], 'o.uuid = hs.uuid', [])
            ->join(['hpn' => 'host_physical_nic'], 'hpn.host_uuid = hs.uuid', [])
            ->where('o.vcenter_uuid = ?', $this->vCenter->getUuid());
    }
}
