<?php

namespace Icinga\Module\Vspheredb\Web\Widget\Config;

use Exception;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Authentication\Auth;
use Icinga\Module\Vspheredb\Db;
use Icinga\Module\Vspheredb\Db\Migrations;
use Icinga\Module\Vspheredb\Web\Form\ApplyMigrationsForm;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Eventually proposes pending migrations
 *
 * USAGE:
 *
 *     $migrations = new ProposeMigrations($db, $this->Auth(), $this->getServerRequest());
 *     if ($migrations->hasAppliedMigrations()) {
 *         $this->redirectNow($this->url());
 *     }
 *     $this->content()->add($migrations);
 */
class ProposeMigrations extends HtmlDocument
{
    use TranslationHelper;

    /** @var Db */
    protected $db;

    /** @var ServerRequestInterface */
    protected $request;

    /** @var Auth */
    protected $auth;

    protected $requiredPermission = 'vspheredb/admin';

    protected $appliedMigrations = false;

    protected $failed = false;

    public function __construct(Db $db, Auth $auth, ServerRequestInterface $request)
    {
        $this->db = $db;
        $this->auth = $auth;
        $this->request = $request;
    }

    /**
     * Whether we applied any pending migration for this request
     *
     * @return bool
     */
    public function hasAppliedMigrations()
    {
        $this->ensureAssembled();
        return $this->appliedMigrations;
    }

    /**
     * Whether checking for (or applying) migrations failed
     *
     * @return bool
     */
    public function hasFailed()
    {
        $this->ensureAssembled();
        return $this->failed;
    }

    protected function assemble()
    {
        try {
            if ($this->auth->hasPermission($this->requiredPermission)) {
                $this->showMigrations($this->db);
            } else {
                $this->showEventualProblems($this->db);
            }
        } catch (Exception $e) {
            $this->add(Hint::error($e->getMessage()));
        }
    }

    protected function showEventualProblems(Db $db)
    {
        $migrations = new Migrations($db);

        if ($migrations->hasSchema()) {
            if ($migrations->hasPendingMigrations()) {
                $this->add(Hint::warning($this->translate(
                    'There are pending Database Schema Migrations. Please ask'
                    . ' an Administrator to apply them now!'
                )));
            }
        } else {
            $this->add(Hint::error($this->translate(
                "The configured DB doesn't have the required has schema. Please"
                . " ask an Administrator to fix the configuration."
            )));
        }
    }

    protected function showMigrations(Db $db)
    {
        $migrations = new Migrations($db);

        if ($migrations->hasSchema()) {
            if ($migrations->hasPendingMigrations()) {
                $this->add(Hint::warning($this->translate(
                    'There are pending Database Schema Migrations. Please apply'
                    . ' them now!'
                )));
                $this->addForm($migrations);
            }
        } else {
            if ($migrations->hasModuleRelatedTable()) {
                $this->add(Hint::error($this->translate(
                    'The chosen Database resource contains related tables,'
                    . ' but the schema is not complete. In case you tried'
                    . ' a pre-release version of this module please drop'
                    . ' this database and start with a fresh new one.'
                )));
            } elseif ($migrations->hasAnyTable()) {
                $this->add(Hint::warning($this->translate(
                    'The chosen Database resource already contains tables. You'
                    . ' might want to continue with this DB resource, but we'
                    . ' strongly suggest to use an empty dedicated DB for this'
                    . ' module.'
                )));
                $this->addForm($migrations);
            } else {
                $this->addForm($migrations);
            }
        }
    }

    protected function addForm(Migrations $migrations)
    {
        $this->add(
            (new ApplyMigrationsForm($migrations))
                ->on(ApplyMigrationsForm::ON_SUCCESS, function () {
                    $this->appliedMigrations = true;
                })
                ->handleRequest($this->request)
        );
    }
}
