<?php

namespace Icinga\Module\Vspheredb\Controllers;

use Icinga\Authentication\Auth;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\Web\Controller;
use Icinga\Module\Vspheredb\Web\Table\HostPciDevicesTable;
use Icinga\Module\Vspheredb\Web\Table\Object\HostInfoTable;
use Icinga\Module\Vspheredb\Web\Table\Objects\VmsTable;
use dipl\Html\Link;
use Icinga\Module\Vspheredb\Web\Table\VMotionHistoryTable;
use Icinga\Module\Vspheredb\Web\Widget\AdditionalTableActions;
use Icinga\Module\Vspheredb\Web\Widget\Summaries;

class HostController extends Controller
{
    /** @var HostSystem */
    protected $host;

    public function init()
    {
        $this->host = $this->addHost();
        $this->handleTabs();
    }

    public function indexAction()
    {
        $table = new HostInfoTable($this->host, $this->vCenter(), $this->pathLookup());
        $this->content()->add($table);
    }

    public function vmsAction()
    {
        $table = new VmsTable($this->db());
        (new AdditionalTableActions($table, Auth::getInstance(), $this->url()))
            ->appendTo($this->actions());

        $table->handleSortUrl($this->url())
            ->filterHost($this->host->get('uuid'))
            ->renderTo($this);
        $summaries = new Summaries($table, $this->db(), $this->url());
        $this->content()->prepend($summaries);
    }

    public function pcidevicesAction()
    {
        $table = new HostPciDevicesTable($this->db());
        $table->filterHost($this->addHost())->renderTo($this);
    }

    public function vmotionsAction()
    {
        $table = new VMotionHistoryTable($this->db());
        $table->filterHost($this->addHost())->renderTo($this);
    }

    protected function addHost()
    {
        $host = HostSystem::load(hex2bin($this->params->getRequired('uuid')), $this->db());
        $this->addTitle($host->object()->get('object_name'));

        return $host;
    }

    protected function handleTabs()
    {
        $hexId = $this->params->getRequired('uuid');
        $this->tabs()->add('index', [
            'label' => $this->translate('Host System'),
            'url' => 'vspheredb/host',
            'urlParams' => ['uuid' => $hexId]
        ])->add('vms', [
            'label' => sprintf(
                $this->translate('Virtual Machines (%d)'),
                $this->host->countVms()
            ),
            'url' => 'vspheredb/host/vms',
            'urlParams' => ['uuid' => $hexId]
        ])->add('pcidevices', [
            'label' => $this->translate('PCI Devices'),
            'url' => 'vspheredb/host/pcidevices',
            'urlParams' => ['uuid' => $hexId]
        ])->add('vmotions', [
            'label' => $this->translate('VMotions'),
            'url' => 'vspheredb/host/vmotions',
            'urlParams' => ['uuid' => $hexId]
        ])->activate($this->getRequest()->getActionName());
    }

    protected function addLinkBackToHost()
    {
        $this->actions()->add(
            Link::create(
                $this->translate('Back to Host'),
                'vspheredb/host',
                ['uuid' => bin2hex($this->host->get('uuid'))],
                ['class' => 'icon-left-big']
            )
        );
    }
}
