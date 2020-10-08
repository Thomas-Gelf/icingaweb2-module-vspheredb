<?php

namespace Icinga\Module\Vspheredb\Clicommands;

use Exception;
use gipfl\Log\Logger;
use gipfl\Log\Writer\JsonRpcWriter;
use gipfl\Log\Writer\SystemdStdoutWriter;
use gipfl\Log\Writer\WritableStreamWriter;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\NetString\StreamWrapper;
use Icinga\Application\Cli;
use Icinga\Cli\Command;
use Icinga\Module\Vspheredb\CliUtil;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

class CommandBase extends Command
{
    /** @var VCenter */
    private $vCenter;

    /** @var LoopInterface */
    private $loop;

    private $loopStarted = false;

    protected $logger;

    /** @var Connection|null */
    protected $rpc;

    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Loop::create();
        }

        return $this->loop;
    }

    protected function eventuallyStartMainLoop()
    {
        if (! $this->loopStarted) {
            $this->loopStarted = true;
            $this->loop()->run();
        }

        return $this;
    }

    protected function stopMainLoop()
    {
        if ($this->loopStarted) {
            $this->loopStarted = false;
            $this->loop()->stop();
        }

        return $this;
    }

    protected function enableRpc()
    {
        $netString = new StreamWrapper(
            new ReadableResourceStream(STDIN, $this->loop()),
            new WritableResourceStream(STDOUT, $this->loop())
        );
        $this->rpc = new Connection();
        $this->rpc->handle($netString);
    }

    public function init()
    {
        $this->app->getModuleManager()->loadEnabledModules();
        $this->clearProxySettings();
        if ($this->isRpc()) {
            $this->enableRpc();
        }
        $this->initializeLogger();
    }

    protected function initializeLogger()
    {
        $this->logger = new Logger();
        if ($this->isRpc()) {
            $this->logger->addWriter(new JsonRpcWriter($this->rpc));
        } else {
            $loop = $this->loop();
            if (isset($_SERVER['NOTIFY_SOCKET'])) {
                $this->logger->addWriter(new SystemdStdoutWriter($loop));
            } else {
                $this->logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDERR, $loop)));
            }
        }
    }

    protected function isRpc()
    {
        return (bool) $this->params->get('rpc');
    }

    protected function clearProxySettings()
    {
        $settings = [
            'http_proxy',
            'https_proxy',
            'HTTPS_PROXY',
            'ALL_PROXY',
        ];
        foreach ($settings as $setting) {
            putenv("$setting=");
        }
    }

    protected function getVCenter()
    {
        if ($this->vCenter === null) {
            $this->vCenter = VCenter::loadWithAutoIncId(
                $this->requiredParam('vCenter'),
                Db::newConfiguredInstance()
            );
        }

        return $this->vCenter;
    }

    public function fail($msg)
    {
        echo $this->screen->colorize("$msg\n", 'red');
        exit(1);
    }

    protected function requireExtension()
    {
    }

    public function failFriendly($task, $error = 'unknown error', $subject = null)
    {
        if ($error instanceof Exception) {
            $message = $error->getMessage();
        } else {
            $message = $error;
        }

        if (!$this->isRpc()) {
            $this->fail($message);
        }

        CliUtil::setTitle(sprintf(
            'Icinga::vSphereDB::%s: (%sfailed: %s)',
            $task,
            $subject ? "$subject: " : '',
            $this->shorten($message, 60)
        ));
        $this->logger->error($message);
        // This allows to flush streams, especially pending log messages
        $this->loop()->addTimer(0.1, function () {
            $this->stopMainLoop();
            exit(1);
        });
        $this->eventuallyStartMainLoop();
    }

    protected function shorten($message, $length)
    {
        if (strlen($message) > $length) {
            return substr($message, 0, $length - 2) . '...';
        } else {
            return $message;
        }
    }

    protected function requiredParam($name)
    {
        $value = $this->params->get($name);
        if ($value === null) {
            /** @var Cli $app */
            $app = $this->app;
            $this->showUsage($app->cliLoader()->getActionName());
            $this->fail("'$name' parameter is required");
        }

        return $value;
    }
}
