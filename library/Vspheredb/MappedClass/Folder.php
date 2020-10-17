<?php

namespace Icinga\Module\Vspheredb\MappedClass;

use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;

class Folder extends ManagedEntity
{
    /**
     * An array of managed object references. Each entry is a reference to a child entity
     *
     * @var ManagedObjectReference[] to a ManagedEntity[]
     */
    public $childEntity = [];

    /**
     * Specifies the object types a folder may contain. When you create a folder,
     * it inherits its childType from the parent folder in which it is created.
     * childType is an array of strings. Each array entry identifies a set of
     * object types - Folder and one or more managed object types. The following
     * list shows childType values for the different folders:
     *
     * - { "vim.Folder", "vim.Datacenter" } - Identifies the root folder and its
     *   descendant folders. Data center folders can contain child data center
     *   folders and Datacenter managed objects. Datacenter objects contain virtual
     *   machine, compute resource, network entity, and datastore folders.
     * - { "vim.Folder", "vim.Virtualmachine", "vim.VirtualApp" } - Identifies a
     *   virtual machine folder. A virtual machine folder may contain child virtual
     *   machine folders. It also can contain VirtualMachine managed objects,
     *   templates, and VirtualApp managed objects.
     * - { "vim.Folder", "vim.ComputeResource" } - Identifies a compute resource
     *   folder, which contains child compute resource folders and ComputeResource
     *   hierarchies.
     * - { "vim.Folder", "vim.Network" } - Identifies a network entity folder.
     *   Network entity folders on a vCenter Server can contain Network,
     *   DistributedVirtualSwitch, and DistributedVirtualPortgroup managed objects.
     *   Network entity folders on an ESXi host can contain only Network objects.
     * - { "vim.Folder", "vim.Datastore" } - Identifies a datastore folder.
     *   Datastore folders can contain child datastore folders and Datastore
     *   managed objects.
     *
     * @var array
     */
    public $childType = [];
}
