<?php

namespace Icinga\Module\Vspheredb;

use Icinga\Util\Format;
use Psr\Log\LoggerAwareTrait;
use SoapClient as PhpSoapClient;

/**
 * Class SoapClient
 *
 * This implementation wraps __doRequest and uses CURL for it's requests to
 * work around issues with SOCKS proxies and similar
 *
 * Use it just like the legacy PHP SoapClient, but inject a CurlLoader instance
 * in case you want to benefit from it's features. Only drawback right now: does
 * not reflect all error conditions
 */
class SoapClient extends PhpSoapClient
{
    use LoggerAwareTrait;

    /** @var CurlLoader */
    protected $curl;

    protected $dumpRawData = false;

    /**
     * @param CurlLoader $curl
     * @return $this
     */
    public function setCurl(CurlLoader $curl)
    {
        $this->curl = $curl;
        return $this;
    }

    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        if ($this->curl === null) {
            return parent::__doRequest($request, $location, $action, $version, $one_way = 0);
        } else {
            return $this->doCurlRequest($request, $location, $action, $version, $one_way = 0);
        }
    }

    /**
     * Delegates the request to our CurlLoader
     *
     * @param $request
     * @param $location
     * @param $action
     * @param $version
     * @param int $one_way
     * @return mixed
     * @throws \Icinga\Exception\AuthenticationException
     * @see PhpSoapClient::__doRequest()
     */
    public function doCurlRequest($request, $location, $action, $version, $one_way = 0)
    {
        $headers = [
            'User-Agent'   => 'Icinga vSphere Client/1.1',
            'Content-Type' => 'text/xml; charset=utf-8',
            'Connection'   => 'Keep-Alive',
            'Keep-Alive'   => '300',
            'SOAPAction'   => $action,
        ];
        // TODO: we might want to collect summaries for sent/received bytes
        // Logger::debug('SOAPClient: ready to send %s', Format::bytes(strlen($request)));
        $result = $this->curl->post($location, $request, $headers);
        if ($this->logger) {
            $this->logger->debug(sprintf(
                'SOAPClient: sent %s in %.02fms, waited %.02fms, got %s response in %0.2fms. Total duration: %.02fms',
                Format::bytes(strlen($request)),
                $this->curl->getLastRequestDuration() * 1000,
                $this->curl->getTimeWaitingForFirstHeader() * 1000,
                Format::bytes(strlen($result)),
                $this->curl->getLastResponseDuration() * 1000,
                $this->curl->getTotalDuration() * 1000
            ));
        }

        if ($this->dumpRawData) {
            echo "$request\n====\n$result\n";
        }

        return $result;
    }
}
