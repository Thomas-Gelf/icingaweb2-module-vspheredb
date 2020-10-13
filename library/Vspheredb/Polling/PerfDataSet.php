<?php

namespace Icinga\Module\Vspheredb\Polling;

use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSet;
use JsonSerializable;

class PerfDataSet implements JsonSerializable
{
    /** @var int */
    protected $vCenterId;

    /** @var array */
    protected $counters;

    /** @var array */
    protected $requiredInstances;

    /**
     * ServerInfo constructor.
     * @param $vCenterId
     * @param array $counters
     * @param array $requiredInstances
     */
    public function __construct($vCenterId, array $counters, array $requiredInstances)
    {
        $this->vCenterId = $vCenterId;
        $this->counters = $counters;
        $this->requiredInstances = $requiredInstances;
    }

    public static function fromPlainObject($object)
    {
        return new static($object->vCenterId, (array) $object->counters, (array) $object->instances);
    }

    public static function fromPerformanceSet($vCenterId, PerformanceSet $set)
    {
        return new static($vCenterId, $set->getCounters(), $set->getRequiredInstances());
    }

    /**
     * @return array
     */
    public function getCounters()
    {
        return $this->counters;
    }

    /**
     * @return array
     */
    public function getRequiredInstances()
    {
        return $this->requiredInstances;
    }

    public function jsonSerialize()
    {
        return ((object) [
            'vCenterId' => $this->vCenterId,
            'counters'  => $this->getCounters(),
            'instances' => $this->getRequiredInstances(),
        ]);
    }
}
