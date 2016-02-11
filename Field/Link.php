<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Link extends \Floxim\Floxim\Component\Field\Entity
{

    public function validateValue($value)
    {
        if ($value === 'null') {
            $value = null;
        }
        if (!parent::validateValue($value)) {
            return false;
        }
        if (is_array($value) && isset($value['title']) && $value['title'] != '') {
            return true;
        }
        if ($value && ($value != strval(intval($value)))) {
            fx::log('value should be INT or NULL', $value, $this);
            $this->error = sprintf(FX_FRONT_FIELD_INT_ENTER_INTEGER, $this['description']);
            return false;
        }
        return true;
    }

    public function getSqlType()
    {
        return "INT";
    }
    
    public function getCastType() {
        return 'int';
    }

    public function formatSettings()
    {
        $fields = array();

        $comp_values = array_merge(fx::data('component')->getSelectValues(), array(
            array('site', 'Site'),
            array('component', 'Component'),
            array('infoblock', 'Infoblock'),
            array('lang', 'Language')
        ));
        $fields['target'] = array(
            'label'  => fx::alang('Links to', 'system'),
            'type'   => 'select',
            'values' => $comp_values,
            'value'  => $this['format']['target'] ? $this['format']['target'] : ''
        );
        $fields['prop_name'] = array(
            'label' => fx::alang('Key name for the property', 'system'),
            'value' => $this->getPropertyName()
        );
        $fields['cascade_delete']= array(
            'label' => fx::alang('Cascade delete', 'system'),
            'value' => $this['format']['cascade_delete'],
            'type' => 'checkbox'
        );
        $fields['render_type'] = array(
            'label'  => fx::alang('Render type', 'system'),
            'type'   => 'select',
            'values' => array(
                'livesearch' => fx::alang('Live search', 'system'),
                'select'     => fx::alang('Simple select', 'system')
            ),
            'value'  => $this['format']['render_type']
        );
        return $fields;
    }

    public function getPropertyName()
    {
        if ($this['format']['prop_name']) {
            return $this['format']['prop_name'];
        }
        if ($this['keyword']) {
            return preg_replace("~_id$~", '', $this['keyword']);
        }
        return '';
    }
    
    public function getTargetFinder($content)
    {
        $target_com = $this->getTargetName();
        $finder = fx::data($target_com);
        $method_name = 'getRelationFinder'. fx::util()->underscoreToCamel($this['keyword']);
        if (method_exists($content, $method_name)) {
            $finder = call_user_func(array($content, $method_name), $finder);
        }
        return $finder;
    }

    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        //$target_com_keyword = $this->getTargetName();
        
        $finder = $this->getTargetFinder($content);
        
        if ($this['format']['render_type'] == 'livesearch') {
            $res['type'] = 'livesearch';
            $res['params'] = array(
                //'content_type' => $target_com_keyword
                'relation_field_id' => $this['id'],
                'entity_id' => $content['id'],
                'send_form' => true,
                'hidden_on_one_value' => true
            );
            $c_val = $content[$this['keyword']];
            if ($c_val) {
                $c_val_obj = $finder->getById($c_val);
                if ($c_val_obj) {
                    $res['value'] = array(
                        'id'   => $c_val_obj['id'],
                        'name' => $c_val_obj['name']
                    );
                } else {
                    unset($res['value']);
                }
            }
        } else {
            $res['type'] = 'select';
        
            
            $name_prop = $finder->getNameField();
            /*
            if (!$name_prop) {
                if ($target_content !== 'lang') {
                    $finder->where('site_id', $content['site_id']);
                    $name_prop = 'name';
                } else {
                    $name_prop = 'en_name';
                }
            }
             * 
             */
            $val_items = $finder->all();
            $res['values'] = $val_items->getValues($name_prop, 'id');
        }
        return $res;
    }

    public function getTargetName()
    {
        $rel_target_id = $this['format']['target'];
        if (!is_numeric($rel_target_id)) {
            $rel_target = $rel_target_id;
        } else {
            $rel_target = fx::component($rel_target_id)->get('keyword');
        }
        return $rel_target;
    }

    public function getRelation()
    {
        if (!$this['format']['target']) {
            return false;
        }
        $rel_target = $this->getTargetName();
        return array(
            System\Finder::BELONGS_TO,
            $rel_target,
            $this['keyword']
        );
    }

    /*
     * Get the referenced component field
     */
    public function getRelatedComponent()
    {
        $rel = $this->getRelation();
        return fx::data('component', $rel[1]);
    }

    public function getRelatedType()
    {
        $rel = $this->getRelation();
        return $rel[1];
    }


    public function getSavestring($content)
    {
        if (is_array($this->value) && isset($this->value['title'])) {
            $title = $this->value['title'];
            $entity_params = array(
                'name' => $title
            );
            
            if ($content instanceof \Floxim\Main\Content\Entity) {
                $entity_infoblock_id = isset($this->value['infoblock_id']) ? $this->value['infoblock_id'] : $content->getLinkFieldInfoblock($this['id']);
                $entity_infoblock = null;
                if ($entity_infoblock_id) {
                    $entity_infoblock = fx::data('infoblock', $entity_infoblock_id);
                    if ($entity_infoblock) {
                        $entity_params += array(
                            'infoblock_id' => $entity_infoblock_id,
                            'parent_id'    => $entity_infoblock['page_id']
                        );
                    }
                }
            }
            if (isset($this->value['parent_id'])) {
                $entity_params['parent_id'] = $this->value['parent_id'];
            }
            $rel = $this->getRelation();
            $entity_type = isset($this->value['type']) ? $this->value['type'] : $rel[1];
            $entity = fx::data($entity_type)->create($entity_params);
            $entity_prop_name = $this['format']['prop_name'];
            $content[$entity_prop_name] = $entity;
            fx::log($content, $entity_prop_name, fx::debug()->backtrace());
            return false;
        }
        return parent::getSavestring();
    }
}