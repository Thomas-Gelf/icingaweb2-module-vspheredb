<?php

namespace Icinga\Module\Vspheredb\Web\Widget;

use Icinga\Module\Vspheredb\DbObject\HostSystem;
use ipl\Html\HtmlDocument;

class BiosInfo extends HtmlDocument
{
    /** @var HostSystem */
    protected $host;

    public function __construct(HostSystem $host)
    {
        $this->host = $host;
    }

    protected function assemble()
    {
        $host = $this->host;
        $version = $host->get('bios_version');
        $releaseDate = date('Y-m-d', strtotime($host->get('bios_release_date')));
        $this->add(sprintf('%s (%s)', $version, $releaseDate));
    }
}
