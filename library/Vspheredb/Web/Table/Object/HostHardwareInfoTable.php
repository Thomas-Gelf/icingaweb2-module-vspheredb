<?php

namespace Icinga\Module\Vspheredb\Web\Table\Object;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\Web\Widget\CpuUsage;
use Icinga\Module\Vspheredb\Web\Widget\MemoryUsage;
use Icinga\Module\Vspheredb\Web\Widget\SubTitle;
use ipl\Html\Html;

class HostHardwareInfoTable extends NameValueTable
{
    use TranslationHelper;

    /** @var HostSystem */
    protected $host;

    public function __construct(HostSystem $host)
    {
        $this->host = $host;
    }

    protected function assemble()
    {
        $this->prepend(new SubTitle($this->translate('Hardware Information'), 'th-thumb-empty'));
        $host = $this->host;
        $this->addNameValuePairs([
            $this->translate('CPU') => [
                \sprintf(
                    $this->translate('%d Packages, %d Cores, %d Threads'),
                    $host->get('hardware_cpu_packages'),
                    $host->get('hardware_cpu_cores'),
                    $host->get('hardware_cpu_threads')
                ),
                Html::tag('br'),
                $host->get('hardware_cpu_model'),
                new CpuUsage(
                    $host->quickStats()->get('overall_cpu_usage'),
                    $host->get('hardware_cpu_cores') * $host->get('hardware_cpu_mhz')
                )
            ],
            $this->translate('Memory') => new MemoryUsage(
                $host->quickStats()->get('overall_memory_usage_mb'),
                $host->get('hardware_memory_size_mb')
            ),
            $this->translate('HBAs') => $host->get('hardware_num_hba'),
        ]);
    }
}
