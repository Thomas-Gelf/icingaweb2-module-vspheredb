<?php

namespace Icinga\Module\Vspheredb\Hook;

use Icinga\Module\Vspheredb\PerformanceData\InfluxDb\DataPoint;
use Icinga\Web\Hook;
use InvalidArgumentException;
use ipl\Html\Form;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use React\EventLoop\LoopInterface;

abstract class PerfDataReceiverHook implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var LoopInterface */
    protected $loop;

    protected $settings = [];

    protected $queue;

    public static function initialize(LoopInterface $loop, $settings = [])
    {
        return (new static)->setLoop($loop)->setSettings($settings);
    }

    public function setLoop(LoopInterface $loop)
    {
        $this->loop = $loop;

        return $this;
    }

    public function setSettings($settings)
    {
        $this->settings = (array) $settings;

        return $this;
    }

    public function getSetting($name, $default = null)
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        } else {
            return $default;
        }
    }

    /**
     * @return string
     */
    public static function getName()
    {
        return preg_replace('/Hook$/', '', static::getClassBaseName(get_called_class()));
    }

    /**
     * @return Form
     */
    abstract public function getConfigurationForm();

    /**
     * @var DataPoint[] $points
     */
    public function pushDataPoints($points)
    {
        if (empty($this->queue)) {
            $this->queue[] = $points;
        } elseif (count($this->queue[0]) + count($points) < 1000) {
            $this->queue[0] = array_merge($this->queue[0], $points);
        } else {
            array_unshift($this->queue, $points);
        }
    }

    /**
     * Override this method, otherwise all data will be drpped
     *
     * @return bool
     */
    protected function processQueue()
    {
        array_pop($this->queue);
        return true;
    }

    public static function enum()
    {
        $enum = [];
        /** @var static $implementation */
        foreach (Hook::all('vspheredb/PerfDataReceiver') as $name => $implementation) {
            $module = static::getModuleFromClassName(get_class($implementation));
            if ($module === 'Vspheredb') {
                $enum[$name] = $implementation->getName();
            } else {
                $enum[$name] = $implementation->getName() . " ($module)";
            }
        }

        return $enum;
    }

    protected static function getClassBaseName($class)
    {
        $parts = \explode('\\', $class);
        return array_pop($parts);
    }

    protected static function getModuleFromClassName($class)
    {
        $parts = \explode('\\', ltrim($class, '\\'));
        if (count($parts) >= 3) {
            if ($parts[0] === 'Icinga' && $parts[1] === 'Module') {
                return $parts[2];
            }
        }

        throw new InvalidArgumentException("'$class' is not a valid Icinga Web 2 class name");
    }
}
