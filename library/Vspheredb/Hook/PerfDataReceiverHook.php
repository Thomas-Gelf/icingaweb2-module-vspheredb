<?php

namespace Icinga\Module\Vspheredb\Hook;

use Icinga\Web\Hook;
use InvalidArgumentException;
use ipl\Html\Form;

abstract class PerfDataReceiverHook
{
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
