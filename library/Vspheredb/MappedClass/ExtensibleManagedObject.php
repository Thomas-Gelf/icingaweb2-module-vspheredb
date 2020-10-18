<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

/**
 * ExtensibleManagedObject provides methods and properties that provide access
 * to custom fields that may be associated with a managed object. Use the
 * CustomFieldsManager to define custom fields. The CustomFieldsManager handles
 * the entire list of custom fields on a server. You can can specify the object
 * type to which a particular custom field applies by setting its managedObjectType.
 * (If you do not set a managed object type for a custom field definition, the
 * field applies to all managed objects.)
 */
class ExtensibleManagedObject
{
    /**
     * List of custom field definitions that are valid for the object's type.
     * The fields are sorted by name.
     *
     * @var CustomFieldDef[]
     */
    public $availableField;

    /**
     * List of custom field values. Each value uses a key to associate an
     * Instance of a CustomFieldStringValue with a custom field definition
     *
     * @var CustomFieldValue[]
     */
    public $value;
}
