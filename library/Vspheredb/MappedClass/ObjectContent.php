<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

// https://www.vmware.com/support/developer/converter-sdk/conv61_apireference/vmodl.query.PropertyCollector.ObjectContent.html

/**
 * The ObjectContent data object type contains the contents retrieved for a
 * single managed object.
 */
class ObjectContent
{
    /**
     * Properties for which values could not be retrieved and the associated fault
     *
     * @var MissingProperty[]|null
     */
    public $missingSet;

    /**
     * Reference to the managed object that contains properties of interest
     *
     * @var ManagedObjectReference
     */
    public $obj;

    /**
     * Set of name-value pairs for the properties of the managed object
     *
     * @var DynamicProperty[]|null
     */
    public $propSet;

    /**
     * @return bool
     */
    public function hasMissingProperties()
    {
        return $this->missingSet !== null;
    }

    /**
     * @return bool
     */
    public function reportsNotAuthenticated()
    {
        if ($this->missingSet === null) {
            return false;
        }

        foreach ($this->missingSet as $missingProperty) {
            if ($missingProperty->isNotAuthenticated()) {
                return true;
            }
        }

        return false;
    }

    public function toNewObject()
    {
        $class = ApiClassMap::requireTypeMap($this->obj->type);
        $obj = new $class;
        foreach ($this->propSet as $dynamicProperty) {
            $obj->{$dynamicProperty->name} = $dynamicProperty->val;
        }

        return $obj;
    }
}
