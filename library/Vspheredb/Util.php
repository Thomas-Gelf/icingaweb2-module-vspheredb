<?php

namespace Icinga\Module\Vspheredb;

use DateTime;
use InvalidArgumentException;
use stdClass;

class Util
{
    public static function createNestedObjects($objects)
    {
        foreach ($objects as $key => $object) {
            $objects[$key] = static::createNestedObject($object);
        }

        return $objects;
    }

    /**
     * Current Unix Timestamp as milliseconds since epoch
     *
     * @return int
     */
    public static function currentTimestamp()
    {
        $time = explode(' ', microtime());

        return (int) round(1000 * ((int) $time[1] + (float) $time[0]));
    }

    public static function timeStringToUnixTime($string)
    {
        return (new DateTime($string))->getTimestamp();
    }

    public static function timeStringToUnixMs($string)
    {
        $time = new DateTime($string);

        return (int) (1000 * $time->format('U.u'));
    }

    public static function uuidToBin($uuid)
    {
        // 401daca3-42cf-bd89-94a1-463e448ea8d1
        return hex2bin(str_replace('-', '', $uuid));
    }

    public static function uuidToHex($bin)
    {
        $hex = bin2hex($bin);

        return implode('-', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        ]);
    }

    public static function extractNumericId($textualId, $optional = false)
    {
        if (is_object($textualId) && property_exists($textualId, '_')) {
            $textualId = $textualId->_;
        }

        if (preg_match('~^.+?(\d+)$~', $textualId, $match)) {
            return (int) $match[1];
        } elseif ($optional) {
            return null;
        } else {
            throw new InvalidArgumentException('Got invalid id: %s', $textualId);
        }
    }

    protected static function createNestedObject($object)
    {
        $res = new stdClass();
        foreach ((array) $object as $key => $value) {
            $keys = explode('.', $key);
            static::setDeepValue($res, $keys, $value);
        }

        return $res;
    }

    /**
     * @param $object
     * @param $keys
     * @param $value
     */
    protected static function setDeepValue($object, $keys, $value)
    {
        $key = array_shift($keys);
        if (empty($keys)) {
            $object->$key = $value;
        } else {
            if (property_exists($object, $key)) {
                if (! is_object($object->$key)) {
                    throw new InvalidArgumentException(
                        'A key can be either object or scalar: %s'
                    );
                }
            } else {
                $object->$key = new stdClass();
            }
            static::setDeepValue($object->$key, $keys, $value);
        }
    }
}
