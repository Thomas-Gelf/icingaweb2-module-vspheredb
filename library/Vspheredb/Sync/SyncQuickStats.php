<?php

namespace Icinga\Module\Vspheredb\Sync;

use Icinga\Module\Vspheredb\DbObject\HostQuickStats;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VmQuickStats;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

class SyncQuickStats
{
    use LoggerAwareTrait;

    /** @var VCenter */
    protected $vCenter;

    public function __construct(VCenter $vCenter)
    {
        $this->vCenter = $vCenter;
        $this->setLogger(new NullLogger());
    }

    public function run()
    {
        $vCenter = $this->vCenter;
        $this->logger->debug('Ready to sync QuickStats for Hosts and VMs');
        HostQuickStats::syncFromApi($vCenter, $this->logger);
        VmQuickStats::syncFromApi($vCenter, $this->logger);

        return $this;
    }
}
