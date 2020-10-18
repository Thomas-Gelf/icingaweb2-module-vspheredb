<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * Encapsulates Comparison of an event's attribute to a value
 */
class EventAlarmExpressionComparison extends DynamicData
{
    /** @var string The attribute of the event to compare */
    public $attributeName;

    /** @var string An operator from the list above */
    public $operator;

    /** @var string The value to compare against */
    public $value;
}
