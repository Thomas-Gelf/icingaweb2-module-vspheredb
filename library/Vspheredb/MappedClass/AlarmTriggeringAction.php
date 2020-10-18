<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

/**
 * This data object type describes one or more triggering transitions and an
 * action to be done when an alarm is triggered.
 *
 * There are four triggering transitions; at least one of them must be provided.
 * A gray state is considered the same as a green state, for the purpose of
 * detecting transitions.
 */
class AlarmTriggeringAction extends AlarmAction
{
    /** @var Action The action to be done when the alarm is triggered */
    public $action;

    /**
     * Deprecated. As of vSphere API 4.0, use AlarmTriggeringActionTransitionSpec
     *
     * Flag to specify that the alarm should trigger on a transition from green
     * to yellow
     *
     * @var bool
     */
    public $green2yellow;

    /**
     * Deprecated. As of vSphere API 4.0, use AlarmTriggeringActionTransitionSpec
     *
     * Flag to specify that the alarm should trigger on a transition from red to yellow
     *
     * @var bool
     */
    public $red2yellow;

    /**
     * Indicates on which transitions this action executes and repeats. This is
     * optional only for backwards compatibility
     *
     * @var AlarmTriggeringActionTransitionSpec[]
     */
    public $transitionSpecs;

    /**
     * Deprecated. As of vSphere API 4.0, use AlarmTriggeringActionTransitionSpec
     *
     * Flag to specify that the alarm should trigger on a transition from yellow
     * to green
     *
     * @var bool
     */
    public $yellow2green;

    /**
     * Deprecated. As of vSphere API 4.0, use AlarmTriggeringActionTransitionSpec
     *
     * Flag to specify that the alarm should trigger on a transition from yellow
     * to red
     *
     * @var bool
     */
    public $yellow2red;
}
