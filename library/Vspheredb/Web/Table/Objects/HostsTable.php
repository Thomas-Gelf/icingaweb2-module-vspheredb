<?php

namespace Icinga\Module\Vspheredb\Web\Table\Objects;

use gipfl\IcingaWeb2\Link;
use Icinga\Date\DateFormatter;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\Web\Widget\BiosInfo;
use Icinga\Module\Vspheredb\Web\Widget\CpuUsage;
use Icinga\Module\Vspheredb\Web\Widget\MemoryUsage;
use Icinga\Module\Vspheredb\Web\Widget\PowerStateRenderer;
use Icinga\Module\Vspheredb\Web\Widget\ServiceTagRenderer;
use Icinga\Module\Vspheredb\Format;

class HostsTable extends ObjectsTable
{
    protected $baseUrl = 'vspheredb/host';

    protected function initialize()
    {
        $serviceTagRenderer = new ServiceTagRenderer();
        $powerStateRenderer = new PowerStateRenderer();
        $this->addAvailableColumns([
            $this->createOverallStatusColumn(),
            $this->createColumn('runtime_power_state', $this->translate('Power'), 'h.runtime_power_state')
                ->setRenderer($powerStateRenderer),
            $this->createObjectNameColumn(),
            $this->createColumn('sysinfo_vendor', $this->translate('Vendor'), 'h.sysinfo_vendor'),
            $this->createColumn('sysinfo_model', $this->translate('Model'), 'h.sysinfo_model'),
            $this->createColumn('bios_version', $this->translate('BIOS Version'), 'h.bios_version'),
            $this->createColumn('bios_release_date', $this->translate('BIOS Release Date'), 'h.bios_release_date')
                ->setRenderer(function ($row) {
                    return DateFormatter::formatDate(strtotime($row->bios_release_date));
                }),
            $this->createColumn('service_tag', $this->translate('Service Tag'), [
                'service_tag'    => 'h.service_tag',
                'sysinfo_vendor' => 'h.sysinfo_vendor',
            ])->setRenderer($serviceTagRenderer),
            $this->createColumn('product_api_version', $this->translate('API Version'), [
                'product_api_version' => 'h.product_api_version',
            ]),
            $this->createColumn('cpu_usage', $this->translate('CPU Usage'), [
                'cpu_usage' => 'hqs.overall_cpu_usage',
                'cpu_total' => '(hardware_cpu_cores * hardware_cpu_mhz)',
            ])->setRenderer(function ($row) {
                return new CpuUsage($row->cpu_usage, $row->cpu_total);
            })->setSortExpression(
                'hqs.overall_cpu_usage / (h.hardware_cpu_cores * h.hardware_cpu_mhz)'
            )->setDefaultSortDirection('DESC'),
            $this->createColumn('memory_usage', $this->translate('Memory Usage'), [
                'hardware_memory_size_mb' => 'h.hardware_memory_size_mb',
                'memory_usage_mb'         => 'hqs.overall_memory_usage_mb',
            ])->setRenderer(function ($row) {
                return new MemoryUsage($row->memory_usage_mb, $row->hardware_memory_size_mb);
            })->setSortExpression(
                '(hqs.overall_memory_usage_mb / h.hardware_memory_size_mb)'
            )->setDefaultSortDirection('DESC'),
            $this->createColumn('hardware_cpu_cores', $this->translate('CPU Cores'), 'h.hardware_cpu_cores')
                ->setDefaultSortDirection('DESC'),
            $this->createColumn('vms_overall_status', $this->translate('VM Status'), [
                'vms_cnt_overall_gray'   => 'vms.vms_cnt_overall_gray',
                'vms_cnt_overall_green'  => 'vms.vms_cnt_overall_green',
                'vms_cnt_overall_yellow' => 'vms.vms_cnt_overall_yellow',
                'vms_cnt_overall_red'    => 'vms.vms_cnt_overall_red',
            ])->setRenderer(function ($row) {
                $result = [];
                foreach (['red', 'yellow', 'gray', 'green'] as $state) {
                    $column = "vms_cnt_overall_$state";
                    if ($row->$column > 0) {
                        $result[] = Link::create(
                            $row->$column,
                            'vspheredb/host/vms',
                            [
                                'uuid'           => bin2hex($row->uuid),
                                'overall_status' => $state
                            ],
                            ['class' => ['state', $state]]
                        );
                    }
                }

                if (empty($result)) {
                    return '-';
                } else {
                    return $result;
                }
            })->setSortExpression([
                'vms.vms_cnt_overall_red',
                'vms.vms_cnt_overall_yellow',
                'vms.vms_cnt_overall_gray',
                'vms.vms_cnt_overall_green',
            ])->setDefaultSortDirection('DESC'),
            $this->createColumn('vms_cnt_cpu', $this->translate('VM CPUs'), 'vms.cnt_cpu')
                ->setDefaultSortDirection('DESC'),
            $this->createColumn(
                'pcpu_vcpu_ration',
                $this->translate('vCPU/pCPU'),
                '(vms.cnt_cpu / h.hardware_cpu_cores)'
            )->setRenderer(function ($row) {
                return sprintf('%.3g:1', $row->pcpu_vcpu_ration);
            })->setDefaultSortDirection('DESC'),
            $this->createColumn('hardware_memory_size_mb', $this->translate('Memory'), 'h.hardware_memory_size_mb')
                ->setRenderer(function ($row) {
                    return Format::mBytes($row->hardware_memory_size_mb);
                })->setDefaultSortDirection('DESC'),
            $this->createColumn('vms_memorymb', $this->translate('VMs Memory'), 'vms.memorymb')
                ->setRenderer(function ($row) {
                    return Format::mBytes($row->vms_memorymb);
                })->setDefaultSortDirection('DESC'),
            $this->createColumn('vms_cnt', $this->translate('VMs'), 'vms.cnt')
                ->setDefaultSortDirection('DESC'),
            $this->createColumn('spectre_meltdown', $this->translate('Spectre / Meltdown'), [
                'sysinfo_vendor'    => 'h.sysinfo_vendor',
                'sysinfo_model'     => 'h.sysinfo_model',
                'bios_version'      => 'h.bios_version',
                'bios_release_date' => 'h.bios_release_date',
            ])->setRenderer(function ($row) {
                $host = HostSystem::create([
                    'sysinfo_vendor'    => $row->sysinfo_vendor,
                    'sysinfo_model'     => $row->sysinfo_model,
                    'bios_version'      => $row->bios_version,
                    'bios_release_date' => $row->bios_release_date,
                ]);

                return new BiosInfo($host);
            }),
            $this->createColumn('uptime', $this->translate('Uptime'), [
                'uptime' => 'hqs.uptime',
            ])->setRenderer(function ($row) {
                if ($row->uptime === null) {
                    return null;
                }

                return DateFormatter::formatDuration($row->uptime);
            }),

        ]);
    }

    public function getDefaultColumnNames()
    {
        return [
            'object_name',
            'cpu_usage',
            'memory_usage',
        ];
    }

    protected function createVmSubQuery()
    {
        return $this->db()->select()->from(
            ['vc' => 'virtual_machine'],
            [
                'cnt'                    => 'COUNT(*)',
                'cnt_cpu'                => 'SUM(vc.hardware_numcpu)',
                'memorymb'               => 'SUM(vc.hardware_memorymb)',
                'runtime_host_uuid'      => 'vc.runtime_host_uuid',
                'vms_cnt_overall_gray'   => "SUM(CASE WHEN vo.overall_status = 'gray' THEN 1 ELSE 0 END)",
                'vms_cnt_overall_green'  => "SUM(CASE WHEN vo.overall_status = 'green' THEN 1 ELSE 0 END)",
                'vms_cnt_overall_yellow' => "SUM(CASE WHEN vo.overall_status = 'yellow' THEN 1 ELSE 0 END)",
                'vms_cnt_overall_red'    => "SUM(CASE WHEN vo.overall_status = 'red' THEN 1 ELSE 0 END)",
            ]
        )->join(
            ['vo' => 'object'],
            'vo.uuid = vc.uuid',
            []
        )->group('vc.runtime_host_uuid');
    }

    public function prepareQuery()
    {
        $columns = $this->getRequiredDbColumns();
        $wantsVms = false;
        foreach ($columns as $column) {
            if (preg_match('/^\(?vms\./', $column)) {
                $wantsVms = true;
                break;
            }
        }

        $query = $this->db()->select()->from(
            ['o' => 'object'],
            $columns
        )->join(
            ['h' => 'host_system'],
            'o.uuid = h.uuid',
            []
        )->join(
            ['hqs' => 'host_quick_stats'],
            'h.uuid = hqs.uuid',
            []
        );

        if ($wantsVms) {
            $query->joinLeft(
                ['vms' => $this->createVmSubQuery()],
                'vms.runtime_host_uuid = h.uuid',
                []
            );
        }

        return $query;
    }
}
