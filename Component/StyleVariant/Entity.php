<?php
namespace Floxim\Floxim\Component\StyleVariant;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\System\Entity
{
    
    public function _getName()
    {
        $name = $this->getReal('name');
        return trim($name) ? $name : '#'.$this['id'];
    }
    
    public function deleteBundles()
    {
        $b = $this->getBundle();
        $bundle_dir = $b->getDirPath();
        if ($bundle_dir) {
            $dir = dirname($bundle_dir);
            return fx::files()->rm($dir);
        }
    }
    
    public function _getLessVars()
    {
        $vars = $this->getReal('less_vars');
        return is_array($vars) ? $vars : array();
    }
    
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
        return $this['less_vars'];
    }

    public function getLessVar($var_name)
    {
        $vars = $this->getLessVars();
        return isset($vars[$var_name]) ? $vars[$var_name] : null;
    }
    
    protected $colors = null;
    
    public function getStyleKeyword()
    {
        return $this['style'].($this['id'] ? '_variant_'.$this['id'] : '');
    }
    
    public function getStyleLess()
    {
        $parts = explode('_', $this['style']);
        $block = $parts[0];
        $style = $parts[1];
        $res = '.'.$block.'_style_'.$this->getStyleKeyword()." {\n";
        $t = '    ';
        $res .= $t.".".$block.'_style_'.$style."(\n";
        if ($this['less_vars'] && is_array($this['less_vars'])) {
            foreach ($this['less_vars'] as $var_name => $var_value) {
                $res .= $t.$t."@".$var_name.':'.$var_value.";\n";
            }
        }
        $res .= $t.");\n";
        $res .= "}";
        return $res;
    }
    public function afterSave()
    {
        
        parent::afterSave();
        $this->deleteBundles();
        
        $ss =  $this->getPayload('screenshot');
        $file_path = $this->getBundle()->getScreenPath();
        if ($ss) {
            $data = end( explode(',', $ss) );
            fx::files()->writefile($file_path, base64_decode($data));
        } elseif (file_exists($file_path)) {
            fx::files()->rm($file_path);
        }
    }
    
    
    public function afterDelete()
    {
        $this->deleteBundles();
        $this->unbindFromVisuals();
    }
    
    public function unbindFromVisuals()
    {
        $visuals = $this->findUsingVisuals();
        
        $kw = $this->getStyleKeyword();
        
        foreach ($visuals as $vis) {
            foreach (array('template_visual', 'wrapper_visual') as $props_type) {
                $props = $vis[$props_type];
                foreach ($props as $pk => $pv) {
                    if ($pv === $kw && preg_match("~_style$~", $pk)) {
                        $props[$pk] = preg_replace("~\-\-\d+$~", '', $kw);
                    }
                }
                $vis[$props_type] = $props;
            }
            $vis->save();
        }
    }
    
    public function findUsingBlocks()
    {
        $visuals = $this->findUsingVisuals();
        $preset_ids = $this->findUsingTemplateVariants()->getValues('id');
        $preset_visuals = fx::data('infoblock_visual')
            ->where(
                array(
                    array('template_variant_id', $preset_ids),
                    array('wrapper_variant_id', $preset_ids)
                ),
                null,
                'or'
            )
            ->all();
        $ib_ids = $visuals->getValues('infoblock_id');
        $ib_ids = array_merge($ib_ids, $preset_visuals->getValues('infoblock_id'));
        $ib_ids = array_unique($ib_ids);
        $ibs = fx::data('infoblock', $ib_ids);
        return $ibs;
    }
    
    public function findUsingVisuals()
    {
        $kw = $this->getStyleKeyword();
        $that = $this;
        $vis = fx::data('infoblock_visual')
            ->where(
                array(
                    array('template_visual', '%'.$kw.'%', 'like'),
                    array('wrapper_visual', '%'.$kw.'%', 'like')
                ),
                null,
                'or'
            )
            ->all()
            ->find(function($v) use ($that) {
                return $that->checkIsInProps($v);
            });
        return $vis;
    }
    
    protected function getStylePropsOfEntity($entity)
    {
        switch ($entity->getType()) {
            case 'infoblock_visual':
                $props = array('template_visual', 'wrapper_visual');
                break;
            case 'template_variant':
                $props = array('params');
                break;
        }
        return $props;
    }
    
    protected function checkIsInProps($entity)
    {
        $kw = $this->getStyleKeyword();
        $props = $this->getStylePropsOfEntity($entity);
        $found = false;
        foreach ($props as $prop ) {
            $entity->traverseProp($prop, function($v) use (&$found, $kw) {
                if ($v === $kw) {
                    $found = true;
                    return false;
                }
            }, false);
            if ($found) {
                break;
            }
        }
        return $found;
    }
    
    public function appendInsteadOf($entity, $old_variant)
    {
        $new_keyword = $this->getStyleKeyword();
        $old_keyword = $old_variant->getStyleKeyword();
        $props = $this->getStylePropsOfEntity($entity);
        foreach ($props as $prop) {
            $data = $entity[$prop];
            $found = false;
            $entity->traverseProp(
                $prop, 
                function($v, $path) use (&$data, $old_keyword, $new_keyword, &$found) {
                    if ($v !== $old_keyword) {
                        return;
                    }
                    $found = true;
                    fx::digSet($data, $path, $new_keyword);
                },
                false
            );
            if ($found) {
                $entity[$prop] = $data;
            }
        }
    }
    
    public function findUsingTemplateVariants()
    {
        $kw = $this->getStyleKeyword();
        $that = $this;
        $variants = fx::data('template_variant')
            ->where('params', '%'.$kw.'%', 'like')
            ->all()
            ->find(function($v) use ($that) {
                return $that->checkIsInProps($v);
            });
        return $variants;
    }
    
    public function getBundleKeyword()
    {
        return 
            $this['block'] .'_default'.
            (
                $this->is_saved && !$this['is_default'] ? 
                    '_variant_'.$this['id'] : 
                    ''
            );
    }

    
    public function getBundle() 
    {
        return fx::assets('style', $this->getBundleKeyword());
    }
}