<?php
namespace Floxim\Floxim\Component\StyleVariant;

use Floxim\Floxim\System\Fx as fx;

class Finder extends \Floxim\Floxim\System\Finder 
{
    protected $json_encode = array(
        'less_vars'
    );
    
    public function getDefault($block, $theme_id = null)
    {
        if (!$theme_id) {
            $theme_id = fx::env('theme_id');
        }
        $res = $this->where('is_default', 1)
                    ->where('block', $block)
                    ->where('theme_id', $theme_id)
                    ->one();
        if (!$res) {
            $res = $this->create([
                'block' => $block,
                'theme_id' => $theme_id,
                'is_default' => 1
            ]);
        }
        return $res;
    }
}