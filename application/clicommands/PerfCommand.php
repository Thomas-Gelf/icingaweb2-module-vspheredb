<?php

namespace Icinga\Module\Vspheredb\Clicommands;

use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\PerformanceData\InfluxDbStreamer;
use React\EventLoop\LoopInterface;

class PerfCommand extends Command
{
    public function hostAction()
    {
        $sql = 'SELECT o.object_name, vdu.disk_path, vdu.capacity,'
          . ' vdu.free_space from object o join vm_disk_usage vdu'
          . ' on vdu.vm_uuid = o.uuid';
        $db = Db::newConfiguredInstance()->getDbAdapter();
        foreach ($db->fetchAll($sql) as $row) {
            $ciName = str_replace(' ', '_', $row->object_name);
            $path = str_replace('/', '_', $row->disk_path);
            $path = str_replace(' ', '_', $path);
            $ci = $ciName . ':' . $path;
            $lineFormat = $ci
                . ' free_space=' . $row->free_space
                . ' capacity=' . $row->capacity
                . ' '
                . time()
                . "\n";
            echo $lineFormat;
        }
    }

    /**
     * Replace with deprecation / sleep / exit
     */
    public function influxdbAction()
    {
        $vCenter = $this->getVCenter();
        // TODO: fetch baseUrl and dbName from vCenter settings
        try {
            $baseUrl = $this->params->getRequired('baseUrl');
            $dbName = $this->params->getRequired('db');
        } catch (\Exception $e) {
            $this->failFriendly('influxdb', $e->getMessage());
        }
        $loop = $this->loop();
        $streamer = new InfluxDbStreamer($vCenter, $loop);
        $interval = $this->params->get('interval', 60);
        $loop->addPeriodicTimer($interval, function () use ($streamer, $baseUrl, $dbName) {
            if ($streamer->isIdle()) {
                $streamer->streamTo($baseUrl, $dbName);
            } else {
                $this->logger->error('Skipping PerfdataStream, Streamer is still idle');
            }
        });
        $streamer->streamTo($baseUrl, $dbName);

        $loop->run();
    }

    protected function streamToInflux(LoopInterface $loop, VCenter $vCenter, $baseUrl, $dbName)
    {
    }

    protected function flushMetrics(&$queue)
    {
    }
}
