<?php

namespace Icinga\Module\Vspheredb\Api;

use Icinga\Module\Vspheredb\MappedClass\ObjectContent;

abstract class ApiHelper
{
    public static function inspectObjectContent(ObjectContent $object)
    {
        if ($object->hasMissingProperties()) {
            foreach ($object->missingSet as $missingProperty) {
                if ($missingProperty->isNotAuthenticated()) {
                    printf(
                        "Cannot see %s because I'm not authenticated\n",
                        $missingProperty->path
                    );
                } elseif ($missingProperty->isNoPermission()) {
                    printf(
                        "Cannot see %s because I have not enough permissions\n",
                        $missingProperty->path
                    );
                } else {
                    printf(
                        "Cannot see %s because of security problems\n",
                        $missingProperty->path
                    );
                }
            }
        }
        $properties = [];
        foreach ($object->propSet as $props) {
            $properties[$props->name] = $props->val;
        }
        printf("Got %s[%s]: %s\n", $object->obj->type, $object->obj->_, print_r($properties, 1));
    }
}
