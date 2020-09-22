<?php

namespace Icinga\Module\Vspheredb\Web\Controller;

use gipfl\IcingaWeb2\Link;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use ipl\Html\Html;
use Icinga\Module\Vspheredb\PathLookup;
use Icinga\Module\Vspheredb\Web\Controller;
use Icinga\Module\Vspheredb\Web\Table\Objects\ObjectsTable;

class ObjectsController extends Controller
{
    protected $otherTabActions = [];

    /** @var PathLookup */
    protected $pathLookup;

    protected function linkBackToOverview($type)
    {
        $this->actions()->add(
            Link::create(
                $this->translate('back'),
                'vspheredb/overview',
                ['type' => $type],
                [
                    'data-base-target' => '_main',
                    'class' => 'icon-left-big'
                ]
            )
        );

        return $this;
    }

    protected function addTreeViewToggle()
    {
        if ($this->params->get('render') === 'tree') {
            $this->actions()->add(
                Link::create(
                    $this->translate('Table'),
                    $this->url()->without('render'),
                    null,
                    ['class' => 'icon-sitemap']
                )
            );
        } else {
            $this->actions()->add(
                Link::create(
                    $this->translate('Tree'),
                    $this->url()->with('render', 'tree'),
                    null,
                    ['class' => 'icon-sitemap']
                )
            );
        }
    }

    protected function eventuallyFilterByParent(ObjectsTable $table, $url, $defaultTitle = null)
    {
        $parent = hex2bin($this->params->get('uuid'));

        if ($parent) {
            $lookup = $this->pathLookup();
            $name = $lookup->getObjectName($parent);
            $uuids = $lookup->listFoldersBelongingTo($parent);
            if ($name) {
                $this->addTitle($name);
            } else {
                $this->addTitle($defaultTitle);
            }
            if ($this->params->get('showDescendants')) {
                $table->filterParentUuids($uuids);
            } else {
                $table->filterParentUuids([$parent]);
            }
            $this->addPathTo($parent, $url);
        } elseif ($defaultTitle !== null) {
            $this->addTitle($defaultTitle);
        }
    }

    protected function eventuallyFilterByVCenter(ObjectsTable $table)
    {
        if ($hex = $this->params->get('vcenter')) {
            $table->filterVCenter(VCenter::loadWithHexUuid($hex, $this->db()));
        }
    }

    protected function showTable(ObjectsTable $table, $url, $defaultTitle = null)
    {
        $this->eventuallyFilterByParent($table, $url, $defaultTitle);
        $this->eventuallyFilterByVCenter($table);
        $this->renderTableWithCount($table, $defaultTitle);

        return $this;
    }

    protected function renderTableWithCount(ObjectsTable $table, $title = null)
    {
        $total = count($table);
        $table->renderTo($this);
        if ($title === null) {
            return;
        }
        $found = count($table);
        if ($total === $found) {
            $this->content()->prepend(sprintf('%d %s', $total, $title));
        } else {
            $this->content()->prepend(sprintf('%d out of %d %s', $found, $total, $title));
        }
    }

    protected function addPathTo($parent, $url)
    {
        $lookup = $this->pathLookup();
        $path = Html::tag('span', ['class' => 'dc-path']);
        $first = true;
        foreach ($lookup->getObjectNames($lookup->listPathTo($parent)) as $uuid => $name) {
            if ($first) {
                $first = false;
            } else {
                $path->add(' > ');
            }
            $path->add(Link::create($name, $url, [
                'uuid'            => bin2hex($uuid),
                'showDescendants' => true,
            ]));
        }

        $this->content()->add($path);
    }

    protected function handleTabs()
    {
        $action = $this->getRequest()->getControllerName();
        if (isset($this->otherTabActions[$action])) {
            $action = $this->otherTabActions[$action];
        }

        $this->tabs()->add('vms', [
            'label'     => $this->translate('Virtual Machine'),
            'url'       => 'vspheredb/vms',
        ])->add('hosts', [
            'label'     => $this->translate('Hosts'),
            'url'       => 'vspheredb/hosts',
        ])->add('datastores', [
            'label'     => $this->translate('Datastores'),
            'url'       => 'vspheredb/datastores',
        ])
        // ->add('switches', [
        //     'label'     => $this->translate('Switches'),
        //     'url'       => 'vspheredb/switches',
        // ])
        ->activate($action);
    }

    protected function pathLookup()
    {
        if ($this->pathLookup === null) {
            $this->pathLookup = new PathLookup($this->db());
        }

        return $this->pathLookup;
    }
}
