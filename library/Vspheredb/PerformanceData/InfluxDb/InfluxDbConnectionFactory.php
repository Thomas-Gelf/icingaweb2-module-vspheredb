<?php

namespace Icinga\Module\Vspheredb\PerformanceData\InfluxDb;

use React\EventLoop\LoopInterface;
use React\Promise\Promise;

abstract class InfluxDbConnectionFactory
{
    /**
     * AsyncInfluxDbWriter constructor.
     * @param LoopInterface $loop
     * @param $baseUrl string InfluxDB base URL
     * @param string|null $username
     * @param string|null $password
     * @return Promise
     */
    public static function create(LoopInterface $loop, $baseUrl, $username = null, $password = null)
    {
        $v1 = new InfluxDbConnectionV1($loop, $baseUrl);
        return $v1->getVersion()->then(function ($version) use ($baseUrl, $username, $password, $loop, $v1) {
            if ($version === null) {
                $v2 = new InfluxDbConnectionV2($loop, $baseUrl, $username, $password);
                return $v2->getVersion()->then(function ($version) use ($v2) {
                    if ($version === null) {
                        throw new \RuntimeException('Unable to detect InfluxDb version');
                    } else {
                        return $v2;
                    }
                });
            } else {
                return $v1;
            }
        });
    }
}
