<?php

namespace Icinga\Module\Vspheredb\Clicommands;

use Exception;
use gipfl\Cli\Tty;
use gipfl\Log\Filter\LogLevelFilter;
use gipfl\Log\Logger;
use gipfl\Log\Writer\JsonRpcWriter;
use gipfl\Log\Writer\SystemdStdoutWriter;
use gipfl\Log\Writer\WritableStreamWriter;
use gipfl\Protocol\JsonRpc\Connection;
use gipfl\Protocol\NetString\StreamWrapper;
use Icinga\Cli\Command as CliCommand;
use Icinga\Module\Vspheredb\CliUtil;
use Icinga\Module\Vspheredb\Daemon\IcingaLogger;
use Icinga\Module\Vspheredb\Daemon\LoggerLogWriter;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use React\EventLoop\Factory as Loop;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

class Command extends CliCommand
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
        if (Tty::isSupported()) {
            $stdin = (new Tty($this->loop()))->setEcho(false)->stdin();
        } else {
            $stdin = new ReadableResourceStream(STDIN, $this->loop());
        }
        $netString = new StreamWrapper(
            $stdin,
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
        $this->logger = $logger = new Logger();
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        if ($this->isDebugging) {
            // Hint: no need to filter
            // $this->logger->addFilter(new LogLevelFilter('debug'));
        } elseif ($this->isVerbose) {
            $logger->addFilter(new LogLevelFilter('info'));
        } else {
            $logger->addFilter(new LogLevelFilter('notice'));
        }
        if ($this->isRpc()) {
            $logger->addWriter(new JsonRpcWriter($this->rpc));
        } else {
            $loop = $this->loop();
            if (isset($_SERVER['NOTIFY_SOCKET'])) {
                $logger->addWriter(new SystemdStdoutWriter($loop));
            } else {
                $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDERR, $loop)));
            }
        }
        IcingaLogger::replaceRunningInstance(new LoggerLogWriter($logger));
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
            /** @var \Icinga\Application\Cli $app */
            $app = $this->app;
            $this->showUsage($app->cliLoader()->getActionName());
            $this->fail("'$name' parameter is required");
        }

        return $value;
    }
}
