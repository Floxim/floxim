<?php
namespace Floxim\Floxim\Component\TemplateVariant;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\System\Entity {
    
    
    public function _getName()
    {
        $n = $this->getReal('name');
        return $n ? $n  : '#'.$this['id'];
    }
    
    protected function beforeSave() {
        $this->recountFiles();
    }


    protected function afterSave() {
        parent::afterSave();
        
        if ($this->isModified('params')) {
            $this->deleteInlineStyles();
        }
    }
    
    public function recountFiles()
    {
        $res = fx::files()->handleVisualFiles($this['params']);
        if ($res) {
            $this['params'] = $res;
            return true;
        }
    }


    protected function deleteInlineStyles()
    {
        if ($this['id']) {
            $dropped = \Floxim\Floxim\Asset\Less\StyleBundle::deleteForTemplateVariant($this['id']);
            if ($dropped && $this->isDeleted()) {
                fx::assets('css')->delete();
            }
        }
    }
}