<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Icinga\Application\Logger as IcingaApplicationLogger;
use Icinga\Exception\ConfigurationError;

class IcingaLogger extends IcingaApplicationLogger
{
    public static function replaceRunningInstance(LoggerLogWriter $writer, $level = null)
    {
        try {
            $instance = static::$instance;
            if ($level !== null) {
                $instance->setLevel($level);
            }

            $instance->writer = $writer;
        } catch (ConfigurationError $e) {
            static::$instance->error($e->getMessage());
        }
    }
}
