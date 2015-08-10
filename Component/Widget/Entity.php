<?php

namespace Floxim\Floxim\Component\Widget;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{

    public function validate()
    {
        $res = true;

        if (!$this['name']) {
            $this->validate_errors[] = array(
                'field' => 'name',
                'text'  => fx::alang('Specify the name of the widget', 'system')
            );
            $res = false;
        }

        if (!$this['keyword']) {
            $this->validate_errors[] = array(
                'field' => 'keyword',
                'text'  => fx::alang('Enter the keyword of widget', 'system')
            );
            $res = false;
        }

        if ($this['keyword'] && !preg_match("/^[a-z0-9_\.]+$/i", $this['keyword'])) {
            $this->validate_errors[] = array(
                'field' => 'keyword',
                'text'  => fx::alang('Keyword can contain only letters and numbers', 'system').' / '.$this['keyword']
            );
            $res = false;
        }

        if ($this['keyword']) {
            $widgets = fx::data('widget')->all();
            foreach ($widgets as $widget) {
                if ($widget['id'] != $this['id'] && $widget['keyword'] == $this['keyword']) {
                    $this->validate_errors[] = array(
                        'field' => 'keyword',
                        'text'  => fx::alang('This keyword is used by widget', 'system') . ' "' . $widget['name'] . '"'
                    );
                    $res = false;
                }
            }
        }

        return $res;
    }

    protected function afterInsert()
    {
        parent::afterInsert();
        $this->scaffold();
    }

    public function scaffold()
    {
        
    }
    
    /**  !!! copy-paste from Component\Entity */
    public function getNamespace()
    {
        return fx::getComponentNamespace($this['keyword']);
    }

    protected $nsParts = null;

    protected function getNamespacePart($number = null)
    {
        if (is_null($this->nsParts)) {
            $ns = $this->getNamespace();
            $this->nsParts = explode("\\", trim($ns, "\\"));
        }
        return $this->nsParts[$number];
    }

    public function getVendorName()
    {
        return $this->getNamespacePart(0);
    }

    public function getModuleName()
    {
        return $this->getNamespacePart(1);
    }

    public function getOwnName()
    {
        return $this->getNamespacePart(2);
    }
    
    public function getPath()
    {
        return fx::path('@module/' . fx::getComponentPath($this['keyword']));
    }
    
    
}