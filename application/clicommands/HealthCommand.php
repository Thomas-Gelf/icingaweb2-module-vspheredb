<?php

namespace Icinga\Module\Vspheredb\Clicommands;

/**
 * Health information for a vCenter or ESXi host
 */
class HealthCommand extends CommandBase
{
    public function checkAction()
    {
        $vCenter = $this->getVCenter();
        $api = $vCenter->getApi($this->logger);
        $time = $api->getCurrentTime()->format('U.u');
        $timeDiff = microtime(true) - (float)$time;
        if (abs($timeDiff) > 0.1) {
            printf("%0.3fms Time difference detected\n", $timeDiff * 1000);
        }

        printf("Connected to a %s\n", $vCenter->getFullName());
        echo $api->getVersionString();
    }
}
