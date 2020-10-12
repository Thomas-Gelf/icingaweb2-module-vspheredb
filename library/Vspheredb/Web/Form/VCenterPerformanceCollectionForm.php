<?php

namespace Icinga\Module\Vspheredb\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\Form\Decorator\DdDtDecorator;
use Icinga\Module\Vspheredb\Hook\PerfDataReceiverHook;
use Icinga\Module\Vspheredb\Json;
use Icinga\Module\Vspheredb\PerformanceData\PerformanceSet\PerformanceSets;
use Icinga\Module\Vspheredb\Storable\PerfdataSubscription;
use Icinga\Module\Vspheredb\Web\Form\Element\VCenterSelection;
use ipl\Html\FormElement\SubmitElement;

class VCenterPerformanceCollectionForm extends ObjectForm
{
    use TranslationHelper;

    protected $class = PerfdataSubscription::class;

    public function assemble()
    {
        $this->addElement((new VCenterSelection($this->store->getDb()))->setLabel($this->translate('VCenter')));
        $this->addElement('boolean', 'enabled', [
            'label' => $this->translate('Enabled'),
            'value' => 'y',
            'required' => true,
        ]);
        if ($implementation = $this->selectImplementation()) {
            $this->addImplementation($implementation);
        }
        $this->addPerformanceSets();
        $this->addButtons();
    }

    public function getValues()
    {
        $values = parent::getValues();
        if (array_key_exists('vcenter', $values)) {
            if ($values['vcenter'] !== null) {
                $values['vcenter_uuid'] = hex2bin($values['vcenter']);
            }
            unset($values['vcenter']);
        }
        $mainProperties = [
            'implementation',
            'settings',
            'enabled',
            'vcenter_uuid',
            'performance_sets',
        ];
        $finalValues = [];
        $settings = [];
        foreach ($values as $key => $value) {
            if (in_array($key, $mainProperties)) {
                $finalValues[$key] = $value;
            } else {
                $settings[$key] = $value;
            }
        }
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $finalValues['settings'] = Json::encode($settings, $flags);
        $finalValues['performance_sets'] = Json::encode($finalValues['performance_sets'], $flags);

        return $finalValues;
    }

    protected function addPerformanceSets()
    {
        $sets = PerformanceSets::enumAvailableSets();
        $this->addElement('multiSelect', 'performance_sets', [
            'label'    => $this->translate('Performance Sets'),
            'options'  => $sets,
            'required' => true,
            'value'    => array_keys($sets),
        ]);
    }

    protected function selectImplementation()
    {
        $this->addElement('select', 'implementation', [
            'label'    => $this->translate('Implementation'),
            'options'  => [
                    null => $this->translate('- please choose -'),
                ] + PerfDataReceiverHook::enum(),
            'required' => true,
            'class'    => 'autosubmit',
        ]);

        return $this->getValue('implementation');
    }

    protected function addImplementation($implementation)
    {
        /** @var PerfDataReceiverHook $instance */
        $instance = new $implementation;
        $form = $instance->getConfigurationForm();
        if ($form instanceof Form) {
            $form->disableCsrf();
            $form->doNotCheckFormName();
        }
        // TODO: Clone Request, strip our values from request?
        $form->handleRequest($this->getRequest());
        $form->ensureAssembled();
        $this->add($form->getContent());
        foreach ($form->getElements() as $element) {
            $this->registerElement($element);
        }
    }

    protected function addButtons()
    {
        if ($this->getValue('implementation')) {
            $submit = new SubmitElement('submit', [
                'label' => $this->isNew() ? $this->translate('Create') : $this->translate('Store')
            ]);
            $this->addElement($submit);
            $this->setSubmitButton($submit);
            $deco = $submit->getWrapper();
            assert($deco instanceof DdDtDecorator);
            $back = new SubmitElement('btn_back', [
                'label' => $this->translate('Back')
            ]);
            $deco->dd()->add($back);
            $this->registerElement($back);
            if ($back->hasBeenPressed()) {
                // Hint: too late
                $this->setElementValue('implementation', null);
            }
        } else {
            $this->addElement('submit', 'submit', [
                'label' => $this->translate('Next')
            ]);
        }
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
}
