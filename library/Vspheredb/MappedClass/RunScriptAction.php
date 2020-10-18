<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * This data object type specifies a script that is triggered by an alarm. You
 * can use any elements of the ActionParameter enumerated list as part of your
 * script to provide information available at runtime.
 */
class RunScriptAction extends Action
{
    /**
     * The fully-qualified path to a shell script that runs on the VirtualCenter
     * server as a result of an alarm
     *
     * @var string
     */
    public $script;
}
