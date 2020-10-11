<?php

namespace Icinga\Module\Vspheredb\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Vspheredb\Db\Migrations;
use Icinga\Web\Notification;

class ApplyMigrationsForm extends Form
{
    use TranslationHelper;

    /** @var  Migrations */
    protected $migrations;

    public function __construct(Migrations $migrations)
    {
        $this->migrations = $migrations;
    }

    public function assemble()
    {
        if ($this->migrations->hasSchema()) {
            $count = $this->migrations->countPendingMigrations();
            if ($count === 1) {
                $label = $this->translate('Apply a pending schema migration');
            } else {
                $label = sprintf(
                    $this->translate('Apply %d pending schema migrations'),
                    $count
                );
            }
        } else {
            $this->add(Hint::warning($this->translate('There is no vSphereDB schema in this database')));
            $label = $this->translate('Create schema');
        }
        $this->addElement('submit', 'submit', [
            'label' => $label
        ]);
    }

    public function onSuccess()
    {
        $this->migrations->applyPendingMigrations();
        Notification::success($this->translate(
            'Pending database schema migrations have successfully been applied'
        ));
    }
}
