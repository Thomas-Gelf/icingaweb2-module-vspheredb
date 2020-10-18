<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

/**
 * Information about the alarm's state.
 */
class AlarmInfo
{
    /**
     * The alarm object
     *
     * @var ManagedObjectReference to a Alarm
     */
    public $alarm;

    /** @var int|null The event ID that records the alarm creation */
    public $creationEventId;

    /**
     * The entity on which the alarm is registered
     *
     * @var ManagedObjectReference to a ManagedEntity
     */
    public $entity;

    /** @var string The unique key */
    public $key;

    /** @var string dateTime The time the alarm was created or modified */
    public $lastModifiedTime;

    /** @var string User name that modified the alarm most recently */
    public $lastModifiedUser;
}
