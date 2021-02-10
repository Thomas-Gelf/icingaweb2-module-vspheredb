<?php

namespace Icinga\Module\Vspheredb\Clicommands;

use Icinga\Date\DateFormatter;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Vspheredb\CheckPluginHelper;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\BaseDbObject;
use Icinga\Module\Vspheredb\DbObject\Datastore;
use Icinga\Module\Vspheredb\DbObject\HostQuickStats;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\DbObject\ManagedObject;
use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use Icinga\Module\Vspheredb\DbObject\VmQuickStats;

/**
 * vSphereDB Check Command
 */
class CheckCommand extends CommandBase
{
    use CheckPluginHelper;

    /** @var Db */
    protected $db;

    /**
     * Check Host Health
     *
     * USAGE
     *
     * icingacli vspheredb check host [--name <name>] [--perfdata]
     */
    public function hostAction()
    {
        $this->run(function () {
            $db = $this->db();
            $host = HostSystem::findOneBy([
                'host_name' => $this->params->getRequired('name')
            ], $db);
            $quickStats = HostQuickStats::load($host->get('uuid'), $db);
            $this
                ->checkOverallHealth($host->object())
                ->checkRuntimePowerState($host)
                ->checkUptime($quickStats, $host)
            ;

            if (isset($this->params->getParams()['perfdata'])) {
                $this->addMessage($this->hostPerfData($host, $quickStats));
            }
        });
    }

    protected function requireObject()
    {
        return ManagedObject::load($this->params->getRequired('name'), $this->db());
    }

    /**
     * Check all Hosts
     *
     * USAGE
     *
     * icingacli vspheredb check hosts
     */
    public function hostsAction()
    {
        $this->showOverallStatusForProblems(
            HostSystem::listNonGreenObjects(Db::newConfiguredInstance())
        );
    }

    /**
     * Check Virtual Machine Health
     *
     * USAGE
     *
     * icingacli vspheredb check vm [--name <name>] [--perfdata]
     */
    public function vmAction()
    {
        $this->run(function () {
            $db = Db::newConfiguredInstance();
            try {
                $vm = VirtualMachine::findOneBy([
                    'object_name' => $this->params->getRequired('name')
                ], $db);
            } catch (NotFoundError $e) {
                $vm = VirtualMachine::findOneBy([
                    'guest_host_name' => $this->params->getRequired('name')
                ], $db);
            }
            $quickStats = VmQuickStats::load($vm->get('uuid'), $db);
            $this->checkOverallHealth($vm->object())
                ->checkRuntimePowerState($vm)
                ->checkUptime($quickStats, $vm);

            if (isset($this->params->getParams()['perfdata'])) {
                $this->addMessage($this->vmPerfData($vm, $quickStats));
            }
        });
    }

    /**
     * Check all Virtual Machines
     *
     * USAGE
     *
     * icingacli vspheredb check vms
     */
    public function vmsAction()
    {
        $this->showOverallStatusForProblems(
            VirtualMachine::listNonGreenObjects(Db::newConfiguredInstance())
        );
    }

    /**
     * Check Datastore Health
     *
     * USAGE
     *
     * icingacli vspheredb check datastore [--name <name>] [--perfdata]
     */
    public function datastoreAction()
    {
        $this->run(function () {
            $db = Db::newConfiguredInstance();
            $datastore = Datastore::findOneBy([
                'object_name' => $this->params->getRequired('name')
            ], $db);
            $this->checkOverallHealth($datastore->object());

            if (isset($this->params->getParams()['perfdata'])) {
                $this->addMessage($this->datastorePerfData($datastore));
            }
        });
    }

    /**
     * Check all Datastores
     *
     * USAGE
     *
     * icingacli vspheredb check datastores
     */
    public function datastoresAction()
    {
        $this->showOverallStatusForProblems(
            Datastore::listNonGreenObjects(Db::newConfiguredInstance())
        );
    }

    protected function showOverallStatusForProblems($problems)
    {
        $this->run(function () use ($problems) {
            if (empty($problems)) {
                $this->addMessage('Everything is fine');
            } else {
                foreach ($problems as $color => $objects) {
                    $this->raiseState($this->getStateForColor($color));
                    $this->addProblematicObjectNames($color, $objects);
                }
            }
        });
    }

    protected function addProblematicObjectNames($color, $objects)
    {
        $showMax = 5;
        $stateName = $this->getStateForColor($color);
        if (count($objects) === 1) {
            $name = array_shift($objects);
            $this->addProblem($stateName, sprintf('Overall status for %s is "%s"', $name, $color));
        } elseif (count($objects) <= $showMax) {
            $last = array_pop($objects);
            $this->addProblem($stateName, sprintf(
                'Overall status is "%s" for %s and %s',
                $color,
                implode(', ', $objects),
                $last
            ));
        } else {
            $names = array_slice($objects, 0, $showMax);
            $this->addProblem($stateName, sprintf(
                'Overall status is "%s" for %s and %d more',
                $color,
                implode(', ', $names),
                count($objects) - $showMax
            ));
        }
    }

    /**
     * @param ManagedObject $object
     * @return $this
     */
    protected function checkOverallHealth(ManagedObject $object)
    {
        switch ($object->get('overall_status')) {
            case 'green':
                $this->prependMessage('Overall status is "green"');
                break;
            case 'gray':
                $this->addProblem('CRITICAL', 'Overall status is "gray", VM might be unreachable');
                break;
            case 'yellow':
                $this->addProblem('WARNING', 'Overall status is "yellow"');
                break;
            case 'red':
                $this->addProblem('CRITICAL', 'Overall status is "critical"');
                break;
            default:
                // Cannot happen
        }

        return $this;
    }

    protected function getStateForColor($color)
    {
        $colors = [
            'green'  => 'OK',
            'gray'   => 'CRITICAL',
            'yellow' => 'WARNING',
            'red'    => 'CRITICAL',
        ];

        return $colors[$color];
    }

    /**
     * @param BaseDbObject $object
     * @return $this
     */
    protected function checkRuntimePowerState(BaseDbObject $object)
    {
        if ($object instanceof VirtualMachine) {
            $what = 'Virtual Machine';
        } elseif ($object instanceof HostSystem) {
            $what = 'Host System';
        } else {
            $what = 'Object';
        }

        switch ($object->get('runtime_power_state')) {
            case 'poweredOff':
                $this->addProblem('CRITICAL', "$what has been powered off");
                break;
            case 'suspended':
                $this->addProblem('CRITICAL', "$what has been suspended");
                break;
            case 'unknown':
                $this->addProblem('UNKNOWN', "$what power state is unknown, might be disconnected");
                break;
            case 'poweredOn':
            default:
                // That's fine
                // Cannot happen
        }

        return $this;
    }

    /**
     * @param BaseDbObject $stats
     * @param BaseDbObject $object
     * @return $this
     */
    protected function checkUptime(BaseDbObject $stats, BaseDbObject $object)
    {
        if ($object->get('runtime_power_state') !== 'poweredOn') {
            // No need to check uptime
            return $this;
        }
        if ($stats->get('uptime') < 900) {
            $this->addProblem('WARNING', sprintf(
                'System booted %s ago',
                DateFormatter::formatDuration($stats->get('uptime'))
            ));
        }

        return $this;
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = Db::newConfiguredInstance();
        }

        return $this->db;
    }

    protected function hostPerfData($host, $quickStats) {
        $hostQuickStats = $quickStats->get('properties');
        $hostProperties = $host->get('properties');

        $hostCpuUsage    = $hostQuickStats['overall_cpu_usage'];
        $hostCpuCores    = $hostProperties['hardware_cpu_cores'];
        $hostCpuMHz      = $hostProperties['hardware_cpu_mhz'];
        $hostCpuCapacity = $hostCpuCores * $hostCpuMHz;

        $hostMemUsage    = $hostQuickStats['overall_memory_usage_mb'];
        $hostMemCapacity = $hostProperties['hardware_memory_size_mb'];

        $perfData = array(
            'overall_cpu_usage'         => $hostCpuUsage,
            'overall_memory_usage_mb'   => $hostMemUsage,
            'hardware_memory_size_mb'   => $hostMemCapacity,

            'hardware_cpu_capacity_mhz' => $hostCpuCapacity,
            'overall_memory_usage_pct'  => sprintf('%.2f%%', 100*$hostMemUsage/$hostMemCapacity),
            'overall_cpu_usage_pct'     => sprintf('%.2f%%', 100*$hostCpuUsage/$hostCpuCapacity),
        );

        return $this->formatPerfData($perfData);
    }

    protected function vmPerfData($vm, $quickStats) {
        $vmQuickStats = $quickStats->get('properties');

        $vmMemoryUsage    = $vmQuickStats['guest_memory_usage_mb'];
        $vmMemoryCapacity = $vm->get('hardware_memorymb');

        $perfData = array(
            'ballooned_memory_mb'               => $vmQuickStats['ballooned_memory_mb'],
            'compressed_memory_kb'              => $vmQuickStats['compressed_memory_kb'],
            'consumed_overhead_memory_mb'       => $vmQuickStats['consumed_overhead_memory_mb'],
            'distributed_cpu_entitlement'       => $vmQuickStats['distributed_cpu_entitlement'],
            'distributed_memory_entitlement_mb' => $vmQuickStats['distributed_memory_entitlement_mb'],
            'guest_memory_usage_mb'             => $vmMemoryUsage,
            'host_memory_usage_mb'              => $vmQuickStats['host_memory_usage_mb'],
            'overall_cpu_demand'                => $vmQuickStats['overall_cpu_demand'],
            'overall_cpu_usage'                 => $vmQuickStats['overall_cpu_usage'],
            'private_memory_mb'                 => $vmQuickStats['private_memory_mb'],
            'shared_memory_mb'                  => $vmQuickStats['shared_memory_mb'],
            'hardware_memory_mb'                => $vmMemoryCapacity,
            'ssd_swapped_memory_kb'             => $vmQuickStats['ssd_swapped_memory_kb'],
            'static_cpu_entitlement'            => $vmQuickStats['static_cpu_entitlement'],
            'static_memory_entitlement_mb'      => $vmQuickStats['static_memory_entitlement_mb'],
            'swapped_memory_mb'                 => $vmQuickStats['swapped_memory_mb'],

            'guest_memory_usage_pct'            => sprintf('%.2f%%', 100*$vmMemoryUsage/$vmMemoryCapacity),
        );

        return $this->formatPerfData($perfData);
    }

    protected function datastorePerfData($datastore) {
        $datastoreProperties = $datastore->get('properties');

        $datastoreCapacity    = $datastoreProperties['capacity'];
        $datastoreFreeSpace   = $datastoreProperties['free_space'];
        $datastoreUncommitted = $datastoreProperties['uncommitted'];

        $datastoreUsedSpace = $datastoreCapacity-$datastoreFreeSpace;
        $datastoreCommitted = $datastoreCapacity-$datastoreUncommitted;

        $perfData = array(
            'capacity'        => $datastoreCapacity,
            'free_space'      => $datastoreFreeSpace,
            'uncommitted'     => $datastoreUncommitted,

            'used_space'      => $datastoreUsedSpace,
            'committed'       => $datastoreCommitted,

            'free_space_pct'  => sprintf('%.2f%%', 100*$datastoreFreeSpace/$datastoreCapacity),
            'used_space_pct'  => sprintf('%.2f%%', 100*$datastoreUsedSpace/$datastoreCapacity),
            'uncommitted_pct' => sprintf('%.2f%%', 100*$datastoreUncommitted/$datastoreCapacity),
            'committed_pct'   => sprintf('%.2f%%', 100*$datastoreCommitted/$datastoreCapacity),
        );

        return $this->formatPerfData($perfData);
    }

    protected function formatPerfData($perfData) {
        $perfDataOutput = array();
        foreach ($perfData as $key => $value) {
            $perfDataOutput[] = sprintf('%s=%s', $key, $value);
        }
        return '|'.implode(' ', $perfDataOutput);
    }
}
