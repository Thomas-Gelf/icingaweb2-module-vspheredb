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
        return $this->servers;
    }
}
