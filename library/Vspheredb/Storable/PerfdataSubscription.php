<?php

namespace Icinga\Module\Vspheredb\Storable;

use gipfl\ZfDbStore\DbStorable;
use gipfl\ZfDbStore\DbStorableInterface;

class PerfdataSubscription implements DbStorableInterface
{
    use DbStorable;

    protected $tableName = 'vcenter_perfdata_subscription';

    protected $keyProperty = 'id';

    protected $autoIncKeyName = 'id';

    protected $defaultProperties = [
        'id'               => null,
        'implementation'   => null,
        'settings'         => null,
        'enabled'          => null,
        'vcenter_uuid'     => null,
        'performance_sets' => null,
    ];
}
