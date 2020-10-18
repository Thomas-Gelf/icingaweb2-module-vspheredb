<?php

namespace Icinga\Module\Vspheredb\Api;

use Icinga\Module\Vspheredb\CurlLoader;
use Psr\Log\LoggerInterface;

class WsdlLoader
{
    /**
     * @var CurlLoader
     */
    protected $curl;

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

    protected $cacheDir;

    /** @var LoggerInterface */
    protected $logger;

    /**
     * WsdlLoader constructor.
     * @param string $cacheDir
     * @param CurlLoader $curl
     * @param LoggerInterface $logger
     */
    public function __construct($cacheDir, CurlLoader $curl, LoggerInterface $logger)
    {
        $this->cacheDir = $cacheDir;
        $this->curl = $curl;
        $this->logger = $logger;
    }

    public function getInitialWsdlFile()
    {
        return $this->cacheDir . '/' . $this->wsdlFiles[0];
    }

    /**
     * Make sure all our WSDL files are in place, fetch missing ones
     */
    public function prepareWsdl()
    {
        $curl = $this->curl;
        $dir = $this->cacheDir;
        foreach ($this->wsdlFiles as $file) {
            if (! file_exists("$dir/$file")) {
                $this->logger->info("Loading sdk/$file");
                $wsdl = $curl->get($curl->url("sdk/$file"));
                file_put_contents("$dir/$file", $wsdl);
            }
        }
    }

    public function flushWsdlCache()
    {
        $dir = $this->cacheDir;
        $this->logger->info("Flushing WSDL Cache in $dir");
        foreach ($this->wsdlFiles as $file) {
            if (file_exists("$dir/$file")) {
                unlink("$dir/$file");
            }
        }
    }
}
