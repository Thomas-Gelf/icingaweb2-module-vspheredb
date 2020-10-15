<?php

namespace Icinga\Module\Vspheredb\Clicommands;

use Icinga\Module\Vspheredb\CheckPluginHelper;

class HealthCommand extends Command
{
    use CheckPluginHelper;

    public function checkAction()
    {
        $this->run(function () {
            $this->addProblem('UNKNOWN', 'Please use `icingacli vspheredb check vcenterconnection`');
            $this->addMessage('This command has never been documented and got removed with v1.2.0');
        });
    }
}
