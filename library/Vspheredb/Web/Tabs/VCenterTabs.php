<?php

namespace Icinga\Module\Vspheredb\Web\Tabs;

use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Ramsey\Uuid\Uuid;

class VCenterTabs extends Tabs
{
    use TranslationHelper;

    /** @var VCenter */
    protected $vCenter;

    public function __construct(VCenter $vCenter)
    {
        $this->vCenter = $vCenter;
        // We are not a BaseElement, not yet
        $this->assemble();
    }

    protected function assemble()
    {
        $hexUuid = Uuid::fromBytes($this->vCenter->getUuid())->toString();
        $this->add('vcenter', [
            'label' => $this->translate('vCenter'),
            'url'   => 'vspheredb/vcenter',
            'urlParams' => ['vcenter' => $hexUuid],
        ])->add('clusters', [
            'label' => $this->translate('Clusters'),
            'url'   => 'vspheredb/resources/clusters',
            'urlParams' => ['vcenter' => $hexUuid],
        ])->add('perfcounters', [
            'label' => $this->translate('Counters'),
            'url'   => 'vspheredb/perfdata/counters',
            'urlParams' => ['vcenter' => $hexUuid],
        ]);
    }
}
