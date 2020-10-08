<?php

use Icinga\Module\Vspheredb\ProvidedHook\Vspheredb\PerfDataReceiverInfluxDb;

/** @var $this \Icinga\Application\Modules\Module */
$this->provideHook('director/ImportSource');
$this->provideHook('vspheredb/PerfDataReceiver', PerfDataReceiverInfluxDb::class);

$modules = $this->app->getModuleManager();
foreach ($this->getDependencies() as $module => $required) {
    if ($modules->hasEnabled($module)) {
        $installed = $modules->getModule($module, false)->getVersion();
        $installed = ltrim($installed, 'v'); // v0.6.0 VS 0.6.0
        if (preg_match('/^([<>=]+)\s*v?(\d+\.\d+\.\d+)$/', $required, $match)) {
            $operator = $match[1];
            $vRequired = $match[2];
            if (version_compare($installed, $vRequired, $operator)) {
                continue;
            }
        }
    }

    include __DIR__ . '/run-missingdeps.php';
    return;
}
