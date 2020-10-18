<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 *  This data object type defines a named argument for an operation
 */
class MethodActionArgument extends DynamicData
{
    /** @var mixed anyType - The value of the argument */
    public $value;
}
