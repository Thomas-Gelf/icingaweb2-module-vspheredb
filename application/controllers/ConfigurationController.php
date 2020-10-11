<?php

namespace Icinga\Module\Vspheredb\Controllers;

use gipfl\IcingaWeb2\Link;
use gipfl\Web\Widget\Hint;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Module\Vspheredb\Web\Form\ChooseDbResourceForm;
use Icinga\Module\Vspheredb\Web\Form\MonitoringConnectionForm;
use Icinga\Module\Vspheredb\Web\Form\VCenterPerformanceCollectionForm;
use Icinga\Module\Vspheredb\Web\Table\MonitoredObjectMappingTable;
use Icinga\Module\Vspheredb\Web\Table\Objects\VCenterServersTable;
use Icinga\Module\Vspheredb\Web\Tabs\ConfigTabs;
use Icinga\Module\Vspheredb\Web\Controller;
use Icinga\Module\Vspheredb\Web\Widget\Config\ProposeMigrations;
use Icinga\Web\Notification;
use ipl\Html\Html;

class ConfigurationController extends Controller
{
    public function init()
    {
        $this->assertPermission('vspheredb/admin');
        parent::init();
    }

    public function databaseAction()
    {
        $this->addTitle($this->translate('vSphereDB Database Configuration'));
        $this->tabs(new ConfigTabs())->activate('database');
        $form = new ChooseDbResourceForm();
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
        if ($form->hasMessages()) {
            $this->addSingleTab($this->translate('Configuration'));

            return;
        }

        if ($this->Config()->get('db', 'resource')) {
            $db = $this->db();
            if ($db === null) {
                return;
            }

            $migrations = new ProposeMigrations($db, $this->Auth(), $this->getServerRequest());
            if ($migrations->hasAppliedMigrations()) {
                $this->redirectNow($this->url());
            }
            $this->content()->add($migrations);
        }
    }

    /**
     * @throws \Icinga\Security\SecurityException
     */
    public function serversAction()
    {
        $this->tabs(new ConfigTabs($this->db()))->activate('servers');
        $this->setAutorefreshInterval(10);
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

    public function monitoringAction()
    {
        $this->tabs(new ConfigTabs($this->db()))->activate('monitoring');
        $this->actions()->add(Link::create(
            $this->translate('Add'),
            'vspheredb/configuration/addmonitoring',
            null,
            [
                'class'            => 'icon-plus',
                'data-base-target' => '_next',
            ]
        ));
        $this->addTitle($this->translate('Monitoring Integration'));
        $table = new MonitoredObjectMappingTable($this->db());
        $table->handleSortPriorityActions($this->getRequest(), $this->getResponse());
        if (count($table)) {
            $wrapper = Html::tag('div', ['class' => 'icinga-module module-director']);
            $wrapper->wrap($table);
            $this->content()->add($wrapper);
            $table->renderTo($this);
        } else {
            $this->content()->add(Hint::warning($this->translate(
                'No integration has been configured'
            )));
        }
    }

    public function addmonitoringAction()
    {
        $this->addSingleTab($this->translate('Create'));
        $this->addTitle($this->translate('New Monitoring Integration'));
        $form = new MonitoringConnectionForm($this->db());
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }

    public function monitoringconfigAction()
    {
        $id = $this->params->get('id');
        if ($id) {
            $this->addSingleTab($this->translate('Modify'));

            $db = $this->db()->getDbAdapter();
            $res = $db->fetchRow(
                $db->select()->from('monitoring_connection')->where('id = ?', $id)
            );
        } else {
            $this->addSingleTab($this->translate('Create'));
            $res = null;
        }


        $this->addTitle($this->translate('Monitoring Integration'));
        $form = new MonitoringConnectionForm($this->db());
        if ($res) {
            $res->vcenter = \bin2hex($res->vcenter_uuid);
            $form->populate((array) $res);
        }
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }

    public function perfdataAction()
    {
        $this->tabs(new ConfigTabs($this->db()))->activate('perfdata');
        $this->addTitle($this->translate('Performance Data'));
        $this->content()->add(Html::tag('p', $this->translate(
            'This module can collect Performance Data from your vCenters or ESXi Hosts.'
            . ' Please configure a graphing implementation...'
        )));
        $store = new ZfDbStore($this->db()->getDbAdapter());
        $form = new VCenterPerformanceCollectionForm($store);
        $form->on(VCenterPerformanceCollectionForm::ON_SUCCESS, function () use ($form) {
            if ($form->wasNew()) {
                Notification::success($this->translate(
                    'Performance data subscription has been created'
                ));
            } else {
                Notification::success($this->translate(
                    'Performance data subscription has been updated'
                ));
            }
            $this->redirectNow($this->url());
        });
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }
}
