<?php

namespace Icinga\Module\Vspheredb;

use Icinga\Date\DateFormatter;
use ipl\Html\Error as HtmlError;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use Throwable;
use Exception;

class WebUtil
{
    public static function runFailSafe($callback, HtmlDocument $parent)
    {
        try {
            $callback();
        } catch (Exception $e) {
            $parent->add(HtmlError::show($e));
        } catch (Throwable $e) {
            $parent->add(HtmlError::show($e));
        }
    }

    public static function timeAgo($time)
    {
        return Html::tag('span', [
            'class' => 'time-ago',
            'title' => DateFormatter::formatDateTime($time)
        ], DateFormatter::timeAgo($time));
    }
}
