<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * An alarm expression that uses the running state of either a virtual machine
 * or a host as the condition that triggers the alarm. Base type.
 *
 * There are two alarm operands: yellow and red. At least one of them must be
 * set. The value of the alarm expression is determined as follows:
 *
 * - If the red state is set but the yellow state is not: the expression is red
 *   when the state operand matches (isEqual operator) or does not match
 *   (isUnequal operator) the state of the managed entity. The expression is
 *   green otherwise.
 * - If yellow is set but red is not: the expression is yellow when the state
 *   operand matches (isEqual) or does not match (isUnequal) the state of the
 *   managed entity. The expression is green otherwise.
 * - If both yellow and red are set, the value of the expression is red when the
 *   red state operand matches (isEqual) or does not match (isUnequal) the state
 *   of the managed entity. Otherwise, the expression is yellow when the yellow
 *   state operand matches (isEqual) or does not match (isUnequal) the state of
 *   the managed entity. Otherwise, the expression is green.
 */
class StateAlarmExpression extends AlarmExpression
{
    /**
     * The operation to be tested on the target state
     *
     * Enum - StateAlarmOperator: The operation on the target state
     *
     * - isEqual    Test if the target state matches the given red or yellow states
     * - isUnequal  Test if the target state does not match the given red or yellow states
     *
     * @var string StateAlarmOperator
     */
    public $operator;

    /**
     * Whether or not to test for a red condition. If this property is not set,
     * do not calculate red status.
     *
     * @var string
     */
    public $red;

    /**
     * Path of the state property.
     *
     * The supported values:
     *
     * - for vim.VirtualMachine type: runtime.powerState or summary.quickStats.guestHeartbeatStatus
     * - for vim.HostSystem type: runtime.connectionState
     *
     * @var string
     */
    public $statePath;

    /** @var string Name of the object type containing the property */
    public $type;

    /**
     * Whether or not to test for a yellow condition. If this property is not
     * set, do not calculate yellow status.
     *
     * @var string
     */
    public $yellow;
}
