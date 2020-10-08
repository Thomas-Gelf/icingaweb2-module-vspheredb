<?php

namespace Icinga\Module\Vspheredb\Web\Widget\Link;

use gipfl\IcingaWeb2\Icon;
use gipfl\Translation\TranslationHelper;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\DbObject\VirtualMachine;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class VmrcLink extends HtmlDocument
{
    use TranslationHelper;

    protected $vCenter;

    protected $label;

    protected $moRef;

    public function __construct(VCenter $vCenter, VirtualMachine $vm, $label = null)
    {
        $this->vCenter = $vCenter;
        if ($label === null) {
            $this->label = $vm->object()->get('object_name');
        } else {
            $this->label = $label;
        }

        $this->moRef = $vm->object()->get('moref');
    }

    protected function assemble()
    {
        try {
            $server = $this->vCenter->getFirstServer();
            $this->add(Html::tag('a', [
                'href' => sprintf(
                    'vmrc://%s/?moid=%s',
                    $server->get('host'),
                    \rawurlencode($this->moRef)
                ),
                'target' => '_self',
                'title' => $this->translate('Open VMware Remote Console (VMRC)'),
                'class' => 'icon-host',
            ], $this->label));
        } catch (NotFoundError $e) {
            $this->add([
                Icon::create('warning-empty', [
                    'class' => 'red'
                ]),
                ' ',
                $this->translate('No related vServer has been configured')
            ]);
        }
    }
}
