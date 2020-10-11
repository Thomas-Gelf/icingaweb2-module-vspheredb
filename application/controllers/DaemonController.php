<?php

namespace Icinga\Module\Vspheredb\Controllers;

use gipfl\IcingaWeb2\Icon;
use gipfl\Web\Widget\Hint;
use Icinga\Date\DateFormatter;
use Icinga\Module\Vspheredb\Format;
use Icinga\Module\Vspheredb\Web\Controller;
use Icinga\Module\Vspheredb\Web\Tabs\MainTabs;
use Icinga\Module\Vspheredb\WebUtil;
use ipl\Html\Html;
use ipl\Html\Table;

class DaemonController extends Controller
{
    public function indexAction()
    {
        $this->assertPermission('vspheredb/admin');
        $this->setAutorefreshInterval(3);
        $this->addTitle($this->translate('vSphereDB Daemon Status'));
        $this->handleTabs();
        $db = $this->db()->getDbAdapter();
        $daemon = $db->fetchRow(
            $db->select()
                ->from('vspheredb_daemon')
                ->order('ts_last_refresh DESC')
                ->limit(1)
        );
        $lineCount = 2000;
        $logLines = $db->fetchAll($db->select()->from([
            'l' => $db->select()
                ->from('vspheredb_daemonlog')
                ->order('ts_create DESC')
                ->limit($lineCount)
        ])->order('ts_create ASC')->limit($lineCount));
        $log = Html::tag('pre', ['class' => 'logOutput']);
        $logWindow = Html::tag('div', ['class' => 'logWindow'], $log);
        foreach ($logLines as $line) {
            $ts = $line->ts_create / 1000;
            if ($ts + 3600 * 16 < time()) {
                $tsFormatted = DateFormatter::formatDateTime($ts);
            } else {
                $tsFormatted = DateFormatter::formatTime($ts);
            }
            $log->add(Html::tag('div', [
                'class' => $line->level
            ], "$tsFormatted: " . $line->message));
        }

        if ($daemon) {
            if ($daemon->ts_last_refresh / 1000 < time() - 10) {
                $info = Hint::error(Html::sprintf(
                    "Daemon keep-alive is outdated, last refresh was %s",
                    WebUtil::timeAgo($daemon->ts_last_refresh / 1000)
                ));
            } else {
                $processes = json_decode($daemon->process_info);
                $table = new Table();
                foreach ($processes as $pid => $process) {
                    $table->add($table::row([
                        [
                            Icon::create($process->running ? 'ok' : 'warning-empty'),
                            ' ',
                            $pid
                        ],
                        $process->command,
                        Format::bytes($process->memory->rss)
                    ]));
                }
                $info = $table;
            }
        } else {
            $info = Hint::error($this->translate('Daemon is not running'));
        }
        $this->content()->add([$info, $logWindow]);
    }

    protected function handleTabs()
    {
        $action = $this->getRequest()->getControllerName();
        $tabs = $this->tabs(new MainTabs($this->Auth(), $this->db()));
        if ($tabs->has($action)) {
            $tabs->activate($action);
        } else {
            $this->redirectNow('vspheredb/configuration');
        }
    }
}
