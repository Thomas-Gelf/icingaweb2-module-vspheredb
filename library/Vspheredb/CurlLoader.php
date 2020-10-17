<?php

namespace Icinga\Module\Vspheredb;

use Evenement\EventEmitter;
use Exception;
use Icinga\Exception\AuthenticationException;
use RuntimeException;

/**
 * Class CurlLoader
 *
 * This class fires all our SOAP requests and fetches WSDL files. This has been
 * implemented for debug reasons and to be able to work with various kinds of
 * HTTP or SOCKS proxies
 */
class CurlLoader extends EventEmitter
{
    /** @var resource */
    private $curl;

    /** @var string */
    private $host;

    /** @var int */
    private $port = 443;

    /** @var string */
    private $user;

    /** @var string */
    private $pass;

    /** @var bool */
    private $verifySslPeer = true;

    /** @var bool */
    private $verifySslHost = true;

    /** @var string */
    private $proxy;

    /** @var string */
    private $proxyType;

    /** @var string */
    private $proxyUser;

    /** @var string */
    private $proxyPass;

    /** @var array */
    private $proxyTypes = [
        'HTTP'   => CURLPROXY_HTTP,
        'SOCKS5' => CURLPROXY_SOCKS5,
    ];

    /** @var bool */
    private $persistCookies = false;

    /** @var string */
    private $cacheDir;

    /** @var string */
    private $cookieFile;

    /** @var array */
    private $cookies = array();

    private $lastResponse;

    private $tsRequestReadyToSend;

    private $tsRequestSent;

    private $tsRecvFirstResponseHeaderLine;

    private $tsRecvFullResponse;

    private $bytesSent;

    private $bytesReceived;

    /**
     * CurlLoader constructor.
     *
     * Please note that only the host is required. User and Pass might be
     * needed in case there is a reverse proxy asking for HTTP Auth sitting
     * in front of your vCenter
     *
     * @param $host
     * @param string $user
     * @param string $pass
     * @param string $cacheDir
     */
    public function __construct($host, $user = null, $pass = null, $cacheDir = null)
    {
        if ($cacheDir !== null) {
            $this->persistCookies = true;
            $this->cacheDir = $cacheDir;
        }

        if ($this->persistCookies) {
            $this->cookieFile = $this->cacheDir . "/cookie-$host";
            if (file_exists($this->cookieFile)) {
                $this->cookies[] = file_get_contents($this->cookieFile);
            }
        }
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
     * Set a specific port number
     *
     * @param int $port
     * @return $this
     */
    public function setPort($port)
    {
        $this->port = (int) $port;

        return $this;
    }

    /**
     * Use a proxy
     *
     * @param $url
     * @param string $type Either HTTP or SOCKS5
     * @return $this
     */
    public function setProxy($url, $type = 'HTTP')
    {
        $this->proxy = $url;
        if (is_int($type)) {
            $this->proxyType = $type;
        } else {
            $this->proxyType = $this->proxyTypes[$type];
        }
        return $this;
    }

    /**
     * @param $user
     * @param $pass
     * @return $this
     */
    public function setProxyAuth($user, $pass)
    {
        $this->proxyUser = $user;
        $this->proxyPass = $pass;
        return $this;
    }

    /**
     * @param bool $disable
     * @return $this
     */
    public function disableSslPeerVerification($disable = true)
    {
        $this->verifySslPeer = ! $disable;
        return $this;
    }

    /**
     * @param bool $disable
     * @return $this
     */
    public function disableSslHostVerification($disable = true)
    {
        $this->verifySslHost = ! $disable;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasCookie()
    {
        return ! empty($this->cookies);
    }

    /**
     * Discard our Cookie
     */
    public function forgetCookie()
    {
        $this->cookies = array();
        if ($this->persistCookies && file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    /**
     * @param $url
     * @return string
     */
    public function url($url)
    {
        return sprintf('https://%s:%d/%s', $this->host, $this->port, $url);
    }

    /**
     * @param $url
     * @param null $body
     * @return mixed
     * @throws AuthenticationException
     */
    public function get($url, $body = null)
    {
        return $this->request('get', $url, $body);
    }

    /**
     * @param $url
     * @param null $body
     * @param array $headers
     * @return mixed
     * @throws AuthenticationException
     */
    public function post($url, $body = null, $headers = array())
    {
        return $this->request('post', $url, $body, $headers);
    }

    public function getCookies()
    {
        return $this->cookies;
    }

    public function getUsername()
    {
        return $this->user;
    }

    /**
     * @param $method
     * @param $url
     * @param null $body
     * @param array $headers
     * @return mixed
     * @throws AuthenticationException
     */
    protected function request($method, $url, $body = null, $headers = array())
    {
        $method = strtoupper($method);
        $sendHeaders = ['Host: ' . $this->host];
        foreach ($this->cookies as $cookie) {
            $sendHeaders[] = 'Cookie: ' . $cookie;
        }
        foreach ($headers as $key => $val) {
            $sendHeaders[] = "$key: $val";
        }

        $this->debugRequest($method, $url, $sendHeaders, $body);
        $curl = $this->curl();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $sendHeaders,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => $this->verifySslPeer,
            CURLOPT_SSL_VERIFYHOST => $this->verifySslHost ? 2 : 0,
            CURLOPT_HEADERFUNCTION => [$this, 'processHeaderLine'],
            CURLOPT_PROGRESSFUNCTION => [$this, 'progress'],
            CURLOPT_ENCODING       => 'gzip',
            CURLOPT_TCP_NODELAY    => true,
            CURLOPT_NOPROGRESS     => false,
            CURLINFO_HEADER_OUT    => true,
            // disabled, see #161
            // CURLOPT_CERTINFO       => true,
        ];

        if ($this->user !== null) {
            $opts[CURLOPT_USERPWD] = sprintf('%s:%s', $this->user, $this->pass);
        }

        if ($this->port !== null) {
            $opts[CURLOPT_PORT] = $this->port;
        }

        if ($this->proxy) {
            $opts[CURLOPT_PROXY] = $this->proxy;
            $opts[CURLOPT_PROXYTYPE] = $this->proxyType;

            if ($this->proxyUser) {
                $opts[CURLOPT_PROXYUSERPWD] = sprintf(
                    '%s:%s',
                    $this->proxyUser,
                    $this->proxyPass
                );
            }
        }

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($curl, $opts);

        $this->tsRequestReadyToSend = \microtime(true);
        $this->tsRecvFirstResponseHeaderLine = null;
        $this->tsRecvFullResponse = null;
        $this->tsRequestSent = null;
        $this->bytesSent = 0;
        $this->bytesReceived = 0;
        $res = curl_exec($curl);

        if ($res === false) {
            throw new RuntimeException('CURL ERROR: ' . curl_error($curl));
        }
        $this->lastResponse = $res;
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->tsRecvFullResponse = \microtime(true);
        $this->debugResponse($res, $statusCode);

        if ($statusCode === 401) {
            throw new AuthenticationException(
                'Unable to authenticate, please check your API credentials'
            );
        }

        if ($statusCode >= 400) {
            if (preg_match('/NotAuthenticated/', $res)) {
                throw new AuthenticationException('Not authenticated');
            }

            if (strpos($res, '<soapenv:Fault>') !== false) {
                return $res;
            }
            // TODO: This should be transformed in a Soap error and deal with such
            throw new RuntimeException(
                "Got $statusCode: " . var_export($res, 1)
            );
        }

        return $res;
    }

    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * @param $method
     * @param $url
     * @param $headers
     * @param $body
     */
    protected function debugRequest($method, $url, $headers, $body)
    {
        if (true) {
            return;
        }

        echo "--> Sending Reqest\n";
        printf("%s %s\n", $method, $url);
        echo implode("\n", $headers);
        echo "\n\n";
        echo $body;
        echo "\n\n--\n";
    }

    protected function debugResponse($response, $statusCode)
    {
        if (true) {
            return;
        }

        echo "<-- Got Response\n";
        printf("Status: %d\n", $statusCode);
        echo "\n\n";
        echo $response;
        echo "\n\n--\n";
    }

    /**
     * Internal callback method, should not be used directly
     *
     * Returns the number of processed bytes and handles eventual Cookies
     *
     * @param $curl
     * @param $header
     * @return int
     */
    public function processHeaderLine($curl, $header)
    {
        if ($this->tsRecvFirstResponseHeaderLine === null) {
            $this->tsRecvFirstResponseHeaderLine = \microtime(true);
        }
        // echo "H: $header";
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) {
            return $len;
        }

        if (\strtolower($header[0]) === 'set-cookie') {
            $cookie = trim($header[1]);
            $this->emit('cookie', [$cookie]);
            if ($this->persistCookies) {
                file_put_contents($this->cookieFile, $cookie);
            }
            $this->cookies[] = $cookie;
        }

        return $len;
    }

    public function progress(
        $curl,
        $bytesToReceive,
        $bytesReceived,
        $bytesToSend,
        $bytesSent
    ) {
        if ($bytesSent !== $this->bytesSent) {
            // printf(
            //     "%s/%s Bytes sent\n",
            //     $bytesSent,
            //     $bytesToSend
            // );
            $this->bytesSent = $bytesSent;
        }
        if ($this->tsRequestSent === null && $bytesSent === $bytesToSend) {
            $this->tsRequestSent = \microtime(true);
        }
        if ($bytesReceived !== $this->bytesReceived) {
            // printf(
            //     "%s/%s Bytes received\n",
            //     $bytesReceived,
            //     $bytesToReceive
            // );
            $this->bytesReceived = $bytesReceived;
        }
    }

    public function getLastRequestDuration()
    {
        return $this->tsRequestSent - $this->tsRequestReadyToSend;
    }

    public function getTimeWaitingForFirstHeader()
    {
        return $this->tsRecvFirstResponseHeaderLine - $this->tsRequestSent;
    }

    public function getLastResponseDuration()
    {
        return $this->tsRecvFullResponse - $this->tsRecvFirstResponseHeaderLine;
    }

    public function getTotalDuration()
    {
        return $this->tsRecvFullResponse - $this->tsRequestReadyToSend;
    }

    /**
     * @throws Exception
     *
     * @return resource
     */
    protected function curl()
    {
        if ($this->curl === null) {
            $this->curl = curl_init(sprintf('https://%s:%d', $this->host, $this->port));
            if (! $this->curl) {
                throw new Exception('CURL INIT ERROR: ' . curl_error($this->curl));
            }
        }

        return $this->curl;
    }
}
