<?php

namespace Floxim\Floxim\Component\InfoblockVisual;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{
    protected $needRecountFiles = true;

    protected function beforeSave()
    {
        parent::beforeSave();
        unset($this['is_stub']);
        if ($this->needRecountFiles) {
            $this->recountFiles();
        }
    }
    
    protected function afterSave() {
        parent::afterSave();
        if ($this->isModified('template_visual') || $this->isModified('wrapper_visual')) {
            $this->deleteInlineStyles();
        }
    }
    
    protected function deleteInlineStyles()
    {
        if ($this['id']) {
            $dropped = \Floxim\Floxim\Asset\Less\StyleBundle::deleteForVisual($this['id']);
            if ($dropped && $this->isDeleted()) {
                fx::assets('css')->delete();
            }
        }
    }
    
    public function setNeedRecountFiles($need)
    {
        $this->needRecountFiles = $need;
    }
    
    public function recountFiles()
    {
        $modified_params = $this->getModifiedParams();
        $fxPath = fx::path();
        foreach ($modified_params as $field => $params) {
            $all_params = $this[$field];
            $is_moved = false;
            foreach ($params as $pk => $pv) {
                if (self::checkValueIsFile($pv['new'])) {
                    $ib = $this['infoblock'];
                    $site_id = $ib ? $ib['site_id'] : fx::env('site_id');

                    $file_name = $fxPath->fileName($pv['new']);
                    $new_path = $fxPath->abs('@content_files/' . $site_id . '/visual/' . $file_name);
                    
                    $move_from = $fxPath->abs($pv['new']);
                    
                    if (file_exists($move_from)) {
                        fx::files()->move($move_from, $new_path);
                        $all_params[$pk] = $fxPath->removeBase($fxPath->http($new_path));
                        $is_moved = true;
                    }
                }
                if ($pv['old'] && (!$is_moved  || $pv['old'] !== $all_params[$pk])) {
                    $old_path = $pv['old'];
                    if (self::checkValueIsFile($old_path)) {
                        fx::files()->rm($old_path);
                    }
                }
            }
            $this[$field] = $all_params;
        }
    }
    
    /**
     * Copy (json) params and duplicate files
     * @param type $source
     */
    public function copyParams($source)
    {
        if (!is_array($source)) {
            return $source;
        }
        $res = array();
        foreach ($source as $k => $v) {
            if (self::checkValueIsFile($v)) {
                try {
                    $v = fx::files()->duplicate($v);
                } catch (\Exception $e) {
                    fx::log('failed copying file', $e, $source, $k);
                    continue;
                }
            }
            $res[$k] = $v;
        }
        return $res;
    }

    protected function beforeDelete()
    {
        parent::beforeDelete();
        $files = $this->getFileParams();
        foreach ($files as $f) {
            fx::files()->rm($f);
        }
    }
    
    protected function afterDelete() {
        parent::afterDelete();
        $this->deleteInlineStyles();
    }

    /**
     * find file paths inside params collection and drop them
     */
    public function getFileParams(System\Collection $params = null)
    {
        if (!$params) {
            $params = fx::collection($this['template_visual'])->concat($this['wrapper_visual']);
        }
        $res = array();
        foreach ($params as $p) {
            if (self::checkValueIsFile($p)) {
                $res [] = $p;
            }
        }
        return $res;
    }

    public static function checkValueIsFile($v)
    {
        if (empty($v) || !is_string($v) || substr($v, 0, 1) !== '/') {
            return false;
        }
        $files_path = fx::path('@files');
        $path = fx::path();
        return $path->isInside($v, $files_path) && $path->isFile($v);
    }

    public function getModifiedParams()
    {
        $res = array();
        foreach (array('template_visual', 'wrapper_visual') as $field) {
            $res[$field] = array();
            if (!$this->isModified($field)) {
                continue;
            }
            $new = $this[$field];
            if (!$new) {
                $new = array();
            }
            $old = $this->getOld($field);
            if (!$old || !is_array($old)) {
                $old = array();
            }
            $keys = array_unique(
                array_merge(
                    array_keys($old),
                    array_keys($new)
                )
            );
            foreach ($keys as $key) {
                if (isset($old[$key]) && isset($new[$key]) && $old[$key] == $new[$key]) {
                    continue;
                }
                $res[$field][$key] = array(
                    'old' => isset($old[$key]) ? $old[$key] : null,
                    'new' => isset($new[$key]) ? $new[$key] : null
                );
            }
        }
        return $res;
    }
    
    public function _getTemplate()
    {
        $tv = $this['template_variant'];
        if (!$tv) {
            return $this->getReal('template');
        }
        return $tv['template'];
    }
    
    public function _getTemplateVisual()
    {
        $tv = $this['template_variant'];
        if (!$tv) {
            return $this->getReal('template_visual');
        }
        return $tv['params'];
    }
    
    public function _getWrapper()
    {
        $wv = $this['wrapper_variant'];
        if (!$wv) {
            return $this->getReal('wrapper');
        }
        return $wv['template'];
    }
    
    public function _getWrapperVisual()
    {
        $wv = $this['wrapper_variant'];
        if (!$wv) {
            return $this->getReal('wrapper_visual');
        }
        return $wv['params'];
    }
    
    public function setReal($offset, $value) 
    {
        return parent::offsetSet($offset, $value);
    }
    
    public function offsetSet($offset, $value) 
    {
        if (!$this->is_loaded) {
            return parent::offsetSet($offset, $value);
        }
        if ($offset === 'template_visual' && ($tv = $this['template_variant']) ) {
            $tv['params'] = $value;
            return $this;
        }
        
        if ($offset === 'wrapper_visual' && ($wv = $this['wrapper_variant']) ) {
            $wv['params'] = $value;
            return $this;
        }
        return parent::offsetSet($offset, $value);
    }
    
    /* ! not ready ! */
    public function __getTemplatesField($area, $role = 'template')
    {
        $infoblock = $this['infoblock'];
        switch ($role) {
            case 'template':
                $controller = $infoblock->initController();
                $templates = $controller->getAvailableTemplates($this['theme_id'], $area);
                $c_value = $this['template'];
                break;
            case 'wrapper':
                $controller = null;
                $templates = \Floxim\Floxim\Template\Suitable::getAvailableWrappers(
                    fx::template('floxim.layout.wrapper'), 
                    $area
                );
                $c_value = $this['wrapper'];
                break;
            
        }
        
        if (empty($templates)) {
            return array(
                'type' => 'hidden',
                'name' => 'template',
                'value' => $c_value
            );
        }
        
        // Collect the available templates
        $theme_variants = fx::env('theme')->get('template_variants');
        
        $area_size = isset($area['size']) ? $area['size'] : '';
        $area_size = \Floxim\Floxim\Template\Suitable::getSize($area_size);
        
        $template_codes = fx::collection($templates)->getValues('full_id');
        
        $mismatched = fx::collection();
        
        
        $template_variants = $theme_variants->find(
            function($variant) use ($area_size, $template_codes, &$c_value, $controller, $mismatched) {
                if (!in_array($variant['template'], $template_codes)) {
                    return false;
                }
                if ($variant['size'] && $variant['size'] !== 'any' && $variant['size'] !== $area_size['width']) {
                    $mismatched []= $variant;
                    return false;
                }
                if ($controller) {
                    $avail_for_type = $controller->checkTemplateAvailForType($variant);
                    if (!$avail_for_type) {
                        $mismatched []= $variant;
                        return false;
                    }
                }
                if (is_null($c_value)) {
                    $c_value = $variant['id'];
                }
                return true;
            }
        );
        
        
        $template_variant_counts = $this->getTemplateVariantCounts(
            $template_variants,
            $infoblock ? $infoblock['id'] : null
        );
        
        $values = [];
        
        $special_values = [];
        
        
        $variant_to_value = function($variant) use ($template_variant_counts) {
            return array(
                (string) $variant['id'],
                $variant['name'],
                array(
                    'basic_template' => $variant['template'],
                    'real_name' => $variant->getReal('name'),
                    'is_locked' => $variant['is_locked'],
                    'size' => $variant['size'] ? $variant['size'] : 'any',
                    'avail_for_type' => $variant['avail_for_type'],
                    'wrapper_variant_id' => $variant['wrapper_variant_id'],
                    'count_using_blocks' => isset($template_variant_counts[$variant['id']]) ?
                                                $template_variant_counts[$variant['id']] :
                                                0
                )
            );
        };
        
        foreach ($templates as $template) {
            
            $c_template_variants = $template_variants->find('template', $template['full_id']);
                
            foreach ($c_template_variants as $variant) {
                $values []= $variant_to_value($variant);
            }
            
            $special_values []= [
                'id' => $template['full_id'],
                'name' => $template['name']
            ];
            
        }
        
        if (count($special_values) > 1) {
            $values []= [
                'name' => 'Специальные настройки',
                'children' => $special_values,
                'expanded' => 'always',
                'disabled' => true
            ];
        } else {
            $special_values[0]['name'] = 'Специальные настройки';
            $values []= $special_values[0];
        }
        if (is_null($c_value)) {
            $c_value = $special_values[0]['id'];
        }
        
        if (count($mismatched) > 0) {
            $mismatched_values = [
                'name' => '<span style="color:#F00;">Не подходят</span>',
                'children' => [],
                //'expanded' => 'always',
                'expanded' => false,
                'disabled' => true
            ];
            foreach ($mismatched as $variant) {
                $mismatched_value = $variant_to_value($variant);
                if (isset($mismatched_value[2]['avail_for_type'])) {
                    $target_com = fx::getComponentByKeyword($mismatched_value[2]['avail_for_type']);
                    if ($target_com) {
                        $mismatched_value[2]['target_type_name'] = $target_com['name'];
                    }
                }
                $mismatched_values['children'] []= $mismatched_value;
            }
            $values []= $mismatched_values;
        }
        
        $res = array(
            'label'  => fx::alang('Template', 'system'),
            'name'   => 'template',
            'type'   => 'livesearch',
            'values' => $values,
            'value' => $c_value
        );
        if ($controller && $role !== 'wrapper') {
            $avail_for_type_field = $controller->getTemplateAvailForTypeField();
            if ($avail_for_type_field) {
                $res['template_variant_params'] = [$avail_for_type_field];
            }
        }
        return $res;
    }
}