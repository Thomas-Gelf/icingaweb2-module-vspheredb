<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 *This managed object type defines an alarm that is triggered and an action
 * that occurs due to the triggered alarm when certain conditions are met on
 * a specific ManagedEntity object.
 */
class Alarm extends ExtensibleManagedObject
{
    /**
     *  Information about this alarm.
     *
     * @var AlarmInfo
     */
    public $info;
}
