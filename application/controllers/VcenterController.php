<?php

namespace Icinga\Module\Vspheredb\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\Web\Table\NameValueTable;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Icinga\Module\Vspheredb\Web\Controller;
use Icinga\Module\Vspheredb\Web\Form\VCenterForm;
use Icinga\Module\Vspheredb\Web\Form\VCenterServerForm;
use Icinga\Module\Vspheredb\Web\Table\Objects\VCenterServersTable;
use Icinga\Module\Vspheredb\Web\Tabs\MainTabs;
use Icinga\Module\Vspheredb\Web\Tabs\VCenterTabs;
use Icinga\Module\Vspheredb\Web\Widget\CpuUsage;
use Icinga\Module\Vspheredb\Web\Widget\MemoryUsage;
use Icinga\Module\Vspheredb\Web\Widget\SubTitle;
use Icinga\Module\Vspheredb\Web\Widget\VCenterHeader;
use Icinga\Module\Vspheredb\Web\Widget\VCenterSummaries;
use Icinga\Web\Notification;

class VcenterController extends Controller
{
    public function indexAction()
    {
        $vCenter = $this->requireVCenter();
        $this->tabs(new VCenterTabs($vCenter))->activate('vcenter');
        $this->controls()->add(new VCenterHeader($vCenter));
        $this->actions()->add(Link::create(
            $this->translate('Edit'),
            'vspheredb/vcenter/edit',
            ['vcenter' => $this->params->get('vcenter')],
            ['class' => 'icon-edit']
        ));
        $this->setAutorefreshInterval(10);
        // $this->content()->add(new VCenterSyncInfo($vCenter));
        $perf = $this->perf();
        $this->content()->add((new NameValueTable())->addNameValuePairs([
            'CPU'    => new CpuUsage($perf->used_mhz, $perf->total_mhz),
            'Memory' => new MemoryUsage($perf->used_mb, $perf->total_mb),
            'Disk'   => new MemoryUsage(
                ($perf->ds_capacity - $perf->ds_free_space) / (1024 * 1024),
                $perf->ds_capacity / (1024 * 1024)
            ),
        ]));
        $this->content()->add(new SubTitle($this->translate('Object Summaries')));
        $this->content()->add(new VCenterSummaries($vCenter));
    }

    public function editAction()
    {
        $vCenter = $this->requireVCenter();
        $this->tabs(new VCenterTabs($vCenter))->activate('vcenter');
        $this->setAutorefreshInterval(10);
        $this->controls()->add(new VCenterHeader($vCenter));
        $this->actions()->add(Link::create(
            $this->translate('Back'),
            'vspheredb/vcenter',
            ['vcenter' => $this->params->get('vcenter')],
            ['class' => 'icon-left-big']
        ));
        $form = new VCenterForm($this->db());
        $form->setObject($vCenter);
        $form->on(VCenterForm::ON_SUCCESS, function (VCenterForm $form) {
            $object = $form->getObject();
            if ($object->hasBeenModified()) {
                $msg = $this->translate('The vCenter has successfully been stored');
                $object->store();
            } else {
                $msg = $this->translate('No action taken, vCenter has not been modified');
            }
            Notification::success($msg);
            $this->redirectNow($this->getOriginalUrl());
        });
        $form->handleRequest($this->getServerRequest());

        $this->content()->add($form);
    }

    protected function perf()
    {
        $vCenter = $this->requireVCenter();
        $db = $vCenter->getConnection()->getDbAdapter();
        $query = $db->select()->from(
            ['h' => 'host_system'],
            [
                'used_mhz'  => 'SUM(hqs.overall_cpu_usage)',
                'total_mhz' => 'SUM(h.hardware_cpu_cores * h.hardware_cpu_mhz)',
                'used_mb'   => 'SUM(hqs.overall_memory_usage_mb)',
                'total_mb'  => 'SUM(h.hardware_memory_size_mb)',
            ]
        )->join(
            ['hqs' => 'host_quick_stats'],
            'h.uuid = hqs.uuid',
            []
        )->where('h.vcenter_uuid = ?', $vCenter->getUuid());
        $compute = $db->fetchRow($query);
            $query = $db->select()->from(
                ['ds' => 'datastore'],
                [
                    'ds_capacity'             => 'SUM(ds.capacity)',
                    'ds_free_space'           => 'SUM(ds.free_space)',
                    'ds_uncommitted'          => 'SUM(ds.uncommitted)',
                ]
            )->where('ds.vcenter_uuid = ?', $vCenter->getUuid());
        $storage = $db->fetchRow($query);

        return (object) ((array) $compute + (array) $storage);
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function serversAction()
    {
        $this->assertPermission('vspheredb/admin');
        $this->setAutorefreshInterval(10);
        $this->handleTabs();
        $this->addTitle($this->translate('vCenter Servers'));
        $this->actions()->add(
            Link::create(
                $this->translate('Add'),
                'vspheredb/vcenter/server',
                null,
                [
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
        );

        $table = new VCenterServersTable($this->db());
        $table->renderTo($this);
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function serverAction()
    {
        $this->assertPermission('vspheredb/admin');
        $this->addSingleTab($this->translate('vCenter Server'));
        $form = new VCenterServerForm(Db::newConfiguredInstance());
        if ($id = $this->params->get('id')) {
            $form->setObject(VCenterServer::loadWithAutoIncId($id, $this->db()));
            $this->addTitle($form->getObject()->get('host'));
        } else {
            $this->addTitle($this->translate('Create a new vCenter/ESXi-Connection'));
        }

        $form->on(VCenterServerForm::ON_SUCCESS, function (VCenterServerForm $form) {
            $object = $form->getObject();
            if ($object->hasBeenModified()) {
                $msg = sprintf(
                    $object->hasBeenLoadedFromDb()
                        ? $this->translate('The Connection has successfully been stored')
                        : $this->translate('A new Connection has successfully been created')
                );
                $object->store();
            } else {
                $msg = $this->translate('No action taken, object has not been modified');
            }
            Notification::success($msg);
            $this->redirectNow('vspheredb/vcenter/servers');
        });
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
        if ($form->hasBeenDeleted()) {
            Notification::success($this->translate('The connection has been deleted'));
            $this->redirectNow('vspheredb/vcenter/servers');
        }
    }

    protected function handleTabs()
    {
        $action = $this->getRequest()->getActionName();
        $tabs = $this->tabs(new MainTabs($this->Auth(), $this->db()));
        if ($tabs->has($action)) {
            $tabs->activate($action);
        } else {
            $this->redirectNow('vspheredb/configuration');
        }
    }
}
