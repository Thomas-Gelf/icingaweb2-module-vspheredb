<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * A data object type that links multiple alarm expressions with AND operators
 */
class AndAlarmExpression extends AlarmExpression
{
    /**
     * List of alarm expressions that define the overall status of the alarm
     *
     * - The state of the alarm expression is gray if all subexpressions are gray.
     *   Otherwise, gray subexpressions are ignored.
     * - The state is red if all subexpressions are red.
     * - Otherwise, the state is yellow if all subexpressions are red or yellow.
     * - Otherwise, the state of the alarm expression is green.
     *
     * @var AlarmExpression[]
     */
    public $expression;
}
