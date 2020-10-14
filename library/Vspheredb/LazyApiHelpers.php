<?php

namespace Icinga\Module\Vspheredb;

use Icinga\Exception\AuthenticationException;
use RuntimeException;

trait LazyApiHelpers
{
    /** @var object */
    private $serviceInstance;

    /** @var EventManager */
    private $eventManager;

    /** @var PerfManager */
    private $perfManager;

    /** @var CustomFieldsManager */
    private $customFieldsManager;

    /** @var PropertyCollector */
    private $propertyCollector;

    /** @var string */
    private $binaryUuid;

    /**
     * @return mixed
     * @throws AuthenticationException
     */
    public function getAbout()
    {
        return $this->getServiceInstance()->about;
    }

    /**
     * @return EventManager
     * @throws AuthenticationException
     */
    public function eventManager()
    {
        if ($this->eventManager === null) {
            /** @var Api $this */
            $this->eventManager = new EventManager($this, $this->logger);
        }

        return $this->eventManager;
    }

    /**
     * @return PerfManager
     */
    public function perfManager()
    {
        if ($this->perfManager === null) {
            /** @var Api $this */
            $this->perfManager = new PerfManager($this, $this->logger);
        }

        return $this->perfManager;
    }

    /**
     * @return CustomFieldsManager
     */
    public function customFieldsManager()
    {
        if ($this->customFieldsManager === null) {
            /** @var Api $this */
            $this->customFieldsManager = new CustomFieldsManager($this);
        }

        return $this->customFieldsManager;
    }

    public function hasCustomFieldsManager()
    {
        return $this->customFieldsManager !== null
            || isset($this->getServiceInstance()->customFieldsManager);
    }

    /**
     * @return PropertyCollector
     */
    public function propertyCollector()
    {
        if ($this->propertyCollector === null) {
            /** @var Api $this */
            $this->propertyCollector = new PropertyCollector($this);
        }

        return $this->propertyCollector;
    }

    /**
     * A ServiceInstance, lazy-loaded only once
     *
     * This is a stdClass for now, might become a dedicated class
     *
     * @return object
     */
    public function getServiceInstance()
    {
        if ($this->serviceInstance === null) {
            $this->serviceInstance = $this->fetchServiceInstance();
        }

        return $this->serviceInstance;
    }

    /**
     * Just a custom version string
     *
     * Please to not make assumptions based on the format of this string, it
     * is for visualization purposes only and might change without pre-announcement
     *
     * @return string
     */
    public function getVersionString()
    {
        $about = $this->getServiceInstance()->about;

        return sprintf(
            "%s on %s, api=%s (%s)\n",
            $about->fullName,
            $about->osType,
            $about->apiType,
            $about->licenseProductName
        );
    }

    /**
     * @return string
     * @throws AuthenticationException
     */
    public function getBinaryUuid()
    {
        if ($this->binaryUuid === null) {
            $about = $this->getAbout();

            if ($about->apiType === 'VirtualCenter') {
                $this->binaryUuid = Util::uuidToBin($about->instanceUuid);
            } elseif ($about->apiType === 'HostAgent') {
                // TODO: We MUST change this. bios uuid?!
                $this->binaryUuid = Util::uuidToBin(md5($this->host));
            } else {
                throw new RuntimeException(
                    'Unsupported API type "%s"',
                    $about->apiType
                );
            }
        }

        return $this->binaryUuid;
    }

    /**
     * @param $moRefId
     * @return string
     * @throws AuthenticationException
     */
    public function makeGlobalUuid($moRefId)
    {
        return sha1($this->getBinaryUuid() . $moRefId, true);
    }

    public function __destruct()
    {
        unset($this->eventManager);
        unset($this->perfManager);
        unset($this->propertyCollector);
        unset($this->serviceInstance);
    }
}
