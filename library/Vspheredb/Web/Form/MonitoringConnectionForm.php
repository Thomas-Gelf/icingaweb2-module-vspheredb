<?php

namespace Icinga\Module\Vspheredb\Web\Form;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Vspheredb\Web\QueryParams;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Form;
use ipl\Html\FormDecorator\DdDtDecorator;
use ipl\Html\FormDecorator\DecoratorInterface;
use ipl\Html\FormElement\SubmitElement;
use ipl\Html\Html;
use gipfl\Translation\TranslationHelper;
use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Vspheredb\Db;
use InvalidArgumentException;
use ipl\Html\HtmlDocument;

class MonitoringConnectionForm extends Form
{
    use TranslationHelper;

    protected $db;

    public function __construct(Db $connection)
    {
        $this->addElementLoader(__NAMESPACE__ . '\\Element');
        $this->db = $connection->getDbAdapter();
        Html::tag('div', ['class' => ['icinga-module', 'module-director']])->wrap($this);
        $this->setDefaultElementDecorator(new DdDtDecorator());
    }

    protected function assemble()
    {
        $this->add(Html::tag('p', $this->translate(
            'The vSphereDB module can hook into the Icinga monitoring module.'
            . ' This allows to show context information related to your Virtualization'
            . ' infrastructure next to each monitored Host.'
        )));

        $this->addElement('select', 'vcenter', [
            'label'    => $this->translate('vCenter'),
            'options'  => $this->optionalEnum($this->enumVCenters()),
            'ignore'   => true,
        ]);

        $this->addElement('select', 'source_type', [
            'label'   => $this->translate('Source Type'),
            'options' => $this->optionalEnum([
                'ido'         => $this->translate('IDO'),
                // 'icinga2-api' => $this->translate('Icinga 2 API'),
                // 'icingadb'    => $this->translate('IcingaDB'),
            ]),
            'class' => 'autosubmit',
        ]);
        $sourceType = $this->getElement('source_type')->getValue();
        if (! $sourceType) {
            return;
        }

        $this->addElement('select', 'source_resource_name', [
            'label'   => $this->translate('IDO Resource'),
            'options' => $this->optionalEnum($this->enumIdoResourceNames()),
            'class'   => 'autosubmit',
        ]);

        $resourceName = $this->getElement('source_resource_name')->getValue();
        if (! $resourceName) {
            $this->addElement('submit', 'submit', [
                'label' => $this->translate('Next')
            ]);
            return;
        }

        try {
            $resource = ResourceFactory::create($resourceName);
            if ($resource instanceof DbConnection) {
                $idoVars = $this->enumIdoCustomVars($resource);
            } else {
                throw new InvalidArgumentException("Resource '$resourceName' is not a DbConnection");
            }
        } catch (\Exception $e) {
            $this->getElement('source_resource_name')->addMessage($e->getMessage());
        }
        $idoOptions = $this->optionalEnum([
            'host_name'    => $this->translate('Hostname'),
            'display_name' => $this->translate('Display Name'),
            'address'      => $this->translate('Address'),
        ] + [$this->translate('Custom Variables') => $idoVars]);

        $this->add(Html::tag('h2', $this->translate('Host Systems')));
        $this->add(Html::tag('p', $this->translate(
            'Map monitored hosts to physical Host Systems belonging to your'
            . ' VMware environment'
        )));
        $this->addElement('select', 'host_property', [
            'label'       => $this->translate('Host System Property'),
            'description' => $this->translate(
                'Property of the Host System (known by the vSphereDB module)'
            ),
            'options'     => $this->optionalEnum([
                'host_name'    => $this->translate('Hostname'),
                'object_name'  => $this->translate('Object Name'),
                'sysinfo_uuid' => $this->translate('System (BIOS) UUID'),
                'service_tag'  => $this->translate('IP Address'),
            ]),
        ]);
        $this->addElement('select', 'monitoring_host_property', [
            'label'       => $this->translate('Monitored Host Property'),
            'description' => $this->translate(
                'Property of the Host System (as known by Icinga)'
            ),
            'options'     => $idoOptions,
        ]);

        $this->add(Html::tag('h2', $this->translate('Virtual Machines')));
        $this->add(Html::tag('p', $this->translate(
            'Map monitored hosts to Virtual Machines belonging to your'
            . ' VMware environment'
        )));
        $this->addElement('select', 'vm_property', [
            'label'       => $this->translate('Virtual Machine Property'),
            'description' => $this->translate(
                'Property of the Virtual Machine (known by the vSphereDB module)'
            ),
            'options'     => $this->optionalEnum([
                'guest_host_name' => $this->translate('Guest Hostname'),
                'object_name'     => $this->translate('Object Name'),
                'bios_uuid'       => $this->translate('BIOS UUID'),
            ]),
        ]);
        $this->addElement('select', 'monitoring_vm_host_property', [
            'label'       => $this->translate('Monitored Host Property'),
            'description' => $this->translate(
                'Property of the Virtual Machine (as known by Icinga)'
            ),
            'options'     => $idoOptions,
        ]);

        $submit = $this->createElement('submit', 'submit', [
            'label' => $this->translate('Store')
        ]);
        $buttons = new HtmlDocument();
        $buttons->add($submit)->setSeparator(' ');
        $this->registerElement($submit);

        if ($id = $this->getId()) {
            $delete = new SubmitElement('delete', [
                'label' => $this->translate('Delete')
            ]);

            $buttons->add($delete);
            $this->registerElement($delete);
            if ($delete->hasBeenPressed()) {
                $this->db->delete(
                    'monitoring_connection',
                    $this->db->quoteInto('id = ?', $id)
                );
            }
        }

        $this->addElement('html', 'buttons', [
            'content' => $buttons,
            'ignore'  => true,
        ]);
    }

    protected function getId()
    {
        return QueryParams::fromRequest($this->getRequest())->get('id');
    }

    public function onSuccess()
    {
        $values = $this->getValues();
        $db = $this->db;
        $id = $this->getId();
        $vCenterUuid = \hex2bin($this->getValue('vcenter'));
        if ($id) {
            $db->update(
                'monitoring_connection',
                $values,
                $db->quoteInto('id = ?', $id)
            );
        } else {
            $priority = (int) $db->fetchOne(
                $db->select()->from('monitoring_connection', 'MAX(priority)')
            ) + 1;
            $db->insert('monitoring_connection', $values + [
                'vcenter_uuid' => $vCenterUuid,
                'priority'     => $priority,
            ]);
        }
    }

    protected function enumIdoCustomVars(DbConnection $db)
    {
        $dba = $db->getDbAdapter();

        $vars = $dba->fetchPairs(
            $dba->select()->from(
                ['cvs' => 'icinga_customvariablestatus'],
                [
                    'varname'   => 'cvs.varname',
                    'varcount' => 'COUNT(*)'
                ]
            )->join(
                ['o' => 'icinga_objects'],
                'o.object_id = cvs.object_id AND o.is_active = 1',
                []
            )
            ->group('varname')
            ->order('varname')
        );

        $result = [];
        foreach ($vars as $name => $count) {
            $result["vars.$name"] = "vars.$name (${count}x)";
        }

        return $result;
    }

    protected function enumHostParents()
    {
        $db = $this->db;
        $query = $db->select()->from(
            ['p' => 'object'],
            ['p.uuid', 'p.object_name']
        )->join(
            ['c' => 'object'],
            'c.parent_uuid = p.uuid AND '
            . $db->quoteInto('c.object_type = ?', 'HostSystem')
            . ' AND '
            . $db->quoteInto('p.object_type = ?', 'ClusterComputeResource'),
            []
        )->group('p.uuid')->order('p.object_name');

        $enum = [];
        foreach ($db->fetchPairs($query) as $k => $v) {
            $enum[bin2hex($k)] = $v;
        }

        return $enum;
    }

    protected function enumIdoResourceNames()
    {
        $resources = [];
        foreach (Config::module('monitoring', 'backends') as $name => $config) {
            if ((bool) $config->get('disabled', false) === false) {
                $resourceName = $config->get('resource');
                if ($name !== $resourceName) {
                    $name = sprintf('%s (%s)', $name, $resourceName);
                }
                $resources[$resourceName] = $name;
            }
        }

        return $resources;
    }

    protected function enumVCenters()
    {
        return $this->db->fetchPairs(
            $this->db->select()->from(
                ['vc' => 'vcenter'],
                [
                    'uuid' => 'LOWER(HEX(vc.instance_uuid))',
                    'host' => 'vcs.host',
                ]
            )->join(
                ['vcs' => 'vcenter_server'],
                'vcs.vcenter_id = vc.id',
                []
            )
        );
    }

    protected function optionalEnum($values)
    {
        return [
            null => $this->translate('- please choose -'),
        ] + $values;
    }
}
