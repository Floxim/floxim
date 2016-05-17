<?php
namespace Floxim\Floxim\Component\StyleVariant;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\System\Entity
{
    public function getUsedFonts()
    {
        $params = $this->getLessVars();
        $res = array();
        foreach ($params as $p => $v) {
            if (preg_match('~^font~', $p)) {
                $res []= $v;
            }
        }
        return $res;
    }
    
    public function getLessVars()
    {
        $res = $this['less_vars'];
        if (!is_array($res)) {
            return array();
        }
        return $res;
    }
}