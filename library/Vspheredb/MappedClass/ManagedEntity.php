<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

class ManagedEntity
{
    /**
     * Whether alarm actions are enabled for this entity
     *
     * Required Privilege: System.Read
     *
     * @var bool
     */
    public $alarmActionsEnabled;

    /**
     * Current configuration issues that have been detected for this entity.
     * Typically, these issues have already been logged as events. The entity
     * stores these events as long as they are still current. The configStatus
     * property provides an overall status based on these events.
     *
     * @var Event[]
     */
    public $configIssue;

    /**
     * The configStatus indicates whether or not the system has detected a
     * configuration issue involving this entity. For example, it might have
     * detected a duplicate IP address or MAC address, or a host in a cluster
     * might be out of compliance. The meanings of the configStatus values are:
     *
     * - red: A problem has been detected involving the entity
     * - yellow: A problem is about to occur or a transient condition has
     *   occurred (For example, reconfigure fail-over policy)
     * - green: No configuration issues have been detected
     * - gray: The configuration status of the entity is not being monitored
     *
     * A green status indicates only that a problem has not been detected; it
     * is not a guarantee that the entity is problem-free.
     *
     * The configIssue property contains a list of the problems that have been
     * detected. In releases after vSphere API 5.0, vSphere Servers might not
     * generate property collector update notifications for this property. To
     * obtain the latest value of the property, you can use PropertyCollector
     * methods RetrievePropertiesEx or WaitForUpdatesEx. If you use the
     * PropertyCollector. WaitForUpdatesEx method, specify an empty string for
     * the version parameter. Any other version value will not produce any
     * property values as no updates are generated.
     *
     * @var ManagedEntityStatus
     */
    public $configStatus;

    /**
     * Custom field values
     *
     * Required Privilege: System.Read
     *
     * @var CustomFieldValue[]
     */
    public $customValue;

    /**
     * A set of alarm states for alarms that apply to this managed entity. The
     * set includes alarms defined on this entity and alarms inherited from the
     * parent entity, or from any ancestors in the inventory hierarchy
     *
     * Alarms are inherited if they can be triggered by this entity or its
     * descendants. This set does not include alarms that are defined on descendants
     * of this entity
     *
     * Required Privilege: System.View
     *
     * @var AlarmState[]
     */
    public $declaredAlarmState;

    /**
     * List of operations that are disabled, given the current runtime state of
     * the entity. For example, a power-on operation always fails if a virtual
     * machine is already powered on. This list can be used by clients to enable
     * or disable operations in a graphical user interface.
     *
     * Note: This list is determined by the current runtime state of an entity,
     * not by its permissions.
     *
     * For a list of what this list might contain for various object types please
     * see: https://www.vmware.com/support/developer/converter-sdk/conv61_apireference/vim.ManagedEntity.html
     *
     * vSphere Servers might not generate property collector update notifications
     * for this property. To obtain the latest value of the property, you can use
     * PropertyCollector methods RetrievePropertiesEx or WaitForUpdatesEx. If you
     * use the PropertyCollector.WaitForUpdatesEx method, specify an empty string
     * for the version parameter. Any other version value will not produce any
     * property values as no updates are generated.
     *
     * @var string[]
     */
    public $disabledMethod;

    /**
     * Access rights the current session has to this entity
     *
     * Required Privilege: System.View
     *
     * @var int[]
     */
    public $effectiveRole;

    /**
     * Name of this entity, unique relative to its parent
     *
     * Any / (slash), \ (backslash), character used in this name element will be
     * escaped. Similarly, any % (percent) character used in this name element
     * will be escaped, unless it is used to start an escape sequence. A slash
     * is escaped as %2F or %2f. A backslash is escaped as %5C or %5c, and a
     * percent is escaped as %25
     *
     * Required Privilege: System.View
     *
     * @var string
     */
    public $name;

    /**
     * General health of this managed entity. The overall status of the managed
     * entity is computed as the worst status among its alarms and the
     * configuration issues detected on the entity. The status is reported as
     * one of the following values:
     *
     * - red: The entity has alarms or configuration issues with a red status
     * - yellow: The entity does not have alarms or configuration issues with
     *   a red status, and has at least one with a yellow status
     * - green: The entity does not have alarms or configuration issues with a
     *   red or yellow status, and has at least one with a green status
     * - gray: All of the entity's alarms have a gray status and the configuration
     *   status of the entity is not being monitored
     *
     * vSphere Servers might not generate property collector update notifications
     * for this property. To obtain the latest value of the property, you can use
     * PropertyCollector methods RetrievePropertiesEx or WaitForUpdatesEx. If you
     * use the PropertyCollector.WaitForUpdatesEx method, specify an empty string
     * for the version parameter. Any other version value will not produce any
     * property values as no updates are generated.
     *
     * @var string ManagedEntityStatus
     */
    public $overallStatus;

    /**
     * Parent of this entity.
     * This value is null for the root object and for VirtualMachine objects
     * that are part of a VirtualApp
     *
     * Required Privilege: System.View
     *
     * @var ManagedObjectReference to a ManagedEntity
     */
    public $parent;

    /**
     * List of permissions defined for this entity
     *
     * @var Permission[]
     */
    public $permission;

    /**
     * The set of recent tasks operating on this managed entity. This is a subset
     * of recentTask belong to this entity. A task in this list could be in one
     * of the four states: pending, running, success or error.
     *
     * This property can be used to deduce intermediate power states for a
     * virtual machine entity. For example, if the current powerState is "poweredOn"
     * and there is a running task performing the "suspend" operation, then the
     * virtual machine's intermediate state might be described as "suspending."
     *
     * Most tasks (such as power operations) obtain exclusive access to the virtual
     * machine, so it is unusual for this list to contain more than one running
     * task. One exception, however, is the task of cloning a virtual machine.
     *
     * vSphere Servers might not generate property collector update notifications
     * for this property. To obtain the latest value of the property, you can use
     * PropertyCollector methods RetrievePropertiesEx or WaitForUpdatesEx. If you
     * use the PropertyCollector.WaitForUpdatesEx method, specify an empty string
     * for the version parameter. Any other version value will not produce any
     * property values as no updates are generated.
     *
     * @var ManagedObjectReference[] to a Task[]
     */
    public $recentTask;


    /**
     * The set of tags associated with this managed entity. Experimental.
     * Subject to change.
     *
     * Required Privilege: System.View
     *
     * @var Tag[]
     */
    public $tag;

    /**
     * A set of alarm states for alarms triggered by this entity or by its
     * descendants.
     *
     * Triggered alarms are propagated up the inventory hierarchy so that a
     * user can readily tell when a descendant has triggered an alarm. vSphere
     * Servers might not generate property collector update notifications for
     * this property. To obtain the latest value of the property, you can use
     * PropertyCollector methods RetrievePropertiesEx or WaitForUpdatesEx. If
     * you use the PropertyCollector.WaitForUpdatesEx method, specify an empty
     * string for the version parameter. Any other version value will not
     * produce any property values as no updates are generated
     *
     * Required Privilege: System.View
     *
     * @var AlarmState[]
     */
    public $triggeredAlarmState;
}
