<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class String extends \Floxim\Floxim\Component\Field\Entity
{

    
    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        $format = isset($this['format']) ? $this['format'] : null;
        if ($format && isset($format['html']) && $format['html']) {
            $res['wysiwyg'] = true;
            $res['type'] = 'textarea';
            if (isset($format['nl2br']) && $format['nl2br']) {
                $res['nl2br'] = true;
            }
        }
        return $res;
    }
    
    public function formatSettings()
    {
        $fields = array(
            array(
                'type'  => 'checkbox',
                'name'  => 'html',
                'label' => fx::alang('allow HTML tags', 'system')
            )
        );
        return $fields;
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