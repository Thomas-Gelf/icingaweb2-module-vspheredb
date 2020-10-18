<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;
use Icinga\Module\Vspheredb\VmwareDataType\NumericRange;
use RuntimeException;

class ApiClassMap
{
    protected static $map;

    public static function getMap()
    {
        if (self::$map === null) {
            self::$map = static::prepareMap();
        }

        return self::$map;
    }

    public static function hasTypeMap($type)
    {
        return isset(static::getMap()[$type]);
    }

    public static function requireTypeMap($type)
    {
        $map = static::getMap();
        if (isset($map[$type])) {
            return $map[$type];
        }

        throw new RuntimeException(sprintf(
            'Type "%s" has no class mapping',
            $type
        ));
    }

    public static function prepareMap()
    {
        // Hint: put more specific classes on top, otherwise a more generic one might match
        $map = [
            'RetrieveResult'             => RetrieveResult::class,
            'RetrievePropertiesResponse' => RetrievePropertiesResponse::class,
            'DynamicData'          => DynamicData::class,
            'DynamicProperty'      => DynamicProperty::class,
            'ObjectContent'        => ObjectContent::class,
            'KeyValue'             => KeyValue::class,
            'MissingProperty'      => MissingProperty::class,
            'InvalidProperty'      => InvalidProperty::class,
            'SystemError'          => SystemError::class,
            'NotAuthenticated'     => NotAuthenticated::class,
            'NoPermission'         => NoPermission::class,
            'SecurityError'        => SecurityError::class,
            'LocalizedMethodFault' => LocalizedMethodFault::class,
            'ServiceContent'       => ServiceContent::class,
            'AboutInfo'            => AboutInfo::class,
            'SessionManager'       => SessionManager::class,
            'UserSession'          => UserSession::class,

            // 'ManagedObjectNotFoundFault' => "$base\\ManagedObjectNotFoundFault",
            // 'AlarmEvent'              => "$base\\AlarmEvent",
            'AlarmAcknowledgedEvent'  => AlarmAcknowledgedEvent::class,
            'AlarmClearedEvent'       => AlarmClearedEvent::class,
            'AlarmCreatedEvent'       => AlarmCreatedEvent::class,
            'AlarmReconfiguredEvent'  => AlarmReconfiguredEvent::class,
            'AlarmRemovedEvent'       => AlarmRemovedEvent::class,
            'AlarmStatusChangedEvent' => AlarmStatusChangedEvent::class,

            // AlarmActionTriggeredEvent
            // AlarmEmailCompletedEvent
            // AlarmEmailFailedEvent
            // AlarmScriptCompleteEvent
            // AlarmScriptFailedEvent
            // AlarmSnmpCompletedEvent
            // AlarmSnmpFailedEvent

            'UserLoginSessionEvent'            => UserLoginSessionEvent::class,
            'SessionTerminatedEvent'           => SessionTerminatedEvent::class,
            'NoAccessUserEvent'                => NoAccessUserEvent::class,
            'BadUsernameSessionEvent'          => BadUsernameSessionEvent::class,
            'GlobalMessageChangedEvent'        => GlobalMessageChangedEvent::class,
            'UserLogoutSessionEvent'           => UserLogoutSessionEvent::class,
            'AlreadyAuthenticatedSessionEvent' => AlreadyAuthenticatedSessionEvent::class,

            // VmMessageEvent
            // TaskEvent
            // EventEx
            // AlarmStatusChangedEvent
            // UserLoginSessionEvent
            // DrsRuleViolationEvent
            // DrsSoftRuleViolationEvent
            // NonVIWorkloadDetectedOnDatastoreEvent
            // VmAcquiredTicketEvent
            // CustomFieldValueChangedEvent

            'VmFailedMigrateEvent'    => VmFailedMigrateEvent::class,
            'MigrationEvent'          => BaseMigrationEvent::class,
            'VmBeingMigratedEvent'    => VmBeingMigratedEvent::class,
            'VmBeingHotMigratedEvent' => VmBeingHotMigratedEvent::class,
            'VmEmigratingEvent'       => VmEmigratingEvent::class,
            'VmMigratedEvent'         => VmMigratedEvent::class,

            'VmBeingCreatedEvent'  => VmBeingCreatedEvent::class,
            'VmCreatedEvent'       => VmCreatedEvent::class,
            'VmPoweredOnEvent'     => VmPoweredOnEvent::class,
            'VmPoweredOffEvent'    => VmPoweredOffEvent::class,
            'VmResettingEvent'     => VmResettingEvent::class,
            'VmSuspendedEvent'     => VmSuspendedEvent::class,
            'VmReconfiguredEvent'  => VmReconfiguredEvent::class,
            'VmStartingEvent'      => VmStartingEvent::class,
            'VmStoppingEvent'      => VmStoppingEvent::class,
            // Not seen yet:
            'VmBeingDeployedEvent' => VmBeingDeployedEvent::class,

            'VmBeingClonedEvent'         => VmBeingClonedEvent::class,
            'VmBeingClonedNoFolderEvent' => VmBeingClonedNoFolderEvent::class,
            'VmClonedEvent'              => VmClonedEvent::class,
            'VmCloneFailedEvent'         => VmCloneFailedEvent::class,

            'ElementDescription'     => ElementDescription::class,
            'PerfCounterInfo'        => PerfCounterInfo::class,
            'PerfInterval'           => PerfInterval::class,
            'PerfEntityMetricCSV'    => PerfEntityMetricCSV::class,
            'PerfMetricId'           => PerfMetricId::class,
            'PerfMetricSeriesCSV'    => PerfMetricSeriesCSV::class,
            'PerformanceDescription' => PerformanceDescription::class,
            'PerformanceManager'     => PerformanceManager::class,
            'PerfQuerySpec'          => PerfQuerySpec::class,

            'EventHistoryCollector'        => EventHistoryCollector::class,
            'AlarmEventArgument'           => AlarmEventArgument::class,
            'ComputeResourceEventArgument' => ComputeResourceEventArgument::class,
            'DatacenterEventArgument'      => DatacenterEventArgument::class,
            'DatastoreEventArgument'       => DatastoreEventArgument::class,
            'HostEventArgument'            => HostEventArgument::class,
            'VmEventArgument'              => VmEventArgument::class,
            'ManagedObjectReference' => ManagedObjectReference::class,
            'NumericRange'           => NumericRange::class,
            'ClusterDasFdmHostState' => ClusterDasFdmHostState::class,
            'CustomFieldsManager'    => CustomFieldsManager::class,
            'CustomFieldDef'         => CustomFieldDef::class,
            'CustomFieldValue'       => CustomFieldValue::class,
            'PrivilegePolicyDef'     => PrivilegePolicyDef::class,

            'DistributedVirtualSwitchPortConnection'    => DistributedVirtualSwitchPortConnection::class,
            'DistributedVirtualSwitchHostMemberBacking' => DistributedVirtualSwitchHostMemberBacking::class,
            'DistributedVirtualSwitchHostMemberPnicBacking' => DistributedVirtualSwitchHostMemberPnicBacking::class,
            'DistributedVirtualSwitchHostMemberPnicSpec' => DistributedVirtualSwitchHostMemberPnicSpec::class,
            'HostDnsConfig'           => HostDnsConfig::class,
            'HostIpConfig'            => HostIpConfig::class,
            'HostIpConfigIpV6Address' => HostIpConfigIpV6Address::class,
            'HostIpConfigIpV6AddressConfiguration' => HostIpConfigIpV6AddressConfiguration::class,
            'HostIpRouteConfig'       => HostIpRouteConfig::class,
            'HostNetStackInstance'    => HostNetStackInstance::class,
            'HostNetworkInfo'         => HostNetworkInfo::class,
            'HostOpaqueNetworkInfo'   => HostOpaqueNetworkInfo::class,
            'HostOpaqueSwitch'        => HostOpaqueSwitch::class,
            'HostPortGroup'           => HostPortGroup::class,
            'HostPortGroupPort'       => HostPortGroupPort::class,
            'HostPortGroupSpec'       => HostPortGroupSpec::class,
            'HostProxySwitch'         => HostProxySwitch::class,
            'HostProxySwitchSpec'     => HostProxySwitchSpec::class,
            'HostVirtualNic'          => HostVirtualNic::class,
            'HostVirtualNicSpec'      => HostVirtualNicSpec::class,
            'HostVirtualSwitch'       => HostVirtualSwitch::class,
            'HostVirtualSwitchBridge' => HostVirtualSwitchBridge::class,
            'HostVirtualSwitchSpec'   => HostVirtualSwitchSpec::class,

            'PhysicalNic'             => PhysicalNic::class,
            'PhysicalNicLinkInfo'     => PhysicalNicLinkInfo::class,
            'PhysicalNicSpec'         => PhysicalNicSpec::class,
            'HostNumericSensorInfo'   => HostNumericSensorInfo::class,
        ];

        return $map;
    }
}
