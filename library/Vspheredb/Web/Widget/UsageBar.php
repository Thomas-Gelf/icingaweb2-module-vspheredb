<?php

namespace Icinga\Module\Vspheredb\Web\Widget;

use gipfl\Translation\TranslationHelper;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class UsageBar extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
        'class' => 'resource-usage',
    ];

    protected $colors = [
        'used' => 'rgba(0, 149, 191, 0.75)',
    ];

    /** @var int */
    protected $used;

    /** @var int */
    protected $capacity;

    protected $formatter;

    public function __construct($used, $capacity)
    {
        $this->used = $used;
        $this->capacity = $capacity;
    }

    protected function makeSegment($percent, $title, $color = 'used')
    {
        if (isset($this->colors[$color])) {
            $color = $this->colors[$color];
        }

        return Html::tag('div', [
            'class' => 'usage',
            'style' => sprintf(
                "background-color: $color; width: %0.3F%%;",
                $percent * 100
            ),
            'title' => $title
        ]);
    }

    public function setFormatter($callback)
    {
        $this->formatter = $callback;

        return $this;
    }

    protected function format($value)
    {
        if ($this->formatter === null) {
            return $value;
        } else {
            $formatter = $this->formatter;

            return $formatter($value);
        }
    }

    protected function getTitleUsed()
    {
        return sprintf(
            $this->translate('Used: %s of %s (%.2F%%)'),
            $this->format($this->used),
            $this->format($this->capacity),
            $this->used / $this->capacity * 100
        );
    }

    protected function getLabelUsed()
    {
        return $this->translate('Used') . ': ' . $this->format($this->used);
    }

    protected function getLabelCapacity()
    {
        return $this->translate('Capacity') . ': ' . $this->format($this->capacity);
    }

    protected function assembleBar(BaseHtmlElement $bar)
    {
        if ($this->capacity !== null && $this->capacity !== 0) {
            $bar->add($this->makeSegment($this->used / $this->capacity, $this->getTitleUsed()));
        }
    }

    protected function assemble()
    {
        $usage = Html::tag('div', [
            'class' => 'usage-bar',
            'data-base-target' => '_next',
        ]);
        $this->assembleBar($usage);
        $this->add($usage);
        $this->addLabels();
    }

    protected function addLabels()
    {
        $this->add([
            Html::tag('span', [
                'class' => 'usage-used'
            ], $this->getLabelUsed()),
            ' ',
            Html::tag('span', [
                'class' => 'usage-capacity'
            ], $this->getLabelCapacity()),
        ]);
    }
}
