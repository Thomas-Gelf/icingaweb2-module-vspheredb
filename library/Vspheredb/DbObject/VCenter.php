<?php

namespace Icinga\Module\Vspheredb\DbObject;

use Icinga\Module\Vspheredb\Api;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\Util;
use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class VCenter extends BaseDbObject
{
    protected $table = 'vcenter';

    protected $keyName = 'instance_uuid';

    protected $autoincKeyName = 'id';

    /** @var Api */
    private $api;

    protected $defaultProperties = [
        'id'                      => null,
        'instance_uuid'           => null,
        'trust_store_id'          => null,
        'name'                    => null,
        'api_name'                => null,
        'version'                 => null,
        'os_type'                 => null,
        'api_type'                => null,
        'api_version'             => null,
        'build'                   => null,
        'vendor'                  => null,
        'product_line'            => null,
        'license_product_name'    => null,
        'license_product_version' => null,
        'locale_build'            => null,
        'locale_version'          => null,
    ];

    protected $propertyMap = [
        'instanceUuid'          => 'instance_uuid',
        'name'                  => 'api_name',
        'version'               => 'version',
        'osType'                => 'os_type',
        'apiType'               => 'api_type',
        'apiVersion'            => 'api_version',
        'build'                 => 'build',
        'vendor'                => 'vendor',
        'productLineId'         => 'product_line',
        'licenseProductName'    => 'license_product_name',
        'licenseProductVersion' => 'license_product_version',
        'localeBuild'           => 'locale_build',
        'localeVersion'         => 'locale_version',
    ];

    public function getFullName()
    {
        return sprintf(
            '%s %s build-%s',
            \preg_replace('/^VMware /', '', $this->get('api_name')),
            $this->get('version'),
            $this->get('build')
        );
    }

    // TODO: Settle with one or the other
    public function getUuid()
    {
        return $this->get('instance_uuid');
    }

    public static function loadWithHexUuid($uuid, Db $db)
    {
        return static::load(\hex2bin(\str_replace('-', '', $uuid)), $db);
    }

    /**
     * @param LoggerInterface|null $logger
     * @return Api
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getApi(LoggerInterface $logger = null)
    {
        if ($this->api === null) {
            if ($logger === null) {
                $logger = new NullLogger();
            }
            $this->api = Api::forServer($this->getFirstServer(), $logger);
        }

        return $this->api;
    }

    /**
     * @return VCenterServer
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getFirstServer()
    {
        $db = $this->getConnection()->getDbAdapter();
        $serverId = $db->fetchOne(
            $db->select()
                ->from('vcenter_server')
                ->where('vcenter_id = ?', $this->get('id'))
                ->limit(1)
        );

        return VCenterServer::loadWithAutoIncId($serverId, $this->getConnection());
    }

    public function makeBinaryGlobalUuid($moRefId)
    {
        if ($moRefId instanceof ManagedObjectReference) {
            return sha1($this->get('uuid') . $moRefId->_, true);
        } else {
            return sha1($this->get('uuid') . $moRefId, true);
        }
    }

    /**
     * @param $value
     * @codingStandardsIgnoreStart
     */
    public function setInstance_uuid($value)
    {
        // @codingStandardsIgnoreEnd
        if (strlen($value) > 16) {
            $value = Util::uuidToBin($value);
        }

        $this->reallySet('instance_uuid', $value);
    }

    /**
     * Just to help the IDE
     *
     * @return Db
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
