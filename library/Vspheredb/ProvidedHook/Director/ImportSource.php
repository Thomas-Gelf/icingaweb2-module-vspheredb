<?php

namespace Icinga\Module\Vspheredb\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Vspheredb\Api;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\Json;
use Zend_Db_Adapter_Abstract as ZfDb;

/**
 * Class ImportSource
 *
 * This is where we provide an Import Source for the Icinga Director
 */
class ImportSource extends ImportSourceHook
{
    /** @var Api */
    protected $api;

    protected $hostColumns = [
        'object_name'             => 'o.object_name',
        'sysinfo_vendor'          => 'h.sysinfo_vendor',
        'sysinfo_model'           => 'h.sysinfo_model',
        'bios_version'            => 'h.bios_version',
        'bios_release_date'       => 'h.bios_release_date',
        'service_tag'             => 'h.service_tag',
        'hardware_cpu_cores'      => 'h.hardware_cpu_cores',
        'hardware_memory_size_mb' => 'h.hardware_memory_size_mb',
        'custom_values'           => 'h.custom_values',
    ];

    protected $vmColumns = [
        'object_name'       => 'o.object_name',
        'guest_ip_address'  => 'vm.guest_ip_address',
        'hardware_numcpu'   => 'vm.hardware_numcpu',
        'hardware_memorymb' => 'vm.hardware_memorymb',
        'guest_id'          => 'vm.guest_id',
        'guest_full_name'   => 'vm.guest_full_name',
        'guest_host_name'   => 'vm.guest_host_name',
        'custom_values'     => 'vm.custom_values',
    ];

    protected $computeResourceColumns = [
        'object_name'              => 'o.object_name',
        'object_type'              => 'o.object_type',
        'effective_cpu_mhz'        => 'cr.effective_cpu_mhz',
        'effective_memory_size_mb' => 'cr.effective_memory_size_mb',
        'cpu_cores'                => 'cr.cpu_cores',
        'cpu_threads'              => 'cr.cpu_threads',
        'effective_hosts'          => 'cr.effective_hosts',
        'hosts'                    => 'cr.hosts',
        'total_cpu_mhz'            => 'cr.total_cpu_mhz',
        'total_memory_size_mb'     => 'cr.total_memory_size_mb',
    ];

    protected $datastoreColumns = [
        'object_name'          => 'o.object_name',
        'vcenter_name'         => 'vc.name',
        'maintenance_mode'     => 'ds.maintenance_mode',
        'capacity'             => 'ds.capacity',
        'multiple_host_access' => 'ds.multiple_host_access',
    ];

    public function getName()
    {
        return 'VMware vSphereDB';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'object_type', [
            'label' => $form->translate('Object Type'),
            'multiOptions' => $form->optionalEnum([
                'host_system'      => $form->translate('Host Systems'),
                'virtual_machine'  => $form->translate('Virtual Machine'),
                'compute_resource' => $form->translate('Compute Resource'),
                'datastore'        => $form->translate('Datastore'),
            ]),
            'required' => true
        ]);
    }

    public function fetchData()
    {
        $connection = Db::newConfiguredInstance();
        $db = $connection->getDbAdapter();
        $objectType = $this->getSetting('object_type');
        switch ($objectType) {
            case 'host_system':
                $result = $db->fetchAll($this->prepareHostsQuery($db));
                break;
            case 'virtual_machine':
                $result = $db->fetchAll($this->prepareVmQuery($db));
                break;
            case 'compute_resource':
                break;
            case 'datastore':
                $result = $db->fetchAll($this->prepareDatastoreQuery($db));
                break;
            default:
                return [];
        }

        if (empty($result)) {
            return [];
        }

        if (\in_array($objectType, ['host_system', 'virtual_machine'])) {
            foreach ($result as &$row) {
                if ($row->custom_values !== null) {
                    $row->custom_values = Json::decode($row->custom_values);
                }
            }
        }

        return $result;
    }

    protected function prepareVmQuery(ZfDb $db)
    {
        return $db->select()->from(['o' => 'object'], $this->vmColumns)->join(
            ['vm' => 'virtual_machine'],
            'o.uuid = vm.uuid',
            []
        )->order('o.object_name')->order('o.uuid');
    }

    protected function prepareHostsQuery(ZfDb $db)
    {
        return $db->select()->from(['o' => 'object'], $this->hostColumns)->join(
            ['h' => 'host_system'],
            'o.uuid = h.uuid',
            []
        )->order('o.object_name')->order('o.uuid');
    }

    protected function prepareComputeResourceQuery(ZfDb $db)
    {
        return $db->select()->from(['o' => 'object'], $this->computeResourceColumns)->join(
            ['cr' => 'compute_resource'],
            'o.uuid = cr.uuid',
            []
        )->order('o.object_name')->order('o.uuid');
    }

    protected function prepareDatastoreQuery(ZfDb $db)
    {
        return $db->select()->from(['o' => 'object'], $this->datastoreColumns)->join(
            ['ds' => 'datastore'],
            'o.uuid = ds.uuid',
            []
        )->order('o.object_name')->order('o.uuid');
    }

    public function listColumns()
    {
        switch ($this->getSetting('object_type')) {
            case 'host_system':
                return \array_keys($this->hostColumns);
            case 'virtual_machine':
                return \array_keys($this->vmColumns);
            case 'compute_resource':
                return \array_keys($this->computeResourceColumns);
            case 'datastore':
                return \array_keys($this->datastoreColumns);
            default:
                return [];
        }

        // Alternative: return $this->callOnManagedObject('getDefaultPropertySet');
    }

    protected function getManagedObjectClass()
    {
        return 'Icinga\\Module\\Vspheredb\\DbObject\\'
            . $this->getSetting('object_type');
    }

    protected function callOnManagedObject($method)
    {
        $params = func_get_args();
        array_shift($params);

        return call_user_func_array(array(
            $this->getManagedObjectClass(),
            $method
        ), $params);
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultKeyColumnName()
    {
        return 'object_name';
    }
}
