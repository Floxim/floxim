<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class Select extends \Floxim\Floxim\Component\Field\Entity
{

    protected function getRenderVariants()
    {
        return array(
            'select' => "Select", 
            //'radio' => 'Radio', 
            'livesearch' => 'Livesearch', 
            'radio_facet' => 'Buttons'
        );
    }
    
    public function getJsField($content)
    {

        $res = parent::getJsField($content);

        $res['values'] = $this->getSelectValues();
        
        $render_variants = array_keys($this->getRenderVariants());
        
        
        if (isset($this['format']['render_type']) && in_array($this['format']['render_type'], $render_variants)) {
            $res['type'] = $this['format']['render_type'];
        } else {
            $res['type'] = 'select';
        }
        $res['value'] = $content[$this['keyword']];
        if (!$content['id'] || !$res['value']) {
            $res['value'] = $this['default'];
        }
        return $res;
    }
    
    public function getSelectValues()
    {
        $values = $this->getOptions();
        return $values;
    }
    
    public function beforeSave() {
        $format = $this->getReal('format');
        
        if (isset($format['override_select_values'])) {
            $overs = $this['format']['override_select_values'];
            if (isset($overs['is_default'])) {
                $default_id = $overs['is_default'];
                $default_value = $this['select_values']->findOne('id', $default_id);
                if ($default_value) {
                    $this['default'] = $default_value['keyword'];
                }
                unset($overs['is_default']);
            } else {
                $this['default'] = null;
            }
            $priority = 0;
            foreach ($overs as &$variant) {
                $variant['priority'] = $priority;
                $variant['use'] = (bool) $variant['use'];
                $priority++;
            }
            $this->setFormatOption('override_select_values', $overs);
        }
        
        // strange hack: save values via format prop
        if (!isset($format['select_values'])) {
            parent::beforeSave();
            return;
        }
        
        $old_vals = $this['select_values'];
        $new_vals = array();
        foreach($format['select_values'] as $val_id => $val) {
            // existing value
            if (is_numeric($val_id)) {
                $old = $old_vals->findOne('id', $val_id);
                $old->set($val);
                $new_vals []= $old;
            } else {
                $new_vals []= fx::data('select_value')->create($val);
            }
        }
        unset($format['select_values']);
        $this['format'] = $format;
        $this['select_values'] = fx::collection($new_vals);
        parent::beforeSave();
    }

    public function formatSettings()
    {
        if ($this['parent_field_id']) {
            $values = $this->getAvailableValues();
            foreach ($values as &$val) {
                $val['_index'] =  $val['id'];
            }
            $default_id = null;
            $default_value = $this['select_values']->findOne('keyword', $this['default']);
            if ($default_value) {
                $default_id = $default_value['id'];
            }
            $fields['override_select_values'] = array(
                'label' => fx::alang('Elements', 'system'),
                'type'  => 'set',
                'tpl'   => array(
                    array('name' => 'name', 'type' => 'raw'),
                    array('name' => 'use', 'type' => 'checkbox'),
                    array('name' => 'is_default', 'type' => 'radio', 'value' => $default_id),
                ),
                'labels' => array(
                    '',
                    fx::alang('Is available').'?',
                    fx::alang('Use by default').'?'
                ),
                'without_delete' => true,
                'without_add' => true,
                'values' => $values
            );
        } else {
            $values = $this['select_values'];
            $f_values = $values->getValues(function($v) {
                $res = $v->get(array('name', 'keyword', 'description'));
                $res['_index'] = $v['id'];
                return $res;
            });
            $fields['select_values'] = array(
                'label'  => fx::alang('Elements', 'system'),
                'type'   => 'set',
                'tpl'    => array(
                    array('name' => 'keyword', 'type' => 'string'),
                    array('name' => 'name', 'type' => 'string')
                ),
                //'values' => $this['format']['values'] ? $this['format']['values'] : array(),
                'values' => $f_values,
                'labels' => array(
                    fx::alang('Keyword', 'system'), 
                    fx::alang('Name', 'system')
                )
            );
        }
        $render_variants = $this->getRenderVariants();
        $render_vals = array();
        foreach ($render_variants as $k => $v) {
            $render_vals []= array($k, $v);
        }
        $fields['render_type'] = array(
            'label' => fx::alang('Field view', 'system'),
            'type' => 'radio_facet',
            'values' => $render_vals,
            'default' => 'select'
        );
        return $fields;
    }

    public function getOptions()
    {
        $values = array();
        $avail = $this->getAvailableValues();
        /*
        foreach ($this['select_values'] as $v) {
            $values[]= array($v['keyword'], $v['name']);
        }
         * 
         */
        foreach ($avail as $v) {
            if (!isset($v['use']) || $v['use']) {
                $values []= array($v['keyword'], $v['name']);
            }
        }
        return $values;
    }
    
    public function getAvailableValues()
    {
        $values = $this['select_values'];
        $format = $this->getFormat('override_select_values');
        if (!is_array($format)) {
            $format = array();
        }
        $res = array();
        foreach ($values as $v) {
            if (!isset($format[$v['id']])) {
                $cf = array('use' => true);
            } else {
                $cf = $format[$v['id']];
            }
            $res []= array(
                'id' => $v['id'],
                'use' => $cf['use'],
                'name' => $v['name'],
                'description' => $v['description'],
                'priority' => isset($cf['priority']) ? $cf['priority'] : $v['priority'],
                'keyword' => $v['keyword']
            );
        }
        usort(
            $res, 
            function($a, $b) {
                return $a['priority'] - $b['priority'];
            }
        );
        return $res;
        //fx::debug($values, $format, $res);
    }

    /*
    public function getValues()
    {
        $values = array();
        if ($this['format']['values']) {
            foreach ($this['format']['values'] as $v) {
                $values[$v['id']] = $v['value'];
            }
        }
        return $values;
    }
    */
    
    public function getSqlType()
    {
        return "VARCHAR (255)";
    }
}