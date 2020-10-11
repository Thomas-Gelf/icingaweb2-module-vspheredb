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
        $isAdmin = $this->auth->hasPermission('vspheredb/admin');

        if ($connection) {
            $migrations = new Migrations($connection);

            if ($migrations->hasSchema()) {
                $this->add('vcenters', [
                    'label'     => $this->translate('vCenters'),
                    'url'       => 'vspheredb/vcenters',
                ]);
            }
        } else {
            $migrations = null;
        }

        if ($isAdmin && $migrations && $migrations->hasSchema()) {
            $this->add('daemon', [
                'label' => $this->translate('Daemon'),
                'url' => 'vspheredb/daemon',
            ]);
        }
    }
}
