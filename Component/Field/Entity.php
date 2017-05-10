<?php

namespace Floxim\Floxim\Component\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{
    
    protected $value, $error, $is_error = false;
    
    
    public function getJsField($content)
    {
        $name = $this['keyword'];
        $res = array(
            'id'    => $name,
            'name'  => $name,
            'label' => $this['name'],
            'type'  => $this['type'],
            'value' => $this['default']
        );
        
        if ( isset($content[$name]) ) {
            //$res['value'] = $content[$name];
            $res['value'] = $content->getReal($name);
        }
        $res['field_meta'] = array(
            'field_id' => $this['id'],
            'component_id' => $content->getComponent()->get('id'),
            'infoblock_id' => $content['infoblock_id'],
            'entity_id' => $content['id'],
            'entity_type' => $content['type']
        );
        
        if (!$this['is_editable']) {
            $res['disabled'] = true;
            $res['lock_edit'] = true;
        }
        $res['priority'] = (float) $this['priority'];
        return $res;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }
    
    public function getCastType()
    {
        return null;
    }

    public function validateValue($value)
    {
        if (!is_array($value) && !is_object($value)) {
            $value = trim($value);
        }
        if ($this['is_required'] && !$value) {
            $this->error = sprintf(fx::alang('Field "%s" is required'), $this['name']);
            return false;
        }
        return true;
    }

    public function getSavestring($content)
    {
        return $this->value;
    }

    public function getError()
    {
        return $this->error;
    }

    public function setError()
    {
        $this->is_error = true;
    }

    protected $column_created = false;

    public function validate()
    {
        $res = true;
        $is_inherited = $this['parent_field_id'];
        
        if ($is_inherited) {
            return $res;
        }
        
        if (!$this['keyword']) {
            $this->validate_errors[] = array(
                'field' => 'keyword',
                'text'  => fx::alang('Specify field keyword', 'system')
            );
            $res = false;
        }
        if ($this['keyword'] && !preg_match("/^[a-z][a-z0-9_]*$/i", $this['keyword'])) {
            $this->validate_errors[] = array(
                'field' => 'keyword',
                'text'  => fx::alang('Field keyword can contain only letters, numbers, and the underscore character',
                    'system')
            );
            $res = false;
        }

        $modified = $this->modified_data['keyword'] && $this->modified_data['keyword'] != $this->data['keyword'];
        
        

        if (!$this->column_created && !$this->getPayload('skip_sql') && $this['component_id'] && ($modified || !$this['id'])) {

            /// Edit here
            $component = fx::data('component')->where('id', $this['component_id'])->one();
            $chain = $component->getChain();
            foreach ($chain as $c_level) {
                if (fx::db()->columnExists($c_level->getContentTable(), $this->data['keyword'])) {
                    $this->validate_errors[] = array(
                        'field' => 'keyword',
                        'text'  => fx::alang('This field already exists', 'system')
                    );
                    $res = false;
                    break;
                }
            }
        }


        if (!$this['name']) {
            $this->validate_errors[] = array(
                'field' => 'name',
                'text'  => fx::alang('Specify field name', 'system')
            );
            $res = false;
        }

        return $res;
    }
    
    public function getAllChildFields() 
    {
        $res = $this['child_fields']->copy();
        foreach ($res->getData() as $field) {
            $res = $res->concat($field->getAllChildFields());
        }
        return $res;
    }
    
    public function getForContext($infoblock_id = null, $component_id = null)
    {
        $all = $this->getAllChildFields();
        $res = fx::collection();
        foreach ($all as $f) {
            $rel = 0;
            if ($infoblock_id) {
                if ($f['infoblock_id'] && $f['infoblock_id'] !== (int) $infoblock_id) {
                    continue;
                }
                $rel += $f['infoblock_id'] ? 1 : 0;
            }
            if ($component_id) {
                if ($f['component_id'] !== (int) $component_id) {
                    continue;
                }
                $rel += $f['component_id'] ? 1 : 0;
            }
            $res[]= array('rel' => $rel, 'field' => $f);
        }
        if (count($res) === 0) {
            $res = $this;
        } else {
            $res = $res->sort('rel')->last();
            $res = $res['field'];
        }
        return $res;
    }

    public function isMultilang()
    {
        return $this['format']['is_multilang'];
    }

    protected function getTable()
    {
        return fx::data('component')->where('id', $this['component_id'])->one()->getContentTable();
    }
    
    public function isReal()
    {
        return !$this['parent_field_id'];
    }

    protected function beforeInsert()
    {
        if ($this->getPayload('skip_sql')) {
            return;
        }
        $type = $this->getSqlType();
        if (!$type) {
            return;
        }
        if ($this->isReal()) {
            try {
                fx::db()->query(
                    "ALTER TABLE `{{" . $this->getTable() . "}}`
                    ADD COLUMN `" . $this['keyword'] . "` " . $type
                );
                parent::beforeInsert();
                $this->column_created = true;
                fx::cache('meta')->delete('schema');
            } catch (\Exception $e) {
                $this->invalid('Can not create column '.$this['keyword'].": ".$e->getMessage());
            }
        }
    }
    
    /**
     * Get field variants wich were inherited from the new field's parent
     * and their parent should be replaced by the current field
     * e.g. news.name <- page.name becomes news.name <- publication.name 
     * @return \Floxim\Floxim\System\Collection;
     */
    public function getNewChildren()
    {
        if (!$this['parent_field_id'] || !$this['component_id'] || $this['infoblock_id']) {
            return fx::collection();
        }
        $root = $this->getRootField();
        $all_children = $root->getAllChildFields();
        $com_ids = $this['component']->getAllVariants()->getValues('id');
        $that = $this;
        $found_children = $all_children->find(
            function($f) use ($com_ids, $that) {
                if ($f['parent_field_id'] != $that['parent_field_id']) {
                    return false;
                }
                if ($f['id'] == $that['id']) {
                    return false;
                }
                return in_array($f['component_id'], $com_ids);
            }
        );
        return $found_children;
    }
    
    protected function afterInsert() {
        parent::afterInsert();
        $cid = $this['id'];
        $new_children = $this->getNewChildren();
        $new_children->apply(
            function($f) use ($cid) {
                $f->set('parent_field_id', $cid)->save();
            }
        );
        $this->resetLinkingFields();
    }
    
    protected function resetLinkingFields()
    {
        if (!$this->isModified('parent_field_id') && !$this->isDeleted()) {
            return;
        }
        $current_parent = $this['parent_field'];
        if ($current_parent) {
            $current_parent->unloadRelation('child_fields');
        }
        $prev_parent_id = $this->getOld('parent_field_id');
        if ($prev_parent_id) {
            $prev_parent = fx::data('field', $prev_parent_id);
            if ($prev_parent) {
                $prev_parent->unloadRelation('child_fields');
            }
        }
    }

    protected function afterUpdate()
    {
        if ($this->isReal()) {
            $type = self::getSqlTypeByType($this['type']);
            if ($type) {
                if ($this->modified_data['keyword'] && $this->modified_data['keyword'] != $this->data['keyword']) {
                    fx::db()->query("ALTER TABLE `{{" . $this->getTable() . "}}`
                    CHANGE `" . $this->modified_data['keyword'] . "` `" . $this->data['keyword'] . "` " . $type);
                } else {
                    if ($this->modified_data['type'] && $this->modified_data['type'] != $this->data['type']) {
                        fx::db()->query("ALTER TABLE `{{" . $this->getTable() . "}}`
                    MODIFY `" . $this->data['keyword'] . "` " . $type);
                    }
                }
                fx::cache('meta')->delete('schema');
            }
        } else {
            $this->resetLinkingFields();
        }
        parent::afterUpdate();
    }

    protected function afterDelete()
    {
        if ($this->isReal()) {
            if (self::getSqlTypeByType($this->data['type'])) {
                try {
                    fx::db()->query("ALTER TABLE `{{" . $this->getTable() . "}}` DROP COLUMN `" . $this['keyword'] . "`");
                } catch (\Exception $e) {
                    fx::log('Drop field exception', $e->getMessage());
                }
            }
        } else {
            foreach ($this['child_fields'] as $chf) {
                $chf->set('parent_field_id', $this['parent_field_id'])->save();
            }
            $this->resetLinkingFields();
        }
        parent::afterDelete();
    }

    /* -- for admin interface -- */

    public function formatSettings()
    {
        return array();
    }
    
    public function getFormatFields()
    {
        $fields = $this->formatSettings();
        $res = array();
        $is_real = $this->isReal();
        
        foreach ($fields as $key => $field) {
            if (!$is_real && isset($field['override']) && $field['override'] === false) {
                continue;
            }
            $key = (isset($field['name']) ? $field['name'] : $key);
            $field['name'] = 'format['. $key .']';
            $field['value'] = $this->getFormat($key);
            if (!$is_real) {
                $field['locked'] = $this->getFormatReal($key) === null;
            }
            $res []= $field;
        }
        return $res;
    }

    public function getSqlType()
    {
        return "TEXT";
    }

    public function checkRights()
    {
        if (fx::isAdmin()) {
            return true;
        }
        
        if ($this['is_editable']) {
            return true;
        }
        
        return false;
    }

    static public function getSqlTypeByType($type)
    {
        if (!$type) {
            fx::log( debug_backtrace() );
        }
        $classname = 'Floxim\\Floxim\\Field\\Field' . ucfirst($type);
        $field = new $classname();
        return $field->getSqlType();
    }

    public function fakeValue($entity = null)
    {
        $c_type = preg_replace("~\(.+?\)~", '', $this->getSqlType());
        $val = '';
        switch ($c_type) {
            case 'VARCHAR':
                $val = $this['name'];
                break;
            case 'TEXT':
                $val = $this['name'] . ' ' . str_repeat(mb_strtolower($this['name']) . ' ', rand(10, 15));
                break;
            case 'INT':
            case 'TINYINT':
            case 'FLOAT':
                $val = rand(0, 1000);
                break;
            case 'DATETIME':
                $val = date('r');
                break;
        }
        return $val;
    }
    
    public function offsetGet($offset) {
        
        if ($offset === 'select_values' && $this['parent_field_id']) {
            return $this->getRootField()->get($offset);
        }
        
        $real_value = parent::offsetGet($offset);
        
        $skip = array('parent_field_id', 'parent_field', 'id', 'child_fields');
        if (in_array($offset, $skip) ) {
            return $real_value;
        }
        
        if ($offset === 'format') {
            if (!is_array($real_value)) {
                $real_value = array();
            }
            $parent = $this['parent_field'];
            $res = $real_value;
            if ($parent) {
                $res = array_merge($parent['format'], $res);
            }
            return $res;
        }
        
        if ( $real_value === null ) {
            $has_key = array_key_exists($offset, $this->data);
            if (!$has_key || $this->data[$offset] === null) {
                $parent = $this['parent_field'];
                if ($parent) {
                    return $parent[$offset];
                }
            }
        }
        return $real_value;
    }
    
    protected $root_field = null;
    
    public function getRootField()
    {
        if (is_null($this->root_field)) {
            $parent = $this['parent_field'];
            $this->root_field = $parent ? $parent->getRootField() : $this;
        }
        return $this->root_field;
    }
    
    public function getFormat($offset, $default = null)
    {
        $f = $this['format'];
        return isset($f[$offset]) ? $f[$offset] : $default;
    }
    
    public function getFormatReal($offset)
    {
        $rf = $this->getReal('format');
        if ($rf && isset($rf[$offset])) {
            return $rf[$offset];
        }
        return null;
    }
    
    public function setFormatOption($option, $value)
    {
        $f = $this->getReal('format');
        if (!is_array($f)) {
            $f = array();
        }
        $f[$option] = $value;
        $this['format'] = $f;
    }
}