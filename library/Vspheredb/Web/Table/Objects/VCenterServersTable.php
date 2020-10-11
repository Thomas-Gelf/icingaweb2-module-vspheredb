<?php

namespace Icinga\Module\Vspheredb\Web\Table\Objects;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Vspheredb\Web\Table\BaseTable;
use Icinga\Module\Vspheredb\Web\Table\SimpleColumn;

class VCenterServersTable extends BaseTable
{
    protected function initialize()
    {
        $this->addAvailableColumns([
            (new SimpleColumn('server', $this->translate('Server'), [
                'id'       => 'vcs.id',
                'host'     => 'vcs.host',
                'username' => 'vcs.username',
                'scheme'   => 'vcs.scheme',
                'enabled'  => 'vcs.enabled',
            ]))->setRenderer(function ($row) {
                return Link::create(
                    $this->makeUrl($row),
                    'vspheredb/vcenter/server',
                    ['id' => $row->id]
                );
            })->setDefaultSortDirection('DESC'),
            (new SimpleColumn('vcenter', $this->translate('VCenter'), 'vc.name')),
        ]);
    }

    public function renderRow($row)
    {
        $tr = parent::renderRow($row);
        if ($row->enabled === 'n') {
            $tr->getAttributes()->add('class', 'disabled');
        }

        return $tr;
    }

    protected function makeUrl($row)
    {
        return sprintf(
            '%s://%s@%s',
            $row->scheme,
            $row->username,
            $row->host
        );
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['vcs' => 'vcenter_server'],
            $this->getRequiredDbColumns()
        )->joinLeft(
            ['vc' => 'vcenter'],
            // 'vc.instance_uuid = vcs.vcenter_uuid',
            'vc.id = vcs.vcenter_id',
            []
        );
    }
}
