<?php

namespace Icinga\Module\Vspheredb\Clicommands;

use Icinga\Module\Vspheredb\Daemon\Daemon;

class DaemonCommand extends Command
{
    /**
     * vSphereDB background daemon
     *
     * USAGE
     *
     * icingacli vsphere daemon run [--verbose|--debug]
     */
    public function runAction()
    {
        if ($this->params->get('vCenterId')) {
            $this->fail(
                'The parameter --vCenterId has been deprecated with v1.0.0,'
                . ' please check our documentation'
            );
        }

        $daemon = new Daemon($this->logger);
        $daemon->run($this->loop());
        $this->eventuallyStartMainLoop();
    }
}
