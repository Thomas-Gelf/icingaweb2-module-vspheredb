<?php

namespace Icinga\Module\Vspheredb\DbObject;

use Exception;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Vspheredb\Db\DbObject as VspheredbDbObject;
use Icinga\Module\Vspheredb\Api;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\MappedClass\ElementDescription;
use Icinga\Module\Vspheredb\PropertySet\PropertySet;
use Icinga\Module\Vspheredb\SelectSet\SelectSet;
use Icinga\Module\Vspheredb\Util;
use Icinga\Module\Vspheredb\VmwareDataType\ManagedObjectReference;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

abstract class BaseDbObject extends VspheredbDbObject
{
    /** @var Db $connection Exists in parent, but IDEs need a berrer hint */
    protected $connection;

    protected $keyName = 'id';

    /** @var ManagedObject */
    private $object;

    protected $propertyMap = [];

    protected $objectReferences = [];

    protected $booleanProperties = [];

    protected $dateTimeProperties = [];

    /**
     * @param array $filter
     * @param Db $connection
     * @return static
     * @throws NotFoundError
     */
    public static function findOneBy($filter, Db $connection)
    {
        $result = static::findBy($filter, $connection);

        if (empty($result)) {
            throw new NotFoundError('No object found for given filter');
        }

        if (count($result) > 1) {
            throw new NotFoundError('More than one object found for given filter');
        }

        $object = new static();
        $object->setConnection($connection)->setDbProperties($result[0]);

        return $object;
    }

    /**
     * @param array $filter
     * @param Db $connection
     * @return array
     */
    private static function findBy($filter, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $table = static::create()->getTableName();
        $select = $db->select()->from($table);

        foreach ($filter as $key => $value) {
            if ($key === 'object_name') {
                $type = static::getType();
                $select->join(
                    'object',
                    $db->quoteInto("object.uuid = $table.uuid AND object.object_type = ?", $type),
                    []
                );
            }
            if ($value === null) {
                $select->where($key);
            } elseif (strpos($key, '?') === false) {
                $select->where("$key = ?", $value);
            } else {
                $select->where($key, $value);
            }
        }

        return $db->fetchAll($select);
    }

    public static function listNonGreenObjects(Db $connection)
    {
        $db = $connection->getDbAdapter();
        $type = static::getType();
        $select = $db->select()
            ->from('object', ['uuid', 'overall_status', 'object_name'])
            ->where('object_type = ?', $type)
            ->where('overall_status != ?', 'green')
            ->order("CASE overall_status WHEN 'gray' THEN 1 WHEN 'yellow' THEN 2 WHEN 'red' THEN 3 END DESC")
            ->order('object_name');

        $result = [];
        foreach ($db->fetchAll($select) as $row) {
            $status = $row->overall_status;
            if (isset($result[$status])) {
                $result[$status][$row->uuid] = $row->object_name;
            } else {
                $result[$status] = [$row->uuid => $row->object_name];
            }
        }

        return $result;
    }

    public function isObjectReference($property)
    {
        return $property === 'parent' || in_array($property, $this->objectReferences);
    }

    public function isBooleanProperty($property)
    {
        return in_array($property, $this->booleanProperties);
    }

    public function isDateTimeProperty($property)
    {
        return in_array($property, $this->dateTimeProperties);
    }

    /**
     * @param $value
     * @return null|string
     */
    protected function makeBooleanValue($value)
    {
        if ($value === true) {
            return 'y';
        } elseif ($value === false) {
            return 'n';
        } elseif ($value === null) {
            return null;
        } else {
            throw new InvalidArgumentException(
                'Boolean expected, got %s',
                var_export($value, 1)
            );
        }
    }

    /**
     * @param $properties
     * @param VCenter $vCenter
     * @return $this
     */
    public function setMapped($properties, VCenter $vCenter)
    {
        if ($this->hasProperty('vcenter_uuid')) {
            $this->set('vcenter_uuid', $vCenter->getUuid());
        }
        foreach ($this->propertyMap as $key => $property) {
            if (property_exists($properties, $key)) {
                $value = $properties->$key;
                if ($this->isObjectReference($property)) {
                    $value = $this->createUuidForMoref($value, $vCenter);
                } elseif ($this->isBooleanProperty($property)) {
                    $value = $this->makeBooleanValue($value);
                } elseif ($this->isDateTimeProperty($property)) {
                    $value = Util::timeStringToUnixMs($value);
                } elseif ($value instanceof ElementDescription) {
                    // Like HostNumericSensorInfo.healthState
                    // Hint: lcfirst -> issue #179, vSphere 7 ships 'Green' instead of 'green',
                    //       at least on that specific system
                    $value = \lcfirst($value->key);
                }
                if ($property === 'customValues') {
                    if ($vCenter->getApi()->hasCustomFieldsManager()) {
                        $value = $vCenter->getApi()->customFieldsManager()->mapFields($value);
                    } else {
                        continue;
                    }
                }

                $this->set($property, $value);
            } else {
                $this->set($property, null);
            }
        }

        return $this;
    }

    protected function createUuidForMoref($value, VCenter $vCenter)
    {
        if (empty($value)) {
            return null;
        } elseif ($value instanceof ManagedObjectReference) {
            return $vCenter->makeBinaryGlobalUuid($value->_);
        } else {
            return $vCenter->makeBinaryGlobalUuid($value);
        }
    }

    /**
     * @return ManagedObject
     * @throws \Icinga\Exception\NotFoundError
     */
    public function object()
    {
        if ($this->object === null) {
            $this->object = ManagedObject::load($this->get('uuid'), $this->connection);
        }

        return $this->object;
    }

    /**
     * @param Api $api
     * @return array
     */
    public static function fetchAllFromApi(Api $api)
    {
        return $api->propertyCollector()->collectObjectProperties(
            new PropertySet(static::getType(), static::getDefaultPropertySet()),
            static::getSelectSet()
        );
    }

    /**
     * @return SelectSet
     */
    public static function getSelectSet()
    {
        $class = '\\Icinga\\Module\\Vspheredb\\SelectSet\\' . static::getType() . 'SelectSet';
        return new $class;
    }

    public static function getType()
    {
        $parts = explode('\\', get_class(static::dummyObject()));
        return end($parts);
    }

    protected static function getDefaultPropertySet()
    {
        return array_keys(static::dummyObject()->propertyMap);
    }

    protected static function dummyObject()
    {
        return static::create();
    }

    /**
     * @param VCenter $vCenter
     * @param LoggerInterface $logger
     * @param BaseDbObject[] $dbObjects
     * @param \stdClass[] $newObjects
     * @throws \Icinga\Module\Vspheredb\Exception\DuplicateKeyException
     */
    protected static function storeSync(VCenter $vCenter, LoggerInterface $logger, &$dbObjects, &$newObjects)
    {
        $type = static::getType();
        $vCenterUuid = $vCenter->getUuid();
        $db = $vCenter->getConnection();
        $dba = $vCenter->getDb();
        $logger->debug("Ready to store $type");
        $dba->beginTransaction();
        try {
            $modified = 0;
            $created = 0;
            $dummy = static::dummyObject();
            $newUuids = [];

            $new = [];
            foreach ($newObjects as $object) {
                $uuid = $vCenter->makeBinaryGlobalUuid($object->id);

                $newUuids[$uuid] = $uuid;
                if (array_key_exists($uuid, $dbObjects)) {
                    $dbObject = $dbObjects[$uuid];
                } else {
                    $dbObjects[$uuid] = $dbObject = static::create([
                        'uuid' => $uuid,
                        'vcenter_uuid' => $vCenterUuid
                    ], $db);
                }
                $dbObject->setMapped($object, $vCenter);
                if ($dbObject->hasBeenLoadedFromDb()) {
                    if ($dbObject->hasBeenModified()) {
                        $dbObject->store();
                        $modified++;
                    }
                } else {
                    $new[] = $dbObject;
                }
            }

            $del = [];
            foreach ($dbObjects as $existing) {
                $uuid = $existing->get('uuid');
                if (!array_key_exists($uuid, $newUuids)) {
                    $del[] = $uuid;
                }
            }

            if (!empty($del)) {
                $dba->delete(
                    $dummy->getTableName(),
                    $dba->quoteInto('uuid IN (?)', $del)
                );
            }
            foreach ($new as $dbObject) {
                $dbObject->store();
                $created++;
            }
            $dba->commit();
        } catch (Exception $error) {
            $logger->error('Storing Sync failed: ' . $error->getMessage());
            try {
                $dba->rollBack();
                /** @var $dba \Zend_Db_Adapter_Pdo_Abstract */
            } catch (\Exception $e) {
                // There is nothing we can do.
            }

            throw $error;
        }
        $logger->debug(sprintf(
            "$type: %d new, %d modified, %d deleted (got %d from API)",
            $created,
            $modified,
            count($del),
            count($newObjects)
        ));
    }

    public static function onStoreSync(Db $db)
    {
    }

    /**
     * @param VCenter $vCenter
     * @return static[]
     */
    public static function loadAllForVCenter(VCenter $vCenter)
    {
        $dummy = new static();

        return static::loadAll(
            $vCenter->getConnection(),
            $vCenter->getDb()
                ->select()
                ->from($dummy->getTableName())
                ->where('vcenter_uuid = ?', $vCenter->get('uuid')),
            $dummy->keyName
        );
    }

    /**
     * @param VCenter $vCenter
     * @param LoggerInterface $logger
     * @throws NotFoundError
     * @throws \Icinga\Module\Vspheredb\Exception\DuplicateKeyException
     * @throws \Zend_Db_Exception
     */
    public static function syncFromApi(VCenter $vCenter, LoggerInterface $logger)
    {
        $type = static::getType();
        $db = $vCenter->getConnection();
        $logger->debug("Loading existing $type from DB");
        $existing = static::loadAllForVCenter($vCenter);
        $logger->debug(sprintf("Got %d existing $type", count($existing)));
        $objects = static::fetchAllFromApi($vCenter->getApi($logger));
        $logger->debug(sprintf("Got %d $type from VCenter", count($objects)));
        static::storeSync($vCenter, $logger, $existing, $objects);
        static::onStoreSync($db);
    }
}
