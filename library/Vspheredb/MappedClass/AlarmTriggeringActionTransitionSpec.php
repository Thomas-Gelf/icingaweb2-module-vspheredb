<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * Specification indicating which on transitions this action fires. The existence
 * of a Spec indicates that this action fires on transitions from that Spec's
 * startState to finalState.
 *
 * There are only four acceptable {startState, finalState} pairs: {green, yellow},
 * {yellow, red}, {red, yellow} and {yellow, green}. At least one of these pairs
 * must be specified. Any deviation from the above will render the enclosing
 * AlarmSpec invalid.
 */
class AlarmTriggeringActionTransitionSpec extends DynamicData
{
    /**
     * The state to which the alarm must transition for the action to fire.
     * Valid choices are red, yellow, and green.
     *
     * @var string ManagedEntityStatus
     */
    public $finalState;

    /**
     * Whether or not the action repeats, as per the actionFrequency defined in
     * the enclosing Alarm
     *
     * @var bool
     */
    public $repeats;

    /**
     * The state from which the alarm must transition for the action to fire.
     * Valid choices are red, yellow and green.
     *
     * @var string ManagedEntityStatus
     */
    public $startState;
}
