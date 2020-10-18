<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * An alarm expression that uses a metric as the condition that triggers an alarm.
 * Base type.
 *
 * There are two alarm operands: yellow and red. At least one of them must be set.
 * The value of the alarm expression is determined as follows:
 *
 * - If the host is not connected, the host metric expression is gray.
 * - If the vm is not connected, the vm metric expression is gray.
 * - If red is set but yellow is not, the expression is red when the metric is
 *   over (isAbove operator) or under (isBelow operator) the red value. Otherwise,
 *   the expression is green.
 * - If yellow is set but red is not, the expression is yellow when the metric
 *   is over (isAbove) or under (isBelow) the yellow value. Otherwise, the
 *   expression is green.
 * - If both yellow and red are set, the value of the expression is red when the
 *   metric is over (isAbove) or under (isBelow) the red value. Otherwise, the
 *   expression is yellow when the metric is over (isAbove) or under (isBelow)
 *   the yellow value. Otherwise, the expression is green.
 */
class MetricAlarmExpression extends AlarmExpression
{
    /**
     * The operation to be tested on the target state
     *
     * Enum - MetricAlarmOperator: The operation on the target metric item
     *
     * - isAbove Test if the target metric item is above the given red or yellow values
     * - isBelow Test if the target metric item is below the given red or yellow values
     *
     * @var string MetricAlarmOperator
     */
    public $operator;

    /**
     * Whether or not to test for a red condition. If not set, do not calculate
     * red status. If set, it contains the threshold value that triggers red status
     *
     * @var string
     */
    public $red;

    /**
     * Time interval in seconds for which the red condition must be true before
     * the red status is triggered. If unset, the red status is triggered
     * immediately when the red condition becomes true.
     *
     * @var int|null
     */
    public $redInterval;

    /** @var string  Name of the object type containing the property */
    public $type;

    /**
     * Whether or not to test for a yellow condition. If not set, do not
     * calculate yellow status. If set, it contains the threshold value that
     * triggers yellow status
     *
     * @var string
     */
    public $yellow;

    /**
     * Time interval in seconds for which the yellow condition must be true
     * before the yellow status is triggered. If unset, the yellow status is
     * triggered immediately when the yellow condition becomes true.
     *
     * @var int|null
     */
    public $yellowInterval;
}
