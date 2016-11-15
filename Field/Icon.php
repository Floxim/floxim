<?php
namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class Icon extends \Floxim\Floxim\Component\Field\Entity
{
    
    public function getJsField($content)
    {

        $res = parent::getJsField($content);
        $res['type'] = 'iconpicker';
        return $res;
    }
    
    public function getCastType() 
    {
        return 'string';
    }

    public function getSqlType()
    {
        return "VARCHAR(255)";
    }
}