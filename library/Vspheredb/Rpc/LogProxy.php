<?php

namespace Icinga\Module\Vspheredb\Rpc;

use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\PacketHandler;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class LogProxy implements PacketHandler
{
    use LoggerAwareTrait;

    protected $connection;

    protected $db;

    protected $vCenterUuid;

    protected $server;

    protected $prefix;

    protected $context = [];

    public function __construct(Db $connection, LoggerInterface $logger)
    {
        $this->setLogger($logger);
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function setServer(VCenterServer $server)
    {
        $this->context['server'] = $server->get('host');
    }

    public function setVCenter(VCenter $vCenter)
    {
        $this->context['vcenter'] = bin2hex($vCenter->getUuid());

        return $this;
    }

    public function setPid($pid)
    {
        $this->context['pid'] = $pid;

        return $this;
    }

    public function log($severity, $message)
    {
        $message = $this->prefix . $message;
        $this->logger->$severity($message);
    }

    public function handle(Notification $notification)
    {
        // TODO: assert valid log level
        switch ($notification->getMethod()) {
            case 'logger.log':
                $this->log(
                    $notification->getParam('level'),
                    $notification->getParam('message')
                    // Also: timestamp, context
                );
                break;
        }
    }
}
