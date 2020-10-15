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

    /** @var ManagedObjectReference|null to a HostLocalAccountManager */
    public $accountManager;

    /** @var ManagedObjectReference|null to a AlarmManager */
    public $alarmManager;

    /** @var ManagedObjectReference|null to a AuthorizationManager */
    public $authorizationManager;

    /** @var ManagedObjectReference|null to a CertificateManager - since vSphere API 6.0 */
    public $certificateManager;

    /** @var ManagedObjectReference|null to a ClusterProfileManager */
    public $clusterProfileManager;

    /** @var ManagedObjectReference|null to a ProfileComplianceManager */
    public $complianceManager;

    /** @var ManagedObjectReference|null to a CustomFieldsManager */
    public $customFieldsManager;

    /** @var ManagedObjectReference|null to a CustomizationSpecManager */
    public $customizationSpecManager;

    /** @var ManagedObjectReference|null to a CustomizationSpecManager */
    public $datastoreNamespaceManager;

    /** @var ManagedObjectReference|null to a DiagnosticManager */
    public $diagnosticManager;

    /** @var ManagedObjectReference|null to a DistributedVirtualSwitchManager */
    public $dvSwitchManager;

    /** @var ManagedObjectReference|null to a EventManager */
    public $eventManager;

    /** @var ManagedObjectReference|null to a ExtensionManager */
    public $extensionManager;

    /** @var ManagedObjectReference|null to a FileManager */
    public $fileManager;

    /** @var ManagedObjectReference|null to a GuestOperationsManager */
    public $guestOperationsManager;

    /** @var ManagedObjectReference|null to a HostProfileManager */
    public $hostProfileManager;

    /** @var ManagedObjectReference|null to a IoFilterManager - since vSphere API 6.0 */
    public $ioFilterManager;

    /** @var ManagedObjectReference|null to a IpPoolManager */
    public $ipPoolManager;

    /** @var ManagedObjectReference|null to a LicenseManager */
    public $licenseManager;

    /** @var ManagedObjectReference|null to a LocalizationManager */
    public $localizationManager;

    /** @var ManagedObjectReference|null to a OverheadMemoryManager - since vSphere API 6.0 */
    public $overheadMemoryManager;

    /** @var ManagedObjectReference|null to a OvfManager */
    public $ovfManager;

    /** @var ManagedObjectReference|null to a PerformanceManager */
    public $perfManager;

    /** @var ManagedObjectReference to a PropertyCollector */
    public $propertyCollector;

    /** @var ManagedObjectReference to a Folder */
    public $rootFolder;

    /** @var ManagedObjectReference|null to a ScheduledTaskManager */
    public $scheduledTaskManager;

    /** @var ManagedObjectReference|null to a SearchIndex */
    public $searchIndex;

    /** @var ManagedObjectReference|null to a ServiceManager */
    public $serviceManager;

    /** @var ManagedObjectReference|null to a SessionManager */
    public $sessionManager;

    /** @var ManagedObjectReference|null to a OptionManager */
    public $setting;

    /** @var ManagedObjectReference|null to a HostSnmpSystem */
    public $snmpSystem;

    /** @var ManagedObjectReference|null to a StorageResourceManager */
    public $storageResourceManager;

    /** @var ManagedObjectReference|null to a TaskManager */
    public $taskManager;

    /** @var ManagedObjectReference|null to a UserDirectory */
    public $userDirectory;

    /** @var ManagedObjectReference|null to a ViewManager */
    public $viewManager;

    /** @var ManagedObjectReference|null to a VirtualDiskManager */
    public $virtualDiskManager;

    /** @var ManagedObjectReference|null to a VirtualizationManager */
    public $virtualizationManager;

    /** @var ManagedObjectReference|null to a VirtualMachineCompatibilityChecker */
    public $vmCompatibilityChecker;

    /** @var ManagedObjectReference|null to a VirtualMachineProvisioningChecker */
    public $vmProvisioningChecker;
}
