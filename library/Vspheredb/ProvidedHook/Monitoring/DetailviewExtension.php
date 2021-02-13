<?php

namespace Icinga\Module\Vspheredb\ProvidedHook\Monitoring;

use Exception;
use Icinga\Module\Monitoring\Hook\DetailviewExtensionHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use Icinga\Module\Vspheredb\Format;
use Icinga\Module\Vspheredb\Web\Table\Object\HostSystemInfoTable;
use Icinga\Module\Vspheredb\Web\Table\Object\HostVmsInfoTable;
use Icinga\Module\Vspheredb\Web\Table\Object\HostVmsLinkTable;
use Icinga\Module\Vspheredb\Web\Table\Object\VmEssentialInfoTable;
use Icinga\Module\Vspheredb\Web\Table\Object\VmGraphTable;
use Icinga\Module\Vspheredb\Web\Table\Object\VmLinkTable;
use Icinga\Module\Vspheredb\Web\Table\Object\VmLocationInfoTable;
use Icinga\Module\Vspheredb\Web\Widget\CpuAbsoluteUsage;
use Icinga\Module\Vspheredb\Web\Widget\CpuUsage;
use Icinga\Module\Vspheredb\Web\Widget\HostHeader;
use Icinga\Module\Vspheredb\Web\Widget\MemoryUsage;
use Icinga\Module\Vspheredb\Web\Widget\VmHeader;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use ipl\Html\HtmlString;

class DetailviewExtension extends DetailviewExtensionHook
{
    private $monitoringConnectionRules;
    private $db;

    public function init()
    {
        $this->db = $this->db();

        $this->monitoringConnectionRules = $this->db->getDbAdapter()->fetchAll(
            $this->db->getDbAdapter()->select()->from('monitoring_connection')->order('priority')
        );
    }

    private function getVsphereHostByProperty($propertyName, $propertyValue, $vcenterUuid){
        try {
            $filter = [$propertyName => $propertyValue];
            if ($vcenterUuid) {
                $filter['vcenter_uuid'] = $vcenterUuid;
            }
            return HostSystem::findOneBy($filter, $this->db);
        } catch (Exception $e) {
            return null;
        }

    }

    private function getVsphereVmByProperty($propertyName, $propertyValue, $vcenterUuid){
        try {
            $filter = [$propertyName => $propertyValue];
            if ($vcenterUuid) {
                $filter['vcenter_uuid'] = $vcenterUuid;
            }
            return VirtualMachine::findOneBy($filter, $this->db);
        } catch (Exception $e) {
            return null;
        }
    }

    private function getVsphereHostFromRule($rule, $monitoredObject)
    {
        $vcenterUuid = $rule->vcenter_uuid;
        $monitoringHostProperty = $rule->monitoring_host_property;
        $propertyValue = $monitoredObject->$monitoringHostProperty;
        $vsphereHostProperty = $rule->host_property;
        return $this->getVsphereHostByProperty($vsphereHostProperty, $propertyValue, $vcenterUuid);
    }

    private function getVsphereVmFromRule($rule, $monitoredObject)
    {
        $vcenterUuid = $rule->vcenter_uuid;
        $monitoringVmHostProperty = $rule->monitoring_vm_host_property;
        $propertyValue = $monitoredObject->$monitoringVmHostProperty;
        $vsphereVmProperty = $rule->vm_property;
        return $this->getVsphereVmByProperty($vsphereVmProperty, $propertyValue, $vcenterUuid);
    }

    /**
     * @inheritDoc
     */
    public function getHtmlForObject(MonitoredObject $object)
    {
        $hookContent = Html::tag('div', ['class' => 'icinga-module module-vspheredb']);

        foreach ($this->monitoringConnectionRules as $rule) {

            if ($vSphereDbHostSystem = $this->getVsphereHostFromRule($rule, $object)){
                $vSphereDbHostLink = Link::create(
                    'ESXi Host: ' . $vSphereDbHostSystem->object()->get('object_name'),
                    'vspheredb/host',
                    ['uuid' => bin2hex($vSphereDbHostSystem->uuid), 'monitoring_obj' => $object->name]
                );
                $hookContent->add(
                    Html::tag('h2', null, $vSphereDbHostLink)
                );
                
                $cpu = new CpuAbsoluteUsage(
                    $vSphereDbHostSystem->quickStats()->get('overall_cpu_usage'),
                    $vSphereDbHostSystem->get('hardware_cpu_cores')
                );
                $mem = new MemoryUsage(
                    $vSphereDbHostSystem->quickStats()->get('overall_memory_usage_mb'),
                    $vSphereDbHostSystem->get('hardware_memory_size_mb')
                );
                $hookContent->add(Html::tag('div', ['style' => 'display: flex; align-items: flex-end;'])->add([$cpu, $mem]));

                return $hookContent->render();
            }

            if ($vSphereDbVm = $this->getVsphereVmFromRule($rule, $object)){
                $vSphereDbHostLink = Link::create(
                    'Virtual Machine: ' . $vSphereDbVm->object()->get('object_name'),
                    'vspheredb/vm',
                    ['uuid' => bin2hex($vSphereDbVm->uuid), 'monitoring_obj' => $object->name]
                );
                $hookContent->add(
                    Html::tag('h2', null, $vSphereDbHostLink)
                );

                $cpu = new CpuAbsoluteUsage(
                    $vSphereDbVm->quickStats()->get('overall_cpu_usage'),
                    $vSphereDbVm->get('hardware_numcpu')
                );
                $mem = new MemoryUsage(
                    $vSphereDbVm->quickStats()->get('guest_memory_usage_mb'),
                    $vSphereDbVm->get('hardware_memorymb'),
                    $vSphereDbVm->quickStats()->get('host_memory_usage_mb')
                );


                $hookContent->add(Html::tag('div', ['style' => 'display: flex; align-items: flex-end;'])->add([$cpu, $mem]));

                return $hookContent->render();
            }
        }
        return '';
    }

    protected function db()
    {
        if ($this->db === null) {
            $this->db = Db::newConfiguredInstance();
        }

        return $this->db;
    }

}