<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * This data object type defines an operation and its arguments, invoked on a
 * particular entity.
 */
class MethodAction extends Action
{
    /** @var MethodActionArgument[] An array consisting of the arguments for the operation */
    public $argument;

    /** @var string  Name of the operation */
    public $name;
}
