<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class Text extends \Floxim\Floxim\Component\Field\Entity
{

    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        if (isset($this['format']) && isset($this['format']['html']) && $this['format']['html']) {
            $res['wysiwyg'] = true;
            $res['nl2br'] = $this['format']['nl2br'];
        }

        return $res;
    }
    
    public function getCastType() 
    {
        return 'string';
    }

    public function formatSettings()
    {
        $fields = array(
            'html' => array(
                'type'  => 'checkbox',
                'label' => fx::alang('allow HTML tags', 'system'),
                'value' => $this['format']['html']
            ),
            'nl2br' => array(
                'type'  => 'checkbox',
                'label' => fx::alang('replace newline to br', 'system'),
                'value' => $this['format']['nl2br']
            )
        );
        return $fields;
    }
    
    public function getSqlType()
    {
        return "MEDIUMTEXT";
    }
}