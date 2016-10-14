<?php
namespace Floxim\Floxim\Component\Theme;

use Floxim\Floxim\System\Fx as fx;

class Finder extends \Floxim\Floxim\System\Finder {
    public function relations()
    {
        return array(
            'palette' => array(
                self::BELONGS_TO,
                'palette',
                'palette_id'
            ),
            'template_variants' => array(
                self::HAS_MANY,
                'template_variant',
                'theme_id'
            )
        );
    }
}