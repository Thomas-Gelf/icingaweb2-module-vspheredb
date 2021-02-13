<?php

namespace Icinga\Module\Vspheredb\Web\Tabs;

use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;
use Exception;
use Icinga\Authentication\Auth;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\Db\Migrations;

class MainTabs extends Tabs
{
    use TranslationHelper;

    /** @var Db|null  */
    protected $connection;

    /** @var Auth */
    protected $auth;

    public function __construct(Auth $auth, Db $connection = null)
    {
        $this->connection = $connection;
        $this->auth = $auth;
        // We are not a BaseElement, not yet
        $this->assemble();
    }

    protected function assemble()
    {
        if ($this->connection) {
            $connection = $this->connection;
        } else {
            try {
                $connection = Db::newConfiguredInstance();
            } catch (Exception $e) {
                $connection = null;
            }
        }

        if ($connection) {
            $migrations = new Migrations($connection);

            if ($migrations->hasSchema()) {
                $this->add('vcenters', [
                    'label'     => $this->translate('vCenters'),
                    'url'       => 'vspheredb/vcenters',
                ]);

                if ($this->auth->hasPermission('vspheredb/admin')) {
                    $this->add('servers', [
                        'label' => $this->translate('Servers'),
                        'url' => 'vspheredb/vcenter/servers',
                    ]);
                }
            }
        } else {
            $migrations = null;
        }

        if ($this->auth->hasPermission('vspheredb/admin')) {
            $this->add('configuration', [
                'label' => $this->translate('Configuration'),
                'url'   => 'vspheredb/configuration',
            ]);

            // Disable Tab unless #160 is ready
            if ($migrations && $migrations->hasSchema()) {
                $this->add('monitoring', [
                    'label' => $this->translate('Monitoring'),
                    'url' => 'vspheredb/configuration/monitoring',
                ]);
            }
        }
        if ($migrations && $migrations->hasSchema()) {
            $this->add('daemon', [
                'label' => $this->translate('Daemon'),
                'url' => 'vspheredb/daemon',
            ]);
        }
    }
}
