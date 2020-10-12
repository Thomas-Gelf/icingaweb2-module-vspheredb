<?php

namespace Icinga\Module\Vspheredb\PerformanceData\InfluxDb;

use InvalidArgumentException;

class InfluxDbQueryResult
{
    public static function extractColumn($result, $idx = 0)
    {
        if (! isset($result->columns)) {
            print_r($result);
            exit;
        }
        $idx = static::getNumericColumn($idx, $result->columns);
        $column = [];
        foreach ($result->values as $row) {
            $column[] = $row[$idx];
        }

        return $column;
    }

    protected static function getNumericColumn($name, $cols)
    {
        if (\is_int($name)) {
            if (isset($cols[$name])) {
                return $name;
            }
        }
        if (\is_string($name)) {
            foreach ($cols as $idx => $alias) {
                if ($name === $alias) {
                    return $idx;
                }
            }
        }

        throw new InvalidArgumentException("There is no '$name' column in the result");
    }

    protected static function extractPairs($result, $keyColumn = 0, $valueColumn = 1)
    {
        $keyColumn = static::getNumericColumn($keyColumn, $result->columns);
        $valueColumn = static::getNumericColumn($valueColumn, $result->columns);
        $pairs = [];
        foreach ($result->values as $row) {
            $pairs[$row[$keyColumn]] = $row[$valueColumn];
        }

        return $pairs;
    }

    protected static function transformResultsTable($table)
    {
        // $table->name = 'databases'
        $cols = $table->columns;
        $values = [];
        foreach ($table->values as $row) {
            $values[] = (object) \array_combine($cols, $row);
        }

        return $values;
    }
}
