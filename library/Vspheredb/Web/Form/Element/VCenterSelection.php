<?php

namespace Icinga\Module\Vspheredb\Web\Form\Element;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Vspheredb\Db;
use ipl\Html\FormElement\SelectElement;
use Ramsey\Uuid\Uuid;
use Zend_Db_Adapter_Abstract as ZfDb;

class VCenterSelection extends SelectElement
{
    use TranslationHelper;

    /** @var Db */
    protected $db;

    protected $optional = false;

    public function __construct(ZfDb $db, $required = false, $name = 'vcenter', $attributes = null)
    {
        $this->db = $db;
        parent::__construct($name, $attributes);
        $enum = $this->enumVCenters();
        $this->addAttributes([
            'options' => $required ? $enum : [
                null => $this->translate('- please choose -'),
            ] + $enum,
            'class' => 'autosubmit',
        ]);
    }

    protected function enumVCenters()
    {
        $pairs = $this->db->fetchPairs(
            $this->db->select()->from(['vc' => 'vcenter'], [
                'uuid' => 'LOWER(HEX(vc.instance_uuid))',
                'name' => "vc.name || ' (' || REPLACE(vc.api_name, 'VMware ', '') || ')'",
            ])->order('vc.name')
        );
        $enum = [];
        foreach ($pairs as $uuid => $label) {
            $enum[Uuid::fromString($uuid)->toString()] = $label;
        }

        return $enum;
    }
}
