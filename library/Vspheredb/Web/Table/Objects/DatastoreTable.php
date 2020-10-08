<?php

namespace Icinga\Module\Vspheredb\Web\Table\Objects;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\DbObject\Datastore;
use Icinga\Module\Vspheredb\Web\Widget\DatastoreUsage;
use Icinga\Util\Format;

class DatastoreTable extends ObjectsTable
{
    protected function initialize()
    {
        $this->addAttributes(['class' => 'datastores-table']);
        $this->addAvailableColumns([
            $this->createOverallStatusColumn(),
            $this->createColumn('object_name', $this->translate('Name'), [
                'overall_status'       => 'o.overall_status',
                'object_name'          => 'o.object_name',
                'uuid'                 => 'o.uuid',
                'capacity'             => 'ds.capacity',
                'free_space'           => 'ds.free_space',
                'uncommitted'          => 'ds.uncommitted',
                'free_space_percent'   => '(ds.free_space / ds.capacity) * 100',
                'uncommitted_percent'  => '(ds.uncommitted / ds.capacity) * 100',
            ])->setRenderer(function ($row) {
                if (in_array('overall_status', $this->getChosenColumnNames())) {
                    $result = [];
                } else {
                    $statusRenderer = $this->overallStatusRenderer();
                    $result = [$statusRenderer($row)];
                }
                $title = sprintf(
                    // '%d VM(s), %s of %s used, %s uncommitted',
                    '%s of %s used, %s uncommitted',
                    // $row->cnt_vm,
                    $this->formatBytesPercent($row, 'free_space'),
                    Format::bytes($row->capacity, Format::STANDARD_IEC),
                    $this->formatBytesPercent($row, 'uncommitted')
                );

                $result[] = Link::create(
                    $row->object_name,
                    'vspheredb/datastore',
                    ['uuid' => bin2hex($row->uuid)],
                    ['title' => $title]
                );

                return $result;
            }),
            $this->createColumn(
                'multiple_host_access',
                $this->translate('Multiple Hosts'),
                'ds.multiple_host_access'
            )->setRenderer(function ($row) {
                return $row->multiple_host_access === 'y' ? $this->translate('Yes') : $this->translate('No');
            }),
            $this->createColumn('free_space', $this->translate('Free'), 'ds.free_space')
                ->setRenderer(function ($row) {
                    return Format::bytes($row->free_space, Format::STANDARD_IEC);
                }),
            $this->createColumn('free_space_percent', $this->translate('Free (%)'), [
                'free_space_percent'  => '(ds.free_space / ds.capacity) * 100'
            ])->setRenderer(function ($row) {
                return $this->formatPercent($row->free_space_percent);
            }),
            $this->createColumn('uncommitted', $this->translate('Uncommitted'), 'ds.uncommitted')
                ->setRenderer(function ($row) {
                    return Format::bytes($row->uncommitted, Format::STANDARD_IEC);
                }),
            $this->createColumn('uncommitted_percent', $this->translate('Uncommitted (%)'), [
                'uncommitted_percent'  => '(ds.uncommitted / ds.capacity) * 100'
            ])->setRenderer(function ($row) {
                return $this->formatPercent($row->uncommitted_percent);
            }),
            $this->createColumn('size', $this->translate('Size'), 'ds.capacity')
                ->setRenderer(function ($row) {
                    return Format::bytes($row->capacity, Format::STANDARD_IEC);
                }),
            $this->createColumn('cnt_vms', $this->translate('VMs'), [
                'cnt_vms' => 'COALESCE(vdu.cnt_vms, 0)',
            ])->setDefaultSortDirection('DESC'),
            $this->createColumn('usage', $this->translate('Usage'), [
                'uuid' => 'o.uuid'
            ])->setRenderer(function ($row) {
                /** @var Db $connection */
                $connection = $this->connection();
                $usage = new DatastoreUsage(Datastore::load($row->uuid, $connection));
                $usage->getAttributes()->add('class', 'compact');
                $usage->loadAllVmDisks()->addFreeDatastoreSpace();

                return $usage;
            })->setSortExpression(
                '1 - (ds.free_space / ds.capacity)'
            )->setDefaultSortDirection('DESC'),
        ]);
    }

    public function getDefaultColumnNames()
    {
        return [
            'object_name',
            'free_space',
            'size',
            'usage',
        ];
    }

    protected function formatBytesPercent($row, $name)
    {
        $bytes = $row->$name;
        $percent = $row->{"${name}_percent"};
        return sprintf(
            '%s (%s)',
            Format::bytes($bytes, Format::STANDARD_IEC),
            $this->formatPercent($percent)
        );
    }

    protected function formatPercent($value)
    {
        return sprintf('%0.2f%%', $value);
    }

    public function sortBy($columns)
    {
        parent::sortBy($columns);

        $this->getQuery()->order('object_name');

        return $this;
    }

    public function prepareQuery()
    {
        $query = $this->db()->select()->from(
            ['o' => 'object'],
            $this->getRequiredDbColumns()
        )->join(
            ['ds' => 'datastore'],
            'o.uuid = ds.uuid',
            []
        )->group('o.uuid');

        if ($this->hasColumn('cnt_vms')) {
            $vduQuery = $this->db()->select()->from('vm_datastore_usage', [
                'cnt_vms' => 'COUNT(*)',
                'ds_uuid' => 'datastore_uuid',
            ])->group('datastore_uuid');
            $query->joinLeft(
                ['vdu' => $vduQuery],
                'vdu.ds_uuid = o.uuid',
                []
            );
        }
        if ($this->parentUuids) {
            $query->where('o.parent_uuid IN (?)', $this->parentUuids);
        }
        if ($this->filterVCenter) {
            $query->where('o.vcenter_uuid = ?', $this->filterVCenter->getUuid());
        }

        return $query;
    }
}
