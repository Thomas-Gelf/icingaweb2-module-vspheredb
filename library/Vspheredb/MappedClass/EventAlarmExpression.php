<?php

namespace Icinga\Module\Vspheredb\MappedClass;

/**
 * An alarm expression that uses the event stream to trigger the alarm
 *
 * This alarm is triggered when an event matching this expression gets logged
 */
class EventAlarmExpression extends AlarmExpression
{
    /** @var EventAlarmExpressionComparison[] The attributes/values to compare */
    public $comparisons;

    /**
     * Deprecated. use eventTypeId instead
     * The type of the event to trigger the alarm on
     *
     * @var string
     */
    public $eventType;

    /**
     * The eventTypeId of the event to match.
     *
     * The semantics of how eventTypeId matching is done is as follows:
     *
     * - If the event being matched is of type EventEx or ExtendedEvent, then
     *   we match this value against the eventTypeId (for EventEx) or eventId
     *   (for ExtendedEvent) member of the Event.
     * - Otherwise, we match it against the type of the Event itself.
     *
     * Either eventType or eventTypeId must be set.
     *
     * @var string|null
     */
    public $eventTypeId;

    /**
     * Name of the type of managed object on which the event is logged.
     *
     * An event alarm defined on a ManagedEntity is propagated to child entities
     * in the VirtualCenter inventory depending on the value of this attribute.
     * If objectType is any of the following, the alarm is propagated down to
     * all children of that type:
     *
     * - A datacenter: Datacenter
     * - A cluster of host systems: ClusterComputeResource
     * - A single host system: HostSystem
     * - A resource pool representing a set of physical resources on a single
     *   host: ResourcePool
     * - A virtual machine: VirtualMachine
     * - A datastore: Datastore
     * - A network: Network
     * - A distributed virtual switch: DistributedVirtualSwitch
     *
     * If objectType is unspecified or not contained in the above list, the
     * event alarm is not propagated down to child entities in the VirtualCenter
     * inventory.
     *
     * It is possible to specify an event alarm containing two (or more) different
     * EventAlarmExpression's which contain different objectTypes. In such a case,
     * the event is propagated to all child entities with specified type(s).
     *
     * @var string
     */
    public $objectType;

    /**
     * The alarm's new state when this condition is evaluated and satisfied. If not
     * specified then there is no change to alarm status, and all actions are fired
     * (rather than those for the transition)
     *
     * @var string ManagedEntityStatus (gray, green, red, yellow)
     */
    public $status;
}
