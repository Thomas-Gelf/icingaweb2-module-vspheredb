<?php

namespace Icinga\Module\Vspheredb\Rpc;

use Exception;
use gipfl\Log\Logger;
use gipfl\Protocol\JsonRpc\Notification;
use gipfl\Protocol\JsonRpc\PacketHandler;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Icinga\Module\Vspheredb\Util;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class LogProxy implements PacketHandler
{
    use LoggerAwareTrait;

    protected $connection;

    protected $db;

    protected $vCenterUuid;

    protected $server;

    protected $instanceUuid;

    protected $prefix;

    public function __construct(Db $connection, LoggerInterface $logger, $instanceUuid)
    {
        $this->setLogger($logger);
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
        $this->instanceUuid = $instanceUuid;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function setServer(VCenterServer $server)
    {
        $this->server = $server;
    }

    public function setVCenter(VCenter $vCenter)
    {
        $this->vCenterUuid = $vCenter->get('instance_uuid');

        return $this;
    }

    public function log($severity, $message)
    {
        $message = $this->prefix . $message;
        $this->logger->$severity($message);
        if ($this->logger instanceof Logger) {
            if (! $this->logger->wants($severity, $message)) {
                return;
            }
        }
        try {
            $this->db->insert('vspheredb_daemonlog', [
                'vcenter_uuid'  => $this->vCenterUuid ?: str_repeat("\x00", 16),
                'instance_uuid' => $this->instanceUuid,
                'ts_create'     => Util::currentTimestamp(),
                'level'         => $severity,
                'message'       => $message,
            ]);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
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
