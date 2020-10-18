<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 *  Parameters for alarm creation
 */
class AlarmSpec
{
    /** @var AlarmAction Action to perform when the alarm is triggered */
    public $action;

    /**
     * Frequency in seconds, which specifies how often appropriate actions should
     * repeat when an alarm does not change state
     *
     * @var int
     */
    public $actionFrequency;

    /** @var string Description of the alarm */
    public $description;

    /** @var bool Flag to indicate whether or not the alarm is enabled or disabled */
    public $enabled;

    /** @var AlarmExpression Top-level alarm expression that defines trigger conditions */
    public $expression;

    /** @var string Name of the alarm */
    public $name;

    /** @var AlarmSetting Tolerance and maximum frequency settings */
    public $setting;

    /**
     * System name of the alarm.
     *
     * This is set only for predefined Alarms - i.e. Alarms created by the
     * server or extensions automatically. After creation this value cannot be
     * modified. User-created Alarms do not have a systemName at all.
     *
     * The purpose of this field is to identify system-created Alarms reliably,
     * even if they are edited by users.
     *
     * When creating Alarms with systemName, the systemName and the name of the
     * alarm should be equal.
     *
     * When reconfiguring an Alarm with systemName, the same systemName should
     * be passed in the new AlarmSpec. Renaming Alarms with systemName is not
     * allowed, i.e. when reconfiguring, the name passed in the new AlarmSpec
     * should be equal to either the systemName or its localized version (the
     * current name in the Alarm's info).
     *
     * @var string
     */
    public $systemName;
}
