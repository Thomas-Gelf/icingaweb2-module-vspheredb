<?php

namespace Icinga\Module\Vspheredb\Web\Tabs;

use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;
use Exception;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\Db\Migrations;

class ConfigTabs extends Tabs
{
    use TranslationHelper;

    /** @var Db|null  */
    protected $connection;

    public function __construct(Db $connection = null)
    {
        $this->connection = $connection;
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

        $this->add('configuration', [
            'label' => $this->translate('Configuration'),
            'url'   => 'vspheredb/configuration',
        ]);

        if ($connection) {
            $migrations = new Migrations($connection);

            if (! $migrations->hasSchema()) {
                return;
            }
        } else {
            $migrations = null;
            return;
        }

        $this->add('servers', [
            'label' => $this->translate('Servers'),
            'url' => 'vspheredb/configuration/servers',
        ]);
        $this->add('perfdata', [
            'label' => $this->translate('Performance Data'),
            'url'   => 'vspheredb/configuration/perfdata',
        ]);

        // Disable Tab unless #160 is ready
        if ($migrations && $migrations->hasSchema()) {
            $this->add('monitoring', [
                'label' => $this->translate('Monitoring'),
                'url' => 'vspheredb/configuration/monitoring',
            ]);
        }
    }
}
