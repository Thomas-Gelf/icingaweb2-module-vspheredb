<?php

namespace Icinga\Module\Vspheredb\Sync;

use Icinga\Module\Vspheredb\Api;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;
use Psr\Log\LoggerInterface;

class VCenterInitialization
{
    public static function initializeFromServer(VCenterServer $server, LoggerInterface $logger)
    {
        /** @var Db $connection */
        $connection = $server->getConnection();
        $vCenter = static::fromApi(
            Api::forServer($server, $logger),
            $connection,
            $logger,
            $server->get('host')
        );
        $server->set('vcenter_id', $vCenter->get('id'));
        $server->store();
    }

    /**
     * Hint: this also updates the vCenter.
     *
     * @param Api $api
     * @param Db $db
     * @param LoggerInterface $logger
     * @param $name
     * @return VCenter
     * @throws \Icinga\Exception\AuthenticationException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Vspheredb\Exception\DuplicateKeyException
     */
    public static function fromApi(Api $api, Db $db, LoggerInterface $logger, $name)
    {
        $about = $api->getAbout();
        $uuid = $api->getBinaryUuid();
        if (VCenter::exists($uuid, $db)) {
            $vCenter = VCenter::load($uuid, $db);
        } else {
            $vCenter = VCenter::create([], $db);
        }

        // Workaround for ESXi, about has no instanceUuid
        $about->instanceUuid = $uuid;
        $vCenter->setMapped($about, $vCenter);
        $vCenter->set('name', $name);

        if ($vCenter->hasBeenModified()) {
            if ($vCenter->hasBeenLoadedFromDb()) {
                $logger->info('vCenter has been modified');
            } else {
                $logger->info('vCenter has been created');
            }

            $vCenter->store();
        } else {
            $logger->info("vCenter hasn't been changed");
        }

        return $vCenter;
    }
}
