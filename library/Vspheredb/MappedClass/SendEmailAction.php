<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * This data object type defines an email that is triggered by an alarm. You
 * can use any elements of the ActionParameter enumerated list as part of your
 * strings to provide information available at runtime.
 */
class SendEmailAction extends Action
{
    /** @var string Content of the email notification */
    public $body;

    /** @var string A comma-separated list of addresses that are cc'ed on the email notification */
    public $ccList;

    /** @var string Subject of the email notification */
    public $subject;

    /** @var string A comma-separated list of addresses to which the email notification is sent */
    public $toList;
}
