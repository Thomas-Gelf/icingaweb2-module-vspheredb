<?php

namespace Icinga\Module\Vspheredb\DbObject;

use DateTime;
use Icinga\Module\Vspheredb\MappedClass\ClusterDasFdmHostState;

class HostSystem extends BaseDbObject
{
    use CustomValueSupport;

    protected $keyName = 'uuid';

    protected $table = 'host_system';

    protected $defaultProperties = [
        'uuid'                    => null,
        'vcenter_uuid'            => null,
        'host_name'               => null,
        'product_api_version'     => null,
        'product_full_name'       => null,
        'bios_version'            => null,
        'bios_release_date'       => null,
        'sysinfo_vendor'          => null,
        'sysinfo_model'           => null,
        'sysinfo_uuid'            => null,
        'service_tag'             => null,
        'hardware_cpu_model'      => null,
        'hardware_cpu_mhz'        => null,
        'hardware_cpu_packages'   => null,
        'hardware_cpu_cores'      => null,
        'hardware_cpu_threads'    => null,
        'hardware_memory_size_mb' => null,
        'hardware_num_hba'        => null,
        'hardware_num_nic'        => null,
        'runtime_power_state'     => null,
        'das_host_state'          => null,
        'custom_values'           => null,
    ];

    protected $propertyMap = [
        // config.fileSystemVolume.mountInfo
        'name'                              => 'host_name',
        'summary.config.product.apiVersion' => 'product_api_version',
        'summary.config.product.fullName'   => 'product_full_name',
        'hardware.biosInfo.biosVersion'     => 'bios_version',
        'hardware.cpuInfo.numCpuPackages'   => 'hardware_cpu_packages',
        'hardware.cpuInfo.numCpuCores'      => 'hardware_cpu_cores',
        'hardware.cpuInfo.numCpuThreads'    => 'hardware_cpu_threads',
        'hardware.systemInfo.model'         => 'sysinfo_model',
        'hardware.systemInfo.uuid'          => 'sysinfo_uuid',
        'hardware.systemInfo.vendor'        => 'sysinfo_vendor',
        'runtime.powerState'                => 'runtime_power_state',
        // TODO: Introduce HostRuntimeInfo?
        // https://<vcenter>/mob/?moid=ha%2dhost&doPath=runtime
        // runtime.inMaintenanceMode   boolean  false
        // runtime.inQuarantineMode    boolean  Unset
        'runtime.dasHostState'              => 'dasHostState',
        'summary.customValue'               => 'customValues',
        'summary.hardware.cpuMhz'           => 'hardware_cpu_mhz',
        'summary.hardware.cpuModel'         => 'hardware_cpu_model',
        'summary.hardware.numHBAs'          => 'hardware_num_hba',
        'summary.hardware.numNics'          => 'hardware_num_nic',
    ];

    protected $quickStats;

    public function quickStats()
    {
        if ($this->quickStats === null) {
            $this->quickStats = HostQuickStats::load($this->get('uuid'), $this->connection);
        }

        return $this->quickStats;
    }

    protected static function getDefaultPropertySet()
    {
        return array_merge(
            parent::getDefaultPropertySet(),
            [
                'summary.hardware.memorySize',
                'hardware.biosInfo.releaseDate',
                'summary.hardware.otherIdentifyingInfo',
            ]
        );
    }

    public function countVms()
    {
        $db = $this->getDb();
        return $db->fetchOne(
            $db->select()
                ->from('virtual_machine', 'COUNT(*)')
                ->where('runtime_host_uuid = ?', $this->get('uuid'))
        );
    }

    public function setMapped($properties, VCenter $vCenter)
    {
        $otherInfo = $properties->{'summary.hardware.otherIdentifyingInfo'};
        if (property_exists($otherInfo, 'HostSystemIdentificationInfo')) {
            $this->setOtherIdentifyingInfo(
                $otherInfo->HostSystemIdentificationInfo
            );
        }
        if (property_exists($properties, 'hardware.biosInfo.releaseDate')) {
            $this->set(
                'bios_release_date',
                $this->formatBiosReleaseDate($properties->{'hardware.biosInfo.releaseDate'})
            );
        }
        $this->set(
            'hardware_memory_size_mb',
            floor($properties->{'summary.hardware.memorySize'} / (1024 * 1024))
        );

        return parent::setMapped($properties, $vCenter);
    }

    protected function setOtherIdentifyingInfo($infos)
    {
        foreach ($infos as $info) {
            if ($info->identifierType->key === 'ServiceTag') {
                $this->set(
                    'service_tag',
                    $info->identifierValue
                );
            }
        }
    }

    protected function setDasHostState(ClusterDasFdmHostState $state = null)
    {
        if ($state === null) {
            $this->set('das_host_state', null);
        } else {
            $this->set('das_host_state', $state->state);
        }
    }

    protected function formatBiosReleaseDate($date)
    {
        return (new DateTime($date))->format('Y-m-d H:i:s');
    }
}
