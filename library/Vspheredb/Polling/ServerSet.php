<?php

namespace Icinga\Module\Vspheredb\Polling;

use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use JsonSerializable;

class ServerSet implements JsonSerializable
{
    protected $servers = [];

    /**
     * ServerSet constructor.
     * @param ServerInfo[] $servers
     */
    public function __construct($servers = [])
    {
        foreach ($servers as $server) {
            $this->addServer($server);
        }
    }

    public static function fromPlainObject($object)
    {
        $self = new static();
        foreach ($object as $key => $server) {
            $info = ServerInfo::fromPlainObject($server);
            // This doesn't fail - yet
            assert($key === $info->getIdentifier());
            $self->addServer($info);
        }

        return $self;
    }

    public function addServer(ServerInfo $server)
    {
        $this->servers[$server->getIdentifier()] = $server;
        ksort($this->servers);
    }

    /**
     * @param VCenterServer[] $servers
     * @return static
     */
    public static function fromServers($servers)
    {
        $serverInfo = [];
        foreach ($servers as $server) {
            $serverInfo[] = ServerInfo::fromServer($server);
        }

        return new static($serverInfo);
    }

    public function jsonSerialize()
    {
        return (object) $this->servers;
    }
}
