<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

/**
 * ServiceContent
 *
 * The ServiceContent data object defines properties for the ServiceInstance
 * managed object. The ServiceInstance itself does not have directly-accessible
 * properties because reading the properties of a managed object requires the
 * use of a property collector, and the property collector itself is a property
 * of the ServiceInstance.
 *
 * For this reason, use the method RetrieveServiceContent to retrieve the
 * ServiceContent object.
 */
class ServiceContent
{
    /** @var AboutInfo */
    public $about;

    /** @var ManagedObjectReference to a HostLocalAccountManager */
    public $accountManager;

    /** @var ManagedObjectReference to a AlarmManager */
    public $alarmManager;

    /** @var ManagedObjectReference to a AuthorizationManager */
    public $authorizationManager;

    /** @var ManagedObjectReference to a CertificateManager - since vSphere API 6.0 */
    public $certificateManager;

    /** @var ManagedObjectReference to a ClusterProfileManager */
    public $clusterProfileManager;

    /** @var ManagedObjectReference to a ProfileComplianceManager */
    public $complianceManager;

    /** @var ManagedObjectReference to a CustomFieldsManager */
    public $customFieldsManager;

    /** @var ManagedObjectReference to a CustomizationSpecManager */
    public $customizationSpecManager;

    /** @var ManagedObjectReference to a CustomizationSpecManager */
    public $datastoreNamespaceManager;

    /** @var ManagedObjectReference to a DiagnosticManager */
    public $diagnosticManager;

    /** @var ManagedObjectReference to a DistributedVirtualSwitchManager */
    public $dvSwitchManager;

    /** @var ManagedObjectReference to a EventManager */
    public $eventManager;

    /** @var ManagedObjectReference to a ExtensionManager */
    public $extensionManager;

    /** @var ManagedObjectReference to a FileManager */
    public $fileManager;

    /** @var ManagedObjectReference to a GuestOperationsManager */
    public $guestOperationsManager;

    /** @var ManagedObjectReference to a HostProfileManager */
    public $hostProfileManager;

    /** @var ManagedObjectReference to a IoFilterManager - since vSphere API 6.0 */
    public $ioFilterManager;

    /** @var ManagedObjectReference to a IpPoolManager */
    public $ipPoolManager;

    /** @var ManagedObjectReference to a LicenseManager */
    public $licenseManager;

    /** @var ManagedObjectReference to a LocalizationManager */
    public $localizationManager;

    /** @var ManagedObjectReference to a OverheadMemoryManager - since vSphere API 6.0 */
    public $overheadMemoryManager;

    /** @var ManagedObjectReference to a OvfManager */
    public $ovfManager;

    /** @var ManagedObjectReference to a PerformanceManager */
    public $perfManager;

    /** @var ManagedObjectReference to a PropertyCollector */
    public $propertyCollector;

    /** @var ManagedObjectReference to a Folder */
    public $rootFolder;

    /** @var ManagedObjectReference to a ScheduledTaskManager */
    public $scheduledTaskManager;

    /** @var ManagedObjectReference to a SearchIndex */
    public $searchIndex;

    /** @var ManagedObjectReference to a ServiceManager */
    public $serviceManager;

    /** @var ManagedObjectReference to a SessionManager */
    public $sessionManager;

    /** @var ManagedObjectReference to a OptionManager */
    public $setting;

    /** @var ManagedObjectReference to a HostSnmpSystem */
    public $snmpSystem;

    /** @var ManagedObjectReference to a StorageResourceManager */
    public $storageResourceManager;

    /** @var ManagedObjectReference to a TaskManager */
    public $taskManager;

    /** @var ManagedObjectReference to a UserDirectory */
    public $userDirectory;

    /** @var ManagedObjectReference to a ViewManager */
    public $viewManager;

    /** @var ManagedObjectReference to a VirtualDiskManager */
    public $virtualDiskManager;

    /** @var ManagedObjectReference to a VirtualizationManager */
    public $virtualizationManager;

    /** @var ManagedObjectReference to a VirtualMachineCompatibilityChecker */
    public $vmCompatibilityChecker;

    /** @var ManagedObjectReference to a VirtualMachineProvisioningChecker */
    public $vmProvisioningChecker;
}
