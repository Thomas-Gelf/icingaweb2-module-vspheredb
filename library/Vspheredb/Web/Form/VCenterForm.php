<?php

namespace Icinga\Module\Vspheredb\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\BaseDbObject;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VCenterServer;

class VCenterForm extends Form
{
    use TranslationHelper;

    protected $objectClassName = VCenterServer::class;

    /** @var VCenterServer */
    protected $object;

    protected $db;

    protected $deleted = false;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function assemble()
    {
        $this->prepareWebForm();
        $this->addElement('text', 'name', [
            'label'       => $this->translate('Name'),
            'description' => $this->translate(
                'You might want to change the display name of your vCenter.'
                . ' This defaults to the first related Server host name.'
            ),
        ]);
        $this->addElement('submit', 'submit', [
            'label' => $this->isNew() ? $this->translate('Create') : $this->translate('Store')
        ]);
        /*
        $this->addElement('submit', 'btn_delete', [
            'label' => $this->translate('Delete')
        ]);
        $deleteButton = $this->getElement('btn_delete');
        if ($deleteButton && $deleteButton->hasBeenPressed()) {
            $this->getObject()->delete();
            $this->deleted = true;
        }
        */
    }

    public function isNew()
    {
        return $this->object === null || ! $this->object->hasBeenLoadedFromDb();
    }

    public function setObject(VCenter $object)
    {
        $this->object = $object;
        $properties = $object->getProperties();
        $this->populate($properties);

        return $this;
    }

    /**
     * @return BaseDbObject
     */
    public function getObject()
    {
        if ($this->object === null) {
            /** @var BaseDbObject $class */
            $class = $this->objectClassName;
            $this->object = $class::create([], $this->db);
        }

        return $this->object;
    }

    public function hasBeenDeleted()
    {
        return $this->deleted;
    }

    public function onSuccess()
    {
        $this->getObject()->setProperties($this->getValues());
    }
}
