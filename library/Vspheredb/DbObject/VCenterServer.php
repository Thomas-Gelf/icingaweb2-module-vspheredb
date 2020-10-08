<?php

namespace Icinga\Module\Vspheredb\DbObject;

class VCenterServer extends BaseDbObject
{
    protected $table = 'vcenter_server';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = [
        'id'              => null,
        'vcenter_id'      => null,
        'scheme'          => null,
        'host'            => null,
        'username'        => null,
        'password'        => null,
        'proxy_type'      => null,
        'proxy_address'   => null,
        'proxy_user'      => null,
        'proxy_pass'      => null,
        'ssl_verify_peer' => null,
        'ssl_verify_host' => null,
        'enabled'         => null,
    ];
}
