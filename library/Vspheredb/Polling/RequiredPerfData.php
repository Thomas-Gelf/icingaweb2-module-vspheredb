<?php

namespace Icinga\Module\Vspheredb\Polling;

use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSet;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSets;
use JsonSerializable;

class RequiredPerfData implements JsonSerializable
{
    /** @var PerfDataSet[] */
    protected $sets = [];

    /**
     * ServerSet constructor.
     * @param PerfDataSet[] $sets
     */
    public function __construct($sets = [])
    {
        foreach ($sets as $set) {
            $this->addSet($set);
        }
    }

    public static function fromPlainObject($object)
    {
        $self = new static();
        foreach ($object as $set) {
            $self->addSet(PerfDataSet::fromPlainObject($set));
        }

        return $self;
    }

    public function addSet(PerfDataSet $set)
    {
        $this->sets[] = $set;
    }

    /**
     * @return PerfDataSet[]
     */
    public function getSets()
    {
        return $this->sets;
    }

    /**
     * @param Db $db
     * @return static
     */
    public static function fromDb(Db $db)
    {
        $vCenters = VCenter::loadAll($db);
        $servers = VCenterServer::loadAll($db);
        $vCenterServers = [];
        foreach ($servers as $server) {
            if ($server->get('enabled') === 'y') {
                $vCenterId = $server->get('vcenter_id');
                if (! isset($vCenterServers[$vCenterId])) {
                    $vCenterServers[$vCenterId] = $server->get('id');
                }
            }
        }
        $required = new static();
        foreach ($vCenters as $vCenter) {
            $vCenterId = $vCenter->get('id');
            if (! isset($vCenterServers[$vCenterId])) {
                continue;
            }
            foreach (PerformanceSets::createInstancesForVCenter($vCenter) as $set) {
                $required->addSet(PerfDataSet::fromPerformanceSet($vCenterId, $set));
            }
        }

        return $required;
    }

    public function jsonSerialize()
    {
        return $this->sets;
    }
}
