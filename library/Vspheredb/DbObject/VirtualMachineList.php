<?php

namespace Icinga\Module\Vspheredb\DbObject;

class VirtualMachineList extends MoRefList
{
    const LIST_TABLE_NAME = 'vm_list';

    const MEMBER_TABLE_NAME = 'vm_list_member';
}
