<?php

namespace Icinga\Module\Vspheredb\PerformanceData\InfluxDb;

class DataPoint
{
    protected $timestamp;

    protected $measurement;

    protected $tags = [];

    protected $fields;

    public function __construct($measurement, $tags = [], $fields = [], $timestamp = null)
    {
        $this->measurement = (string) $measurement;
        if ($timestamp !== null) {
            $this->timestamp = $timestamp;
        }

        if (! empty($tags)) {
            $this->tags = (array) $tags;
            \ksort($this->tags);
        }

        if (\is_array($fields) || \is_object($fields)) {
            $this->fields = (array) $fields;
        } else {
            $this->fields = ['value' => $fields];
        }

        if (empty($this->fields)) {
            throw new \InvalidArgumentException('At least one field/value is required');
        }
    }

    public function addTags($tags)
    {
        $this->tags = array_merge($this->tags, (array) $tags);
    }

    public function getTag($name, $default = null)
    {
        if (array_key_exists($name, $this->tags)) {
            return $this->tags[$name];
        } else {
            return $default;
        }
    }

    public function __toString()
    {
        return LineProtocol::renderMeasurement($this->measurement, $this->tags, $this->fields, $this->timestamp);
    }
}
