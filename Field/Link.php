<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Link extends Baze
{

    public function validateValue($value)
    {
        if (!parent::validateValue($value)) {
            return false;
        }
        if (is_array($value) && isset($value['title']) && $value['title'] != '') {
            return true;
        }
        if ($value && ($value != strval(intval($value)))) {
            $this->error = sprintf(FX_FRONT_FIELD_INT_ENTER_INTEGER, $this['description']);
            return false;
        }
        return true;
    }

    public function getSqlType()
    {
        return "INT";
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
        $fields[] = array(
            'id'     => 'format[target]',
            'name'   => 'format[target]',
            'label'  => fx::alang('Links to', 'system'),
            'type'   => 'select',
            'values' => $comp_values,
            'value'  => $this['format']['target'] ? $this['format']['target'] : ''
        );
        $fields[] = array(
            'id'    => 'format[prop_name]',
            'name'  => 'format[prop_name]',
            'label' => fx::alang('Key name for the property', 'system'),
            'value' => $this->getPropName()
        );
        $fields[] = array(
            'id'    => 'format[is_parent]',
            'name'  => 'format[is_parent]',
            'label' => fx::alang('Bind value to the parent', 'system'),
            'type'  => 'checkbox',
            'value' => $this['format']['is_parent']
        );
        $fields[] = array(
            'id'     => 'format[render_type]',
            'name'   => 'format[render_type]',
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

    public function getPropName()
    {
        if ($this['format']['prop_name']) {
            return $this['format']['prop_name'];
        }
        if ($this['name']) {
            return preg_replace("~_id$~", '', $this['name']);
        }
        return '';
    }

    public function getJsField($content)
    {
        parent::getJsField($content);
        //$target_component = fx::data('component', $this['format']['target']);
        //$target_content = 'content_'.$target_component['keyword'];
        $target_content = $this->getTargetName();
        $finder = fx::data($target_content);

        if ($this['format']['render_type'] == 'livesearch') {
            $this->_js_field['type'] = 'livesearch';
            $this->_js_field['params'] = array('content_type' => $target_content);
            if (($c_val = $content[$this['keyword']])) {
                $c_val_obj = $finder->where('id', $c_val)->one();
                if ($c_val_obj) {
                    $this->_js_field['value'] = array(
                        'id'   => $c_val_obj['id'],
                        'name' => $c_val_obj['name']
                    );
                }
            }
            return $this->_js_field;
        }

        $this->_js_field['type'] = 'select';
        if ($target_content !== 'lang') {
            $finder->where('site_id', $content['site_id']);
            $name_prop = 'name';
        } else {
            $name_prop = 'en_name';
        }
        $val_items = $finder->all();
        $this->_js_field['values'] = $val_items->getValues($name_prop, 'id');
        return $this->_js_field;
    }

    public function getTargetName()
    {
        $rel_target_id = $this['format']['target'];
        if (!is_numeric($rel_target_id)) {
            $rel_target = $rel_target_id;
        } else {
            $rel_target = fx::data('component', $rel_target_id)->get('keyword');
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
            System\Data::BELONGS_TO,
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
            $entity_infoblock_id = isset($this->value['infoblock_id']) ? $this->value['infoblock_id'] : $content->getLinkFieldInfoblock($this['id']);

            $entity_params = array(
                'name' => $title
            );
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
            if (isset($this->value['parent_id'])) {
                $entity_params['parent_id'] = $this->value['parent_id'];
            }
            $rel = $this->getRelation();
            $entity_type = isset($this->value['type']) ? $this->value['type'] : $rel[1];
            $entity = fx::data($entity_type)->create($entity_params);
            $entity_prop_name = $this['format']['prop_name'];
            $content[$entity_prop_name] = $entity;
            return false;
        }
        return parent::getSavestring();
    }
}