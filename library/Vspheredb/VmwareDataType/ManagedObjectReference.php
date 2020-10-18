<?php

namespace Icinga\Module\Vspheredb\VmwareDataType;

class ManagedObjectReference
{
    /**
     * @codingStandardsIgnoreStart
     */
    public $_;
    // codingStandardsIgnoreEnd

    public $type;

    public function __construct($type, $moref)
    {
        $this->_ = $moref;
        $this->type = $type;
    }

    public function getLogName()
    {
        return $this->type . '[' . $this->_ . ']';
    }

    public function __toString()
    {
        if (true) {
            throw new \Exception('Moref to string!');
        }

        return $this->_;
    }
}
