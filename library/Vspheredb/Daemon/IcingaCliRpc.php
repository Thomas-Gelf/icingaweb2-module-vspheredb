<?php

namespace Icinga\Module\Vspheredb\Daemon;

use Exception;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\NetString\StreamWrapper;
use React\ChildProcess\Process;

class IcingaCliRpc extends IcingaCli
{
    /** @var IcingaCliRunner */
    protected $runner;

    /** @var Connection */
    protected $rpc;

    protected $arguments = [];

    protected function init()
    {
        $this->on('start', function (Process $process) {
            $netString = new StreamWrapper(
                $process->stdout,
                $process->stdin
            );
            $netString->on('error', function (Exception $e) {
                $this->emit('error', [$e]);
            });
            $this->rpc()->handle($netString);
        });
    }

    /**
     * @return Connection
     */
    public function rpc()
    {
        if ($this->rpc === null) {
            $this->rpc = new Connection();
        }

        return $this->rpc;
    }
}
