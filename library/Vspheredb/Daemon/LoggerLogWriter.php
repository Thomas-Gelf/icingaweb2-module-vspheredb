<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Icinga\Application\Logger as IcingaApplicationLogger;
use Icinga\Application\Logger\LogWriter;
use Icinga\Data\ConfigObject;
use Psr\Log\LoggerInterface;

class LoggerLogWriter extends LogWriter
{
    protected $logger;

    protected static $severityMap = [
        IcingaApplicationLogger::DEBUG   => 'debug',
        IcingaApplicationLogger::INFO    => 'info',
        IcingaApplicationLogger::WARNING => 'warning',
        IcingaApplicationLogger::ERROR   => 'error',
    ];

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct(new ConfigObject([]));
        $this->logger = $logger;
    }

    public function log($severity, $message)
    {
        $severity = static::$severityMap[$severity];
        $this->logger->$severity($message);
    }
}
