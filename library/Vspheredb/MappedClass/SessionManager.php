<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * SessionManager
 *
 * This managed object type includes methods for logging on and logging off
 * clients, determining which clients are currently logged on, and forcing
 * clients to log off.
 */
class SessionManager
{
    /**
     * This property contains information about the client's current session. If
     * the client is not logged on, the value is null.
     *
     * RequiredPrivilege: System.Anonymous
     *
     * @var UserSession|null
     */
    public $currentSession;

    /**
     * This is the default server locale.
     *
     * RequiredPrivilege: System.Anonymous
     *
     * @var string
     */
    public $defaultLocale;

    /**
     * The system global message from the server
     *
     * RequiredPrivilege: System.View
     *
     * @var string
     */
    public $message;

    /**
     * Provides the list of locales for which the server has localized messages
     *
     * RequiredPrivilege: System.Anonymous
     *
     * @var array|null
     */
    public $messageLocaleList;

    /**
     * The list of currently active sessions
     *
     * RequiredPrivilege: Sessions.TerminateSession
     *
     * @var UserSession[]|null
     */
    public $sessionList;

    /**
     * Provides the list of locales that the server supports. Listing a locale
     * ensures that some standardized information such as dates appear in the
     * appropriate format. Other localized information, such as error messages,
     * are displayed, if available. If localized information is not available,
     * the message is returned using the system locale.
     *
     * RequiredPrivilege: System.Anonymous
     *
     * @var array|null
     */
    public $supportedLocaleList;
}
