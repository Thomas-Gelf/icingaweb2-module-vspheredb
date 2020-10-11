<?php

namespace Icinga\Module\Vspheredb\Web\Table\Object;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Vspheredb\DbObject\HostSystem;
use Icinga\Module\Vspheredb\DbObject\VCenter;
use Icinga\Module\Vspheredb\Web\Widget\Link\MobLink;
use Icinga\Module\Vspheredb\Web\Widget\Link\VCenterLink;
use Icinga\Module\Vspheredb\Web\Widget\Renderer\PathToObjectRenderer;
use Icinga\Module\Vspheredb\Web\Widget\SubTitle;

class HostVirtualizationInfoTable extends NameValueTable
{
    use TranslationHelper;

    /** @var HostSystem */
    protected $host;

    /** @var VCenter */
    protected $vCenter;

    /**
     * HostVirtualizationInfoTable constructor.
     * @param HostSystem $host
     * @throws NotFoundError
     */
    public function __construct(HostSystem $host)
    {
        $this->host = $host;
        $this->vCenter = VCenter::load($host->get('vcenter_uuid'), $host->getConnection());
    }

    protected function assemble()
    {
        $this->prepend(new SubTitle($this->translate('Virtualization Information'), 'cloud'));
        $host = $this->host;
        $uuid = $host->get('uuid');

        $this->addNameValuePairs([
            $this->translate('vCenter')     => new VCenterLink($this->vCenter),
            $this->translate('Path')        => PathToObjectRenderer::render($host),
            $this->translate('Vms') => Link::create(
                $host->countVms(),
                'vspheredb/host/vms',
                ['uuid' => bin2hex($uuid)]
            ),
            $this->translate('HA State')    => $host->get('das_host_state'),
            $this->translate('Hypervisor')  => $host->get('product_full_name'),
            $this->translate('API Version') => $host->get('product_api_version'),
        ]);
    }
}
