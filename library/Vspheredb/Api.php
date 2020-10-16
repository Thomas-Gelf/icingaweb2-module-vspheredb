<?php

namespace Icinga\Module\Vspheredb;

use DateTime;
use Exception;
use http\Exception\RuntimeException;
use Icinga\Exception\AuthenticationException;
use Icinga\Module\Vspheredb\MappedClass\ApiClassMap;
use Icinga\Module\Vspheredb\MappedClass\RetrieveResult;
use Icinga\Module\Vspheredb\MappedClass\ServiceContent;
use Icinga\Module\Vspheredb\Polling\ServerInfo;
use Icinga\Module\Vspheredb\PropertySet\PropertySet;
use Icinga\Module\Vspheredb\SelectSet\SelectSet;
use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use SoapVar;

/**
 * Class Api
 *
 * This is your main entry point when working with this library
 */
class Api
{
    use LazyApiHelpers;
    use LoggerAwareTrait;

    /** @var CurlLoader */
    private $curl;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $user;

    /** @var string */
    private $pass;

    /** @var string */
    private $wsdlDir;

    /** @var string */
    private $cacheDir;

    /** @var SoapClient */
    private $soapClient;

    /**
     * Involved WSDL files
     *
     * We'll always fetch and store them in case they are not available. Pay
     * attention when modifying this list, we'll use the first one to start
     * with when connecting to the SOAP API
     *
     * @var array
     */
    private $wsdlFiles = [
        'vimService.wsdl',
        'vim.wsdl',
        'core-types.xsd',
        'query-types.xsd',
        'query-messagetypes.xsd',
        'reflect-types.xsd',
        'reflect-messagetypes.xsd',
        'vim-types.xsd',
        'vim-messagetypes.xsd',
    ];

    /**
     * Api constructor.
     *
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param int|null $port
     */
    protected function __construct($host, $user, $pass, $port = null)
    {
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
        $this->port = $port;
    }

    /**
     * @param ServerInfo $server
     * @return static
     */
    public static function forServer(ServerInfo $server, LoggerInterface $logger)
    {
        $host = $server->get('host');
        if (preg_match('/^(.+?):(\d{1,5})$/', $host, $match)) {
            $host = $match[1];
            $port = (int) $match[2];
        } else {
            $port = null;
        }

        $api = new static(
            $host,
            $server->get('username'),
            $server->get('password'),
            $port
        );
        $api->setLogger($logger);

        $curl = $api->curl();

        if ($port !== null) {
            $curl->setPort($port);
        }

        if ($type = $server->get('proxy_type')) {
            $curl->setProxy($server->get('proxy_address'), $type);

            if ($user = $server->get('proxy_user')) {
                $curl->setProxyAuth($user, $server->get('proxy_pass'));
            }
        }

        if ($server->get('scheme') === 'https') {
            if ($server->get('ssl_verify_peer') === 'n') {
                $curl->disableSslPeerVerification();
            }
            if ($server->get('ssl_verify_host') === 'n') {
                $curl->disableSslHostVerification();
            }
        }

        return $api;
    }

    /**
     * @return DateTime
     */
    public function getCurrentTime()
    {
        $result = $this->soapCall(
            'CurrentTime',
            $this->makeBaseServiceInstanceParam()
        );

        return new DateTime($result->returnval);
    }

    /**
     * @return CurlLoader
     */
    public function curl()
    {
        if ($this->curl === null) {
            $this->curl = new CurlLoader($this->host, null, null, $this->cacheDir());
            $this->curl->on('cookie', [$this, 'gotCookie']);
        }

        return $this->curl;
    }

    public function gotCookie($cookie)
    {
        // vmware_soap_session="d4a9b281fb7e587ef73a5097fe591bfbaa420ccd"; Path=/; HttpOnly; Secure;
        $this->logger->info('Got new session cookie from VCenter');
        return;
        $parts = explode(';', $cookie);
        list($name, $sid) = preg_split('/=/', $parts[0], 2);
        $sid = trim($sid, '"');

        $db->insert('vcenter_session', [
            'vcenter_uuid' => $this->getBinaryUuid(),
            'session_id' => hex2bin(sha1($cookie)),
            'session_cookie_string' => $cookie,
            'session_cookie_name' => $name,
            'scope' => 'Main Sync',
            'ts_created' => Util::currentTimestamp(),
            'ts_last_check' => Util::currentTimestamp(),
        ]);
    }

    /**
     * SOAP call wrapper
     *
     * Accepts the desired method name and optional arguments
     *
     * @param $method
     * @return mixed
     * @throws AuthenticationException
     * @throws \SoapFault
     */
    public function soapCall($method)
    {
        $arguments = func_get_args();
        array_shift($arguments);

        try {
            return $this->soapClient()->__soapCall($method, $arguments);
        } catch (AuthenticationException $e) {
            if ($method === 'Login' || $method === 'Logout') {
                throw $e;
            } else {
                try {
                    $this->logout();
                } catch (AuthenticationException $ae) {
                    // That's fine.
                }
                $this->login();

                return $this->soapClient()->__soapCall($method, $arguments);
            }
        }
    }

    /**
     * Builds our base url
     *
     * @return string
     */
    protected function makeLocation()
    {
        $host = $this->host;
        if ($this->port && $this->port !== 443) {
            $host .= ':' . $this->port;
        }

        return "https://$host/sdk";
    }

    /**
     * Lazy-instantiation of our SoapClient
     *
     * @return SoapClient
     * @throws \SoapFault
     */
    protected function soapClient()
    {
        if ($this->soapClient === null) {
            $this->prepareWsdl();
            $wsdlFile = $this->cacheDir() . '/' . $this->wsdlFiles[0];
            $features = SOAP_SINGLE_ELEMENT_ARRAYS | SOAP_USE_XSI_ARRAY_TYPE;
            $options = [
                'trace'              => true,
                'location'           => $this->makeLocation(),
                'exceptions'         => true,
                'connection_timeout' => 10,
                'classmap'           => ApiClassMap::getMap(),
                'features'           => $features,
                'cache_wsdl'         => WSDL_CACHE_NONE,
                'compression'        => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            ];

            try {
                $soap = new SoapClient($wsdlFile, $options);
            } catch (Exception $e) {
                // e.g.: SOAP-ERROR: Parsing Schema: can't import schema from '/tmp/[..]/vim-types.xsd
                $this->flushWsdlCache();

                throw $e;
            }
            $soap->setCurl($this->curl())->setLogger($this->logger);
            $this->soapClient = $soap;
        }

        return $this->soapClient;
    }

    /**
     * Make sure all our WSDL files are in place, fetch missing ones
     */
    protected function prepareWsdl()
    {
        $curl = $this->curl();
        $dir = $this->cacheDir();
        foreach ($this->wsdlFiles as $file) {
            if (! file_exists("$dir/$file")) {
                $this->logger->info("Loading sdk/$file");
                $wsdl = $curl->get($curl->url("sdk/$file"));
                file_put_contents("$dir/$file", $wsdl);
            }
        }
    }

    protected function flushWsdlCache()
    {
        $dir = $this->cacheDir();
        $this->logger->info("Flushing WSDL Cache in $dir");
        foreach ($this->wsdlFiles as $file) {
            if (file_exists("$dir/$file")) {
                unlink("$dir/$file");
            }
        }
    }

    /**
     * Really fetch the ServiceInstance
     *
     * @see getServiceInstance()
     *
     * @return ServiceContent
     */
    protected function retrieveServiceContent()
    {
        $result = $this->soapCall(
            'RetrieveServiceContent',
            $this->makeBaseServiceInstanceParam()
        );

        return $result->returnval;
    }

    protected function makeBaseServiceInstanceParam()
    {
        $param = [$this->makeVar('ServiceInstance', 'ServiceInstance')];

        return new SoapVar($param, SOAP_ENC_OBJECT);
    }

    /**
     * Log in to to API
     *
     * This will retrieve a session cookie and pass it with subsequent requests
     * @throws AuthenticationException
     * @throws \SoapFault
     */
    public function login()
    {
        if ($this->curl()->hasCookie()) {
            $this->logger->debug('Using existing Cookie');
            return $this;
        }

        $this->logger->debug(sprintf('Sending Login request to %s', $this->makeLocation()));
        $request = array(
            '_this'    => $this->getServiceInstance()->sessionManager,
            'userName' => $this->user,
            'password' => $this->pass,
        );

        $this->soapCall('Login', $request);

        return $this;
    }

    /**
     * Logout, destroy our session
     */
    public function logout()
    {
        $request = array(
            // ManagedObjectReference: _ = SessionManager, type = SessionManager
            '_this' => $this->getServiceInstance()->sessionManager
        );
        try {
            $this->soapCall('Logout', $request);
        } catch (Exception $e) {
            // Do nothing
        }
        $this->curl()->forgetCookie();
    }

    /**
     * @param SelectSet $selectSet
     * @param null $base
     * @return array
     * @throws AuthenticationException
     */
    protected function makeObjectSet(SelectSet $selectSet, $base = null)
    {
        /** @var ManagedObjectReference $base _ = group-d1, type = Folder */
        if ($base === null) {
            $base = $this->getServiceInstance()->rootFolder;
        }

        return [
            'obj'   => $base,
            'skip'  => false,
            'selectSet' => $selectSet->toArray(),
        ];
    }

    /**
     * @param PropertySet $propSet
     * @param SelectSet $selectSet
     * @return array
     * @throws AuthenticationException
     */
    public function makePropertyFilterSpec(PropertySet $propSet, SelectSet $selectSet)
    {
        return [
            'propSet'   => $propSet->toArray(),
            'objectSet' => $this->makeObjectSet($selectSet)
        ];
    }

    /**
     * TODO: Can be used for mass requests once we deal with the token in the RetrieveResult
     *
     * @param ManagedObjectReference $object
     * @param array|null $properties
     * @return RetrieveResult
     */
    public function retrieveProperties(ManagedObjectReference $object, $properties = null)
    {
        $specSet = [
            '_this'   => $this->getServiceInstance()->propertyCollector,
            'specSet' => [
                'propSet' => [
                    'type'    => $object->type,
                    'all'     => $properties === null,
                    'pathSet' => $properties
                ],
                'objectSet' => [
                    'obj'  => $object,
                    'skip' => false
                ]
            ],
            'options' => null
        ];

        $result = $this->soapClient->__call('RetrievePropertiesEx', [$specSet]);
        if (! isset($result->returnval)) {
            // Should not be reached, as Authentication or Connection Errors are thrown beforehand
            throw new RuntimeException('Got no returnval for RetrievePropertiesEx');
        }
        assert($result->returnval instanceof RetrieveResult);

        return $result->returnval;
    }

    /**
     * @return string
     */
    protected function cacheDir()
    {
        if ($this->cacheDir === null) {
            $this->cacheDir = SafeCacheDir::getSubDirectory($this->host);
        }

        return $this->cacheDir;
    }

    /**
     * Just a helper method
     *
     * @return SoapVar
     */
    public function makeVar($key, $val)
    {
        return new SoapVar($val, XSD_STRING, $key, null, 'ns1:_this');
    }
}
