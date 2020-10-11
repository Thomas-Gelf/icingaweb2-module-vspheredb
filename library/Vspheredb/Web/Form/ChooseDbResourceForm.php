<?php

namespace Icinga\Module\Vspheredb\Web\Form;

use Exception;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\Widget\Hint;
use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\Db\Migrations;
use Icinga\Web\Notification;
use ipl\Html\Html;

class ChooseDbResourceForm extends Form
{
    use TranslationHelper;

    private $config;

    private $storeConfigLabel;

    private $createDbLabel;

    private $migrateDbLabel;

    protected function assemble()
    {
        $this->storeConfigLabel = $this->translate('Store configuration');

        $this->addResourceConfigElements();

        if (!$this->config()->get('db', 'resource')
            || ($this->config()->get('db', 'resource') !== $this->getResourceName())) {
            return;
        }

        if ($this->hasBeenSubmitted()) {
            // Do not hinder the form from being stored
            return;
        }
        if ($resourceName = $this->getResourceName()) {
            try {
                $resourceConfig = ResourceFactory::getResourceConfig($resourceName);
                if (! isset($resourceConfig->charset)
                    || $resourceConfig->charset !== 'utf8mb4'
                ) {
                    $this->getElement('resource')
                        ->addMessage('Please change the encoding for the database to utf8mb4');
                }

                $resource = $this->getResource();
                $db = $resource->getDbAdapter();
            } catch (Exception $e) {
                $this->getElement('resource')
                    ->addMessage('Resource failed: ' . $e->getMessage());

                return;
            }

            try {
                $db->fetchOne('SELECT 1');
            } catch (Exception $e) {
                $this->getElement('resource')
                    ->addMessage('Could not connect to database: ' . $e->getMessage());

                $this->add(Hint::info($this->translate(
                    'Please make sure that your database exists and your user has'
                    . ' been granted enough permissions'
                )));
            }
        }
    }

    protected function addResourceConfigElements()
    {
        $config = $this->config();
        $resources = $this->enumResources();

        $this->addElement('select', 'resource', array(
            'required'      => true,
            'label'         => $this->translate('DB Resource'),
            'multiOptions'  => [null => $this->translate('- please choose -')] + $resources,
            'class'         => 'autosubmit',
            'value'         => $config->get('db', 'resource')
        ));

        if (!$this->getResourceName()) {
            $this->add(Hint::info($this->translate(
                'No database resource has been configured yet. Please choose a'
                . ' resource to complete your config'
            )));
        }

        if (empty($resources)) {
            $this->add(Hint::info(Html::sprintf(
                $this->translate('Please click %s to create new MySQL/MariaDB resource'),
                Link::create(
                    $this->translate('here'),
                    'config/resource',
                    null,
                    ['data-base-target' => '_main']
                )
            )));
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->storeConfigLabel
        ]);
    }

    /**
     * @return bool
     */
    protected function storeResourceConfig()
    {
        $config = $this->config();
        $value = $this->getValue('resource');

        $config->setSection('db', ['resource' => $value]);

        try {
            $config->saveIni();
            // $this->setSuccessMessage
            Notification::success($this->translate('Configuration has been stored'));

            return true;
        } catch (Exception $e) {
            $this->getElement('resource')->addMessage(
                sprintf(
                    $this->translate(
                        'Unable to store the configuration to "%s". Please check'
                        . ' file permissions or manually store the content shown below'
                    ),
                    $config->getConfigFile()
                )
            );
            $submit = $this->getSubmitButton();
            $this->remove($submit);
            $this->addElement('html', 'hint', [
                'label'   => $this->translate('File content'),
                'content' => Html::tag('pre', null, (string) $config)
            ]);
            // Hint: re-adding the element shows two of them, and if
            // you clone it first it seems to be wrapped twice.
            $this->addElement('submit', 'submit', [
                'label' => $submit->getButtonLabel()
            ]);

            return false;
        }
    }

    public function onSuccess()
    {
        if ($this->getSubmitLabel() === $this->storeConfigLabel) {
            if ($this->storeResourceConfig()) {
                parent::onSuccess();
            } else {
                return;
            }
        }

        if ($this->getSubmitLabel() === $this->createDbLabel
            || $this->getSubmitLabel() === $this->migrateDbLabel) {
            $this->migrations()->applyPendingMigrations();
        }
    }

    protected function getSubmitLabel()
    {
        return $this->getSubmitButton()->getButtonLabel();
    }

    protected function getResourceName()
    {
        if ($this->hasBeenSent()) {
            $resource = $this->getValue('resource');
            $resources = $this->enumResources();
            if (in_array($resource, $resources)) {
                return $resource;
            } else {
                return null;
            }
        } else {
            return $this->config()->get('db', 'resource');
        }
    }

    public function getDb()
    {
        return Db::fromResourceName($this->getResourceName());
    }

    protected function getResource()
    {
        return ResourceFactory::create($this->getResourceName());
    }

    /**
     * @return Migrations
     */
    protected function migrations()
    {
        return new Migrations($this->getDb());
    }

    public function setModuleConfig(Config $config)
    {
        $this->config = $config;

        return $this;
    }

    protected function config()
    {
        if ($this->config === null) {
            $this->config = Config::module('vspheredb');
        }

        return $this->config;
    }

    protected function enumResources()
    {
        // return [];
        $resources = [];
        $allowed = ['mysql'];

        foreach (ResourceFactory::getResourceConfigs() as $name => $resource) {
            if ($resource->get('type') === 'db' && in_array($resource->get('db'), $allowed)) {
                $resources[$name] = $name;
            }
        }

        ksort($resources);

        return $resources;
    }
}
