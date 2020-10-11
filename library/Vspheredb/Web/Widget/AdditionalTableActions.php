<?php

namespace Icinga\Module\Vspheredb\Web\Widget;

use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Url;
use Icinga\Authentication\Auth;
use Icinga\Module\Vspheredb\Web\Table\BaseTable;

class AdditionalTableActions
{
    use TranslationHelper;

    /** @var Auth */
    protected $auth;

    /** @var Url */
    protected $url;

    /** @var BaseTable */
    protected $table;

    public function __construct(BaseTable $table, Auth $auth, Url $url)
    {
        $this->auth = $auth;
        $this->url = $url;
        $this->table = $table;
    }

    public function appendTo(HtmlDocument $parent)
    {
        $links = [];
        if (false && $this->hasPermission('vspheredb/admin')) {
            // TODO: not yet
            // $links[] = $this->createDownloadJsonLink();
        }
        if ($this->hasPermission('vspheredb/showsql')) {
            $links[] = $this->createShowSqlToggle();
        }
        if (! empty($links)) {
            $parent->add($this->moreOptions($links));
        }

        return $this;
    }

    protected function createDownloadJsonLink()
    {
        return Link::create(
            $this->translate('Download as JSON'),
            $this->url->with('format', 'json'),
            null,
            ['target' => '_blank']
        );
    }

    protected function createShowSqlToggle()
    {
        if ($this->url->getParam('format') === 'sql') {
            $link = Link::create(
                $this->translate('Hide SQL'),
                $this->url->without('format')
            );
        } else {
            $link = Link::create(
                $this->translate('Show SQL'),
                $this->url->with('format', 'sql')
            );
        }

        return $link;
    }

    protected function toggleColumnsOptions()
    {
        $links = [];
        $table = $this->table;
        $url = $this->url;

        $enabled = $url->getParam('columns');
        if ($enabled === null) {
            $enabled = $table->getChosenColumnNames();
        } else {
            $links[] = Link::create(
                $this->translate('Reset'),
                $url->without('columns'),
                null,
                ['class' => 'icon-reply']
            );
            $enabled = preg_split('/,/', $enabled, -1, PREG_SPLIT_NO_EMPTY);
            $table->chooseColumns($enabled);
        }

        $all = [];
        $disabled = [];
        foreach ($this->table->getAvailableColumns() as $column) {
            $title = $column->getTitle();
            $alias = $column->getAlias();
            $all[] = $alias;
            if (in_array($alias, $enabled)) {
                $links[] = Link::create(
                    $title,
                    $url->with('columns', implode(',', array_diff($enabled, [
                        $alias
                    ]))),
                    null,
                    ['class' => 'icon-ok']
                );
            } else {
                $disabled[] = $alias;
                $links[] = Link::create(
                    $title,
                    $url->with('columns', implode(',', array_merge($enabled, [
                        $alias
                    ]))),
                    null,
                    ['class' => 'icon-plus']
                );
            }
        }
        if (! empty($disabled)) {
            array_unshift($links, Link::create(
                $this->translate('All'),
                $url->with('columns', implode(',', $all)),
                null,
                [
                    'class' => 'icon-resize-horizontal',
                    'data-base-target' => '_main'
                ]
            ));
        }

        return $links;
    }

    protected function moreOptions($links)
    {
        $options = $this->ul([
            /*$this->li([
                Link::create('Columns', '#', null, ['class' => 'icon-th-list']),
                $this->linkList($this->toggleColumnsOptions())
            ]),*/
            $this->li([
                Link::create(Icon::create('down-open'), '#'),
                $this->linkList($links)
            ]),
        ], ['class' => 'nav']);

        return $options;
    }

    protected function linkList($links)
    {
        $ul = Html::tag('ul');

        foreach ($links as $link) {
            $ul->add($this->li($link));
        }

        return $ul;
    }

    protected function ulLi($content)
    {
        return $this->ul($this->li($content));
    }

    protected function ul($content, $attributes = null)
    {
        return Html::tag('ul', $attributes, $content);
    }

    protected function li($content)
    {
        return Html::tag('li', null, $content);
    }

    protected function hasPermission($permission)
    {
        return $this->auth->hasPermission($permission);
    }
}
