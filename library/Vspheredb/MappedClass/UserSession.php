<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * UserSession
 *
 * Information about a current user session
 */
class UserSession
{
    /** @var int (long) Number of API invocations since the session started */
    public $callCount;

    /** @var bool Whether or not this session belongs to a VC Extension */
    public $extensionSession;

    /** @var string The full name of the user, if available */
    public $fullName;

    /** @var string The client identity. It could be IP address, or pipe name depended on client binding */
    public $ipAddress;

    /** @var string A unique identifier for this session, also known as the session ID */
    public $key;

    /** @var string (dateTime) Timestamp when the user last executed a command */
    public $lastActiveTime;

    /** @var string The locale for the session used for data formatting and preferred for messages */
    public $locale;

    /** @var string (dateTime) Timestamp when the user last logged on to the server */
    public $loginTime;

    /**
     * The locale used for messages for the session. If there are no localized
     * messages for the user-specified locale, then the server determines this
     * locale
     *
     * @var string
     */
    public $messageLocale;

    /** @var string The name of user agent or application */
    public $userAgent;

    /** @var string The user name represented by this session */
    public $userName;
}
