<?php

namespace Icinga\Module\Vspheredb\PerformanceData;

use Icinga\Module\Vspheredb\MappedClass\PerfEntityMetricCSV;
use Icinga\Module\Vspheredb\PerformanceData\InfluxDb\DataPoint;
use Icinga\Module\Vspheredb\Util;
use JsonSerializable;
use function count;
use function preg_split;

class CompactEntityMetrics implements JsonSerializable
{
    protected $properties;

    public function __construct($properties)
    {
        $this->properties = $properties;
    }

    public function getCounters()
    {
        return (array) $this->properties->counters;
    }

    public function getMeasurementName()
    {
        return $this->properties->perfSet;
    }

    public function normalize()
    {
        $result = [];
        $dates = $this->properties->dates;
        $counters = $this->getCounters();
        foreach ($this->properties->metrics as $instance => $series) {
            foreach ($series as $counterKey => $flatValues) {
                $counter = $counters[$counterKey];
                foreach ($flatValues as $idx => $value) {
                    $result[$dates[$idx]][$instance][$counter] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * @return DataPoint[]
     */
    public function getDataPoints()
    {
        $result = $this->normalize();
        $entity = $this->properties->entity;
        $measurement = $this->getMeasurementName();
        $points = [];
        foreach ($result as $time => $instances) {
            foreach ($instances as $instance => $counters) {
                $points[$time] = new DataPoint(
                    $measurement,
                    ['instance' => "$entity/$instance"],
                    $counters,
                    $time
                );
            }
        }

        return $points;
    }

    public static function process(PerfEntityMetricCSV $metric, $setName, $countersMap)
    {
        $object = $metric->entity->_;
        $dates = static::parseDates($metric);
        $result = (object) [
            'entity'   => $object,
            'perfSet'  => $setName,
            'dates'    => $dates,
            'counters' => $countersMap,
        ];
        $metrics = (object) [];
        foreach ($metric->value as $series) {
            $instance = $series->id->instance;
            if (! property_exists($metrics, $instance)) {
                $metrics->$instance = (object) [];
            }
            $metrics->$instance->{$series->id->counterId} = array_map(function ($value) {
                if ($value === '') {
                    return null;
                } else {
                    return (int) $value;
                }
            }, preg_split('/,/', $series->value));
        }
        $result->metrics = $metrics;

        return new static($result);
    }

    protected static function parseDates(PerfEntityMetricCSV $metric)
    {
        $parts = preg_split('/,/', $metric->sampleInfoCSV);
        $max = count($parts) - 1;
        $dates = [];
        for ($i = 1; $i <= $max; $i += 2) {
            $dates[] = Util::timeStringToUnixTime($parts[$i]);
        }

        return $dates;
    }

    public function jsonSerialize()
    {
        return $this->properties;
    }
}
