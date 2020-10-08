<?php

namespace Icinga\Module\Vspheredb\ProvidedHook\Vspheredb;

use Icinga\Module\Vspheredb\Hook\PerfDataReceiverHook;
use Icinga\Module\Vspheredb\Web\Form\InfluxDbConnectionForm;

class PerfDataReceiverInfluxDb extends PerfDataReceiverHook
{
    public static function getName()
    {
        return 'InfluxDB';
    }

    public function getConfigurationForm()
    {
        return new InfluxDbConnectionForm();
    }
}
