<?php

namespace Floxim\Floxim\Component\Scope;

class Finder extends \Floxim\Floxim\System\Finder {
    public function relations()
    {
        return array(
            'infoblocks' => array(
                self::HAS_MANY,
                'infoblock',
                'scope_id'
            )
        );
    }
}