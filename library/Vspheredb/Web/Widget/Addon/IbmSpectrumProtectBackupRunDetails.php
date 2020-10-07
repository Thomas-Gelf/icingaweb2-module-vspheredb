<?php

namespace Icinga\Module\Vspheredb\Web\Widget\Addon;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Date\DateFormatter;
use Icinga\Module\Vspheredb\Addon\IbmSpectrumProtect;
use Icinga\Util\Format;

class IbmSpectrumProtectBackupRunDetails extends NameValueTable
{
    use TranslationHelper;

    /**
     * IbmSpectrumProtectBackupRunDetails constructor.
     * @param IbmSpectrumProtect $details
     */
    public function __construct(IbmSpectrumProtect $details)
    {
        $attributes = $details->requireParsedAttributes();

        $optional = [
            $this->translate('Schedule')      => $attributes['Schedule'],
            $this->translate('Application Protection') => $attributes['Application Protection'],
        ];

        $this->addNameValuePairs([
            $this->translate('Status') => $attributes['Status'],
            $this->translate('Last Run Time')    => DateFormatter::formatDateTime($attributes['Last Run Time']),
            $this->translate('Data Transmitted') => Format::bytes($attributes['Data Transmitted']),
            $this->translate('Duration')      => DateFormatter::formatDuration($attributes['Duration']),
            $this->translate('Type')          => $attributes['Type'],
            $this->translate('Data Mover')    => $attributes['Data Mover'],
            $this->translate('Snapshot Type') => $attributes['Snapshot Type'],
        ]);

        foreach ($optional as $name => $value) {
            if (strlen(trim($value))) {
                $this->addNameValueRow($name, $value);
            }
        }
    }
}
