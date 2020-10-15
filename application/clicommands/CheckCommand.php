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
class CheckCommand extends Command
{
    use CheckPluginHelper;

    /** @var Db */
    protected $db;

    /**
     * This establishes a connection to the given vCenter
     *
     * USAGE
     *
     * icingacli vspheredb check vcenter [--vCenter <id>]
     */
    public function vcenterconnectionAction()
    {
        $this->run(function () {
            $vCenter = $this->getVCenter();
            $vCenterName = $vCenter->get('name');
            $api = $vCenter->getApi($this->logger);
            try {
                $about = $api->getServiceInstance()->about;
                $time = $api->getCurrentTime()->format('U.u');
            } catch (\Exception $e) {
                if (preg_match('/CURL ERROR: (.+)$/', $e->getMessage(), $match)) {
                    $this->addProblem('CRITICAL', sprintf(
                        'Failed to contact %s: %s',
                        $vCenterName,
                        $match[1]
                    ));
                } else {
                    $this->addProblem('UNKNOWN', $e->getMessage());
                }
                return;
            }
            $timeDiff = microtime(true) - (float)$time;
            if (abs($timeDiff) > 0.1) {
                $this->raiseState('warning');
                if (abs($timeDiff) > 3) {
                    $this->raiseState('critical');
                }
                printf("%0.3fms Time difference detected\n", $timeDiff * 1000);
            }

            echo sprintf(
                "%s: Connected to %s on %s, api=%s (%s)\n",
                $vCenterName,
                $about->fullName,
                $about->osType,
                $about->apiType,
                $about->licenseProductName
            );
        });
    }

    /**
     * Check Host Health
     *
     * USAGE
     *
     * icingacli vspheredb check host [--name <name>]
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
     * icingacli vspheredb check vm [--name <name>]
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
     * icingacli vspheredb check datastore [--name <name>]
     */
    public function datastoreAction()
    {
        $this->run(function () {
            $db = Db::newConfiguredInstance();
            $datastore = Datastore::findOneBy([
                'object_name' => $this->params->getRequired('name')
            ], $db);
            $this->checkOverallHealth($datastore->object());
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
}
