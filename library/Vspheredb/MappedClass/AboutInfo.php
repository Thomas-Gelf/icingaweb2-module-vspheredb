<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * AboutInfo
 *
 * This data object type describes system information including the name, type,
 * version, and build number.
 */
class AboutInfo
{
    /**
     * Indicates whether or not the service instance represents a standalone
     * host. If the service instance represents a standalone host, then the
     * physical inventory for that service instance is fixed to that single
     * host. VirtualCenter server provides additional features over single
     * hosts. For example, VirtualCenter offers multi-host management.
     *
     * Examples of values are:
     *
     * - "VirtualCenter" - For a VirtualCenter instance.
     * - "HostAgent" - For host agent on an ESX Server or VMware Server host.
     *
     * @var string
     */
    public $apiType;

    /** @var string dot-separated string. For example, "1.0.0". */
    public $apiVersion;

    /**
     * Build string for the server on which this call is made. For example,
     * x.y.z-num. This string does not apply to the API.
     *
     * @var string
     */
    public $build;

    /** @var string The complete product name, including the version information. */
    public $fullName;

    /** @var string|null A globally unique identifier associated with this service instance */
    public $instanceUuid;

    /** @var string|null The license product name */
    public $licenseProductName;

    /** @var string|null The license product version */
    public $licenseProductVersion;

    /**
     * Build number for the current session's locale. Typically, this is a small
     * number reflecting a localization change from the normal product build.
     *
     * @var string|null
     */
    public $localeBuild;

    /** @var string|null Version of the message catalog for the current session's locale */
    public $localeVersion;

    /** @var string Short form of the product name */
    public $name;

    /** @var
     * Operating system type and architecture
     *
     * Examples of values are:
     * - "win32-x86" - For x86-based Windows systems.
     * - "linux-x86" - For x86-based Linux systems.
     * - "vmnix-x86" - For the x86 ESX Server microkernel.
     *
     * string
     */
    public $osType;

    /**
     * The product ID is a unique identifier for a product line.
     *
     * Examples of values are:
     *
     * - "gsx" - For the VMware Server product.
     * - "esx" - For the ESX product.
     * - "embeddedEsx" - For the ESXi product.
     * - "vpx" - For the VirtualCenter product.
     *
     * @var string
     */
    public $productLineId;

    /** @var string Name of the vendor of this product */
    public $vendor;

    /** @var string Dot-separated version string. For example, "1.2" */
    public $version;
}
