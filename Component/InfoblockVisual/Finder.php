<?php

namespace Floxim\Floxim\Component\InfoblockVisual;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Finder extends System\Finder
{
    public function __construct()
    {
        parent::__construct();
        // todo: psr0 need verify
        $this->classname = 'fx_infoblock_visual';
        $this->json_encode = array('wrapper_visual', 'template_visual');
    }

    public function getForInfoblocks(System\Collection $infoblocks, $layout_id, $layout_style_id = null)
    {
        $ib_ids = $infoblocks->getValues('id');
        $this->where('infoblock_id', $ib_ids);
        if ($layout_id) {
            $this->where('layout_id', $layout_id);
        }
        if ($layout_style_id) {
            $this->where('style_variant_id', $layout_style_id);
        }
        return $this->all();
    }

    public static function isStaticCacheUsed()
    {
        return true;
    }
    
    public function relations() {
        return array(
            'infoblock' => array(
                self::BELONGS_TO,
                'infoblock',
                'infoblock_id'
            ),
            'template_variant' => array(
                self::BELONGS_TO,
                'template_variant',
                'template_variant_id'
            ),
            'wrapper_variant' => array(
                self::BELONGS_TO,
                'template_variant',
                'wrapper_variant_id'
            )
        );
    }
}