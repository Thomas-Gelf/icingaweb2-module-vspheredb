<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

/**
 * Information about the alarm's state.
 */
class AlarmState
{
    /**
     * Flag to indicate if the alarm's actions have been acknowledged for the
     * associated ManagedEntity
     *
     * @var bool|null
     */
    public $acknowledged;

    /**
     * The user who acknowledged this triggering. If the triggering has not been
     * acknowledged, then the value is not valid
     *
     * @var string|null
     */
    public $acknowledgedByUser;

    /**
     * The time this triggering was acknowledged. If the triggering has not
     * been acknowledged, then the value is not valid
     *
     * @var string dateTime
     */
    public $acknowledgedTime;

    /**
     * Alarm object from which the AlarmState object is instantiated
     *
     * @var ManagedObjectReference to a Alarm
     */
    public $alarm;

    /**
     * Entity on which the alarm is instantiated
     *
     * @var ManagedObjectReference to a ManagedEntity
     */
    public $entity;

    /**
     * Contains the key of the event that has triggered the alarm. The value is
     * set only for event based alarms. The value is not set for gray or manually
     * reset alarms (via vim.AlarmManager.setAlarmStatus)
     *
     * Since vSphere API 6.0
     *
     * @var int|null
     */
    public $eventKey;

    /**
     * Unique key that identifies the alarm
     *
     * @var string
     */
    public $key;

    /**
     * Overall status of the alarm object. This is the value of the alarm's
     * top-level expression. vSphere Servers might not generate property collector
     * update notifications for this property. To obtain the latest value of the
     * property, you can use PropertyCollector methods RetrievePropertiesEx or
     * WaitForUpdatesEx. If you use the PropertyCollector.WaitForUpdatesEx method,
     * specify an empty string for the version parameter. Since this property is
     * on a DataObject, an update returned by WaitForUpdatesEx may contain values
     * for this property when some other property on the DataObject changes. If
     * this update is a result of a call to WaitForUpdatesEx with a non-empty
     * version parameter, the value for this property may not be current.
     *
     * @var string ManagedEntityStatus
     */
    public $overallStatus;

    /**
     * Time the alarm triggered
     *
     * @var string dateTime
     */
    public $time;
}
