<?php

namespace Icinga\Module\Vspheredb\Web;

use Exception;
use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;

class Controller extends CompatController
{
    /** @var Db */
    private $db;

    public function init()
    {
        parent::init();
        if ($this->view->compact) {
            $this->controls()->addAttributes([
                'class' => 'show-compact'
            ]);
        }
    }

    protected function db()
    {
        if ($this->db === null) {
            try {
                $this->db = Db::newConfiguredInstance();
                $migrations = new Db\Migrations($this->db);
                if (! $migrations->hasSchema()) {
                    $this->redirectToConfiguration();
                }
            } catch (Exception $e) {
                $this->redirectToConfiguration();
            }
        }

        return $this->db;
    }

    protected function requireVCenter($paramName = 'vcenter')
    {
        $hexUuid = $this->params->getRequired($paramName);
        return VCenter::loadWithHexUuid($hexUuid, $this->db());
    }

    protected function redirectToConfiguration()
    {
        if ($this->getRequest()->getControllerName() !== 'configuration'
            || $this->getRequest()->getActionName() !== 'database'
        ) {
            $this->redirectNow('vspheredb/configuration/database');
        }
    }
}
