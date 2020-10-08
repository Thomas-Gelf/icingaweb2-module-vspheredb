<?php

namespace Icinga\Module\Vspheredb\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\Form\Element\TextWithActionButton;
use Icinga\Module\Vspheredb\PerformanceData\InfluxDb\InfluxDbConnectionFactory;
use Icinga\Module\Vspheredb\PerformanceData\InfluxDb\InfluxDbConnectionV1;
use Icinga\Module\Vspheredb\PerformanceData\InfluxDb\InfluxDbConnectionV2;
use ipl\Html\FormElement\BaseFormElement;
use ipl\Html\FormElement\SelectElement;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\Promise;
use function Clue\React\Block\await;
use function React\Promise\resolve;

class InfluxDbConnectionForm extends Form
{
    use TranslationHelper;

    const INFLUXDB_MIN_SUPPORTED_VERSION = '1.6.0';

    /** @var LoopInterface */
    protected $loop;

    /** @var InfluxDbConnectionV1|InfluxDbConnectionV2 */
    protected $influxDb;

    /** @var array|null|false */
    protected $dbList;

    protected $detectedApiVersion;

    protected $influxDbVersion;

    protected $baseUrlElement;

    public function assemble()
    {
        $this->addHidden('checked_url', ['ignore' => true]);
        $this->addHidden('checked_api_version', ['ignore' => true]);
        $this->baseUrlElement = new TextWithActionButton('base_url', [
            'label'       => $this->translate('Base URL'),
            'description' => $this->translate('InfluxDB base URL, like http://influxdb.example.com:8086'),
            'required'    => true,
        ], [
            'label' => $this->translate('Überprüfen'),
            'title' => $this->translate('Attempt to establish a connection to your InfluxDB instance')
        ]);
        $this->baseUrlElement->addToForm($this);
        $this->addElement('select', 'api_version', [
            'label' => $this->translate('API Version'),
            'class' => 'autosubmit',
            'description' => $this->translate(
                'InfluxDB API version, autodetect should work fine'
            ),
            'options' => [
                null => $this->translate('Autodetect'),
                'v1' => 'v1',
                'v2' => 'v2',
            ],
        ]);
        $this->appendVersionInformation($this->getDetectedApiVersion(), $this->getInfluxDbVersion());
        $this->addCredentials();
        $this->addDbSelection();
    }

    protected function addCredentials()
    {
        if ($this->getApiVersion() === 'v2') {
            $this->addV2Credentials();
        } else {
            $this->addV1Credentials();
        }

        return $this;
    }

    protected function getInfluxDb()
    {
        $baseUrl = $this->getValue('base_url');

        switch ($this->getDetectedApiVersion()) {
            case 'v1':
                return new InfluxDbConnectionV1(
                    $this->loop(),
                    $baseUrl,
                    $this->getValue('username'),
                    $this->getValue('password')
                );
            case 'v2':
                return new InfluxDbConnectionV2(
                    $this->loop(),
                    $baseUrl,
                    $this->getValue('org'),
                    $this->getValue('token')
                );
            default:
                return null;
        }
    }

    protected function getDbList()
    {
        if ($this->dbList === null) {
            $this->refreshDbList();
        }

        return $this->dbList;
    }

    protected function refreshDbList()
    {
        try {
            $influxDb = $this->getInfluxDb();
            $this->dbList = $this->fetchDbList($influxDb);
        } catch (\Exception $e) {
            // Hint: we no longer refresh if it's false
            $this->dbList = false;
        }
    }

    protected function getDetectedApiVersion()
    {
        if ($this->detectedApiVersion === null) {
            $this->detectedApiVersion = $this->getApiVersionForVersionString(
                $this->getInfluxDbVersion()
            );
        }

        return $this->detectedApiVersion;
    }

    protected function getApiVersion()
    {
        return $this->getValue('api_version', $this->getDetectedApiVersion());
    }

    protected function getInfluxDbVersion()
    {
        if ($this->influxDbVersion === null) {
            $element = $this->getUrlElement();
            if ($element->getButton()->hasBeenPressed()) {
                $this->influxDbVersion = $this->detectInfluxDbVersion($this->getValue('base_url'));
            } elseif ($this->autodetectIsUpToDate()) {
                $this->markUrlAsValidated();
                $this->influxDbVersion = $this->getValue('checked_api_version');
            }
        }

        return $this->influxDbVersion;
    }

    /**
     * @return TextWithActionButton
     */
    protected function getUrlElement()
    {
        return $this->baseUrlElement;
    }

    protected function markUrlAsValidated()
    {
        $this
            ->getUrlElement()
            ->getElement()
            ->addAttributes(['class' => 'validated']);

        return $this;
    }

    protected function autodetectIsUpToDate()
    {
        $baseUrl = $this->getValue('base_url');

        return $baseUrl && $this->getValue('checked_url') === $baseUrl;
    }

    protected function appendVersionInformation($apiVersion, $detectedVersion)
    {
        $element = $this->getElement('api_version');
        assert($element instanceof SelectElement);
        $autoOption = $element->getOption(null);
        $autoOption->setLabel(\sprintf(
            $this->translate('Autodetect: %s API, Version is %s'),
            $apiVersion,
            $detectedVersion
        ));
    }

    protected function addV1Credentials()
    {
        $this->addElement('text', 'username', [
            'label'       => $this->translate('Username'),
        ]);
        $this->addElement('password', 'password', [
            'label'       => $this->translate('Password'),
            'required'    => $this->hasElementValue('username'),
        ]);
    }

    protected function addV2Credentials()
    {
        $this->addElement('text', 'token', [
            'label'       => $this->translate('Token'),
            'description' => $this->translate('InfluxDB Token (InfluxDB -> Data -> Tokens'),
            'required'    => true,
        ]);
        $this->addElement('text', 'org', [
            'label'       => $this->translate('Organisation'),
            'description' => $this->translate('InfluxDB Token (InfluxDB -> Data -> Tokens'),
            'required'    => true,
        ]);
    }

    protected function detectInfluxDbVersion($baseUrl)
    {
        try {
            $promise = InfluxDbConnectionFactory::create($baseUrl, $this->loop())
                ->then(function ($connection) {
                    return $connection->getVersion();
                });
            $version = await($promise, $this->loop(), 5);
            if ($this->versionIsFine($version)) {
                $this->setCheckedApiVersionFor($baseUrl, $version);
                $this->markUrlAsValidated();
                return $version;
            } else {
                throw new \Exception("Version $version is not supported");
            }
        } catch (\Exception $e) {
            $this->triggerElementError('base_url', $e->getMessage());
            return false;
        }
    }

    protected function setCheckedApiVersionFor($baseUrl, $version)
    {
        $this->getElement('checked_url')->setValue($baseUrl);
        $this->setElementValue('checked_api_version', $version);
    }

    protected function createRequestedDb(BaseFormElement $element, TextWithActionButton $action)
    {
        $name = $element->getValue();
        try {
            await($this->influxDb()->createDatabase($name), $this->loop());
            $this->remove($action->getButton());
            $this->remove($action->getElement());
            $list = $this->getDbList();
            $dbOptions = $this->getDbOptions();
            if ($element instanceof SelectElement) {
                $element->setOptions($dbOptions);
            }
            if (\in_array($name, $dbOptions)) {
                $element->setValue($name);
            } else {
                $this->triggerElementError(
                    $element,
                    $this->translate('There is no such DB/Bucket: "%s"'),
                    $name
                );
            }
        } catch (\Exception $e) {
            $this->triggerElementError($element, $e->getMessage());
        }
    }

    protected function getDbOptions()
    {
        return [null => $this->translate('Please choose')]
        + \array_combine($this->dbList, $this->dbList)
        + ['_new' => ' -> ' . $this->translate('Create a new Database')];
    }

    protected function fetchDbList($influxDb)
    {
        if ($influxDb === null) {
            return null;
        }

        return \array_filter(
            await($influxDb->listDatabases(), $this->loop(), 5),
            function ($value) {
                return $value[0] !== '_';
            }
        );
    }

    protected function versionIsFine($version)
    {
        return \version_compare($version, static::INFLUXDB_MIN_SUPPORTED_VERSION, 'ge');
    }

    protected function getApiVersionForVersionString($version)
    {
        if ($version === null) {
            return null;
        }

        return \version_compare($version, '1.999.999', 'gt') ? 'v2' : 'v1';
    }

    protected function influxDb()
    {
        if ($this->influxDb instanceof Promise) {
            return $this->influxDb;
        } elseif ($this->influxDb === null) {
            $deferred = new Deferred();
            InfluxDbConnectionFactory::create(
                $this->getElement('base_url')->getValue(),
                $this->loop()
            )->then(function ($connection) use ($deferred) {
                if ($connection instanceof InfluxDbConnectionV2) {
                    if ($this->hasElementValue('token')) {
                        $connection->setToken($this->getElementValue('token'));
                    }
                    if ($this->hasElementValue('org')) {
                        $connection->setToken($this->getElementValue('org'));
                    }
                }

                $this->influxDb = $connection;

                $deferred->resolve($this->influxDb);
            });

            $this->influxDb = $deferred->promise();
        }

        return resolve($this->influxDb);
    }

    protected function addDbSelection()
    {
        if ($this->getSentValue('dbname') === '_new') {
            $elDbName = $this->createElement('hidden', 'dbname');
            $this->registerElement($elDbName);
            $this->prepend($elDbName);
        } elseif ($this->dbList) {
            $elDbName = $this->createElement('select', 'dbname', [
                'label'       => $this->translate('Database'),
                'description' => $this->translate('InfluxDB database name'),
                'class'       => 'autosubmit',
                'options'     => $this->getDbOptions()
            ]);
            $this->addElement($elDbName);
        } else {
            $elDbName = $this->createElement('text', 'dbname', [
                'label'       => $this->translate('Database'),
                'description' => $this->translate('InfluxDB database name'),
                'required'    => true,
            ]);
            $this->addElement($elDbName);
        }
        if ($this->getValue('dbname') === '_new') {
            $action = new TextWithActionButton('new_dbname', [
                'label'       => $this->translate('New Database'),
                'description' => $this->translate('New InfluxDB database name'),
                'required'    => true,
            ], [
                'label' => $this->translate('Create'),
                'title' => $this->translate('Create a new InfluxDB database')
            ]);
            $action->addToForm($this);
            if ($action->getButton()->hasBeenPressed()) {
                assert($elDbName instanceof BaseFormElement);
                $this->createRequestedDb($elDbName, $action);
            }
        }

        return $this;
    }

    protected function loop()
    {
        if ($this->loop === null) {
            $this->loop = Factory::create();
        }

        return $this->loop;
    }
}
