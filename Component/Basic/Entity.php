<?php

namespace Floxim\Floxim\Component\Basic;

use Floxim\Floxim\Template;
use Floxim\Floxim\System\Fx as fx;
use Floxim\Floxim\System;
use Floxim\Floxim\Component\Field;

/**
 * This is a basic Entity class for all models handled by Component subsystem
 */
abstract class Entity extends \Floxim\Floxim\System\Entity {
    protected $available_offsets_cache = null;
    //protected $available_offset_keys_cache = null;
    
    public function __construct($input = array())
    {
        if ($input['component_id']) {
            $this->component_id = $input['component_id'];
        }
        parent::__construct($input);
        $this->available_offsets_cache = fx::getComponentById($this->component_id)->getAvailableEntityOffsets();
        return $this;
    }
    
    protected function beforeSave() {
        $modified = $this->getModified();
        foreach ($modified as $field_keyword) {
            $field = $this->getField($field_keyword);
            if (!$field instanceof \Floxim\Floxim\Field\File) {
                continue;
            }
            $new_val = $this[$field_keyword];
            if (!is_string($new_val) || !preg_match("~^https?://~", $new_val)) {
                continue;
            }
            $file = fx::files()->saveRemoteFile($new_val, 'upload');
            if (!$file) {
                continue;
            }
            $field->setValue($file);
            $this[$field_keyword] = $field->getSavestring($this);
        }
        return parent::beforeSave();
    }
    
    public function getAvailableOffsets() {
        return $this->available_offsets_cache;
    }
    
    public function getAvailableOffsetKeys() {
        if (is_null($this->available_offset_keys_cache)) {
            foreach ($this->available_offsets_cache as $k => $v){
                $this->available_offset_keys_cache[$k] = true;
            }
            foreach ($this->data as $k => $v) {
                $this->available_offset_keys_cache[$k] = true;
            }
        }
        return $this->available_offset_keys_cache;
    }
    
    
    protected $type = null;
    
    /*
     * Returns the keyword of entity component
     */
    public function getType()
    {
        if (is_null($this->type)) {
            $this->type = $this->getComponent()->get('keyword');
        }
        return $this->type;
    }

    public function getComponentId()
    {
        return $this->component_id;
    }
    
    /**
     * Get entity component
     * @return \Floxim\Floxim\Component\Component\Entity
     */
    public function getComponent()
    {
        $component = fx::getComponentById($this->component_id);
        return $component;
    }

    public function isInstanceof($type)
    {
        $type = fx::getComponentFullName($type);
        if ($this['type'] == $type) {
            return true;
        }
        $chain = $this->getComponent()->getChain();
        foreach ($chain as $com) {
            if ($com['keyword'] == $type) {
                return true;
            }
        }
        return false;
    }

    public function loadFromForm($form, $fields = null)
    {
        $vals = $this->getFromForm($form, $fields);
        $this->setFieldValues($vals, $fields);
        $this->bindForm($form);
        return $this;
    }
    
    /*
     * Populates $this->data based on administrative forms
     */
    public function setFieldValues($values = array(), $save_fields = null)
    {
        if (count($values) == 0) {
            return;
        }
        $fields = $save_fields ? $this->getFields()->find('keyword', $save_fields) : $this->getFields();
        $result = array('status' => 'ok');
        $val_keys = $values instanceof System\Collection ? $values->keys() : array_keys($values);
        foreach ($fields as $field) {
            $field_keyword = $field['keyword'];
            unset($val_keys[array_search($field_keyword, $val_keys)]);
            if (!isset($values[$field_keyword])) {
                if ($field['type'] == Field\Entity::FIELD_MULTILINK) {
                    $value = array();
                } else {
                    continue;
                }
            } else {
                $value = $values[$field_keyword];
            }

            if (!$field->checkRights()) {
                continue;
            }

            if ($field->validateValue($value)) {
                $field->setValue($value);
                $this[$field_keyword] = $field->getSavestring($this);
            } else {
                $field->setError();
                $result['status'] = 'error';
                $result['errors'][] = array(
                    'field' => $field_keyword,
                    'error' => $field->getError()
                );
            }
        }
        foreach ($val_keys as $payload_key) {
            $this->setPayload($payload_key, $values[$payload_key]);
        }
        return $result;
    }
    
    protected static $content_fields_by_component = array();

    protected $_fields_to_show = null;

    public function getFieldMeta($field_keyword)
    {
        $fields = $this->getFields();
        $is_template_var = self::isTemplateVar($field_keyword);
        if ($is_template_var) {
            $field_keyword = mb_substr($field_keyword, 1);
            $cf = $fields[$field_keyword];
            $v_id = $this['id'];
            if (!$v_id) {
                $v_id = '#new_id#';
            }
            $field_meta = array(
                'var_type' => 'visual',
                'id'       => $field_keyword . '_' . $v_id,
                'name'     => $field_keyword . '_' . $v_id
            );
        } else {
            $cf = $fields[$field_keyword];
            if ($cf['type_of_edit'] == Field\Entity::EDIT_NONE) {
                return false;
            }
            if (!$cf) {
                $offsets = $this->getAvailableOffsets();
                if (isset($offsets[$field_keyword])) {
                    $offset_meta = $offsets[$field_keyword];
                    if ($offset_meta['type'] === self::OFFSET_SELECT) {
                        $cf = $fields[$offset_meta['real_offset']];
                    }
                }
                if (!$cf) {
                    return false;
                }
            }
            $field_meta = array(
                'var_type'        => 'content',
                'content_id'      => $this['id'],
                'content_type_id' => $this->component_id,
                'id'              => $cf['id'],
                'name'            => $cf['keyword'],
                'is_required'     => $cf['is_required']
            );
        }
        $field_meta['label'] = $cf && $cf['name'] ? $cf['name'] : $field_keyword;
        if ($cf && $cf->type) {
            if ($cf->type === 'text') {
                $field_meta['type'] = isset($cf['format']['html']) && $cf['format']['html'] ? 'html' : 'text';
            } else {
                $field_meta['type'] = $cf->type;
            }
            if ($field_meta['type'] === 'html') {
                $field_meta['linebreaks'] = isset($cf['format']['nl2br']) && $cf['format']['nl2br'];
            }
            if ($cf->type === 'select') {
                $field_meta['values'] = $cf->getSelectValues();
                $field_meta['value'] = $this[$cf['keyword']];
            }
            if ($cf->type === 'link') {
                $field_meta = array_merge(
                    $field_meta,
                    $cf->getJsField($this)
                );
            }
        }
        return $field_meta;
    }
    
    public function getFormField($field_or_keyword)
    {
        if (is_string($field_or_keyword)) {
            $field = $this->getField($field_or_keyword);
        } else {
            $field = $field_or_keyword;
        }
        if ($field['type_of_edit'] == Field\Entity::EDIT_NONE) {
            return;
        }
        $field_method = 'getFormField' . fx::util()->underscoreToCamel($field['keyword'], true);
        if (method_exists($this, $field_method)) {
            $jsf = call_user_func(array($this, $field_method), $field);
        } else {
            $jsf = $field->getJsField($this);
        }
        if ($field['keyword'] === 'is_published') {
            $jsf['tab'] = 'footer';
            $jsf['class'] = 'toggler';
        }
        return $jsf;
    }

    public function getFormFields()
    {
        $all_fields = $this->getFields();
        $form_fields = array();
        //$coms = array();
        //$content_com_id = fx::component('content')->get('id');
        foreach ($all_fields as $field) {
            if (!$field->checkRights()) {
                continue;
            }
            $jsf = $this->getFormField($field);
            if (!$jsf) {
                continue;
            }
            $form_fields[] = $jsf;
        }
        $form_fields = fx::collection($form_fields);
        return $form_fields;
    }
    
    
    public function getFields()
    {
        $com_id = $this->component_id;

        if (!isset(self::$content_fields_by_component[$com_id])) {
            $fields = array();
            foreach ($this->getComponent()->getAllFields() as $f) {
                $fields[$f['keyword']] = $f;
            }
            self::$content_fields_by_component[$com_id] = fx::collection($fields);
        }
        return self::$content_fields_by_component[$com_id];
    }

    public function hasField($field_keyword)
    {
        $fields = $this->getFields();
        return isset($fields[$field_keyword]);
    }
    
    public function getField($field_keyword) {
        $fields = $this->getFields();
        return isset($fields[$field_keyword]) ? $fields[$field_keyword] : null;
    }
    
    protected function afterDelete() {
        parent::afterDelete();
        // delete images and files when deleting content
        $image_fields = $this->getFields()->find('type', array(
            Field\Entity::FIELD_IMAGE,
            Field\Entity::FIELD_FILE
        ));
        foreach ($image_fields as $f) {
            $c_prop = $this[$f['keyword']];
            if (fx::path()->isFile($c_prop)) {
                fx::files()->rm($c_prop);
            }
        }
    }
    
    protected function afterUpdate() {
        parent::afterUpdate();
        // modified image fields
        $image_fields = $this->getFields()->
            find('keyword', $this->modified)->
            find('type', array(
                Field\Entity::FIELD_IMAGE,
                Field\Entity::FIELD_FILE
            ));

        foreach ($image_fields as $img_field) {
            $old_value = $this->modified_data[$img_field['keyword']];
            if (fx::path()->isFile($old_value)) {
                fx::files()->rm($old_value);
            }
        }
    }
    
    public function addTemplateRecordMeta($html, $collection, $index, $is_subroot)
    {
        // do nothing if html is empty
        if (!trim($html)) {
            return $html;
        }

        $entity_atts = $this->getTemplateRecordAtts($collection, $index);
        
        if ($is_subroot) {
            $html = preg_replace_callback(
                "~^(\s*?)(<[^>]+>)~",
                function ($matches) use ($entity_atts) {
                    $tag = Template\HtmlToken::createStandalone($matches[2]);
                    $tag->addMeta($entity_atts);
                    return $matches[1] . $tag->serialize();
                },
                $html
            );
            return $html;
        }
        $proc = new Template\Html($html);
        $html = $proc->addMeta($entity_atts);
        return $html;
    }

    public function getForcedEditableFields() {
        return array();
    }
    
    public function getTemplateRecordAtts($collection, $index)
    {
        $entity_meta = array(
            $this->get('id'),
            $this->getType(false)
        );
        
        $linkers = null;
        if (is_object($collection) && $collection->linkers) {
            $linkers = $collection->linkers;
            if (isset($collection->linkers[$index])) {
                $linker = $linkers[$index];
                $entity_meta[] = $linker['id'];
                $entity_meta[] = $linker['type'];
            }
        }
        $entity_atts = array(
            'data-fx_entity' => $entity_meta,
            'class'          => 'fx_entity' . (is_object($collection) && $collection->is_sortable ? ' fx_sortable' : '')
        );
        
        if (!$this->isVisible()) {
            $entity_atts['class'] .= ' fx_entity_hidden'.(!$collection || count($collection) === 1 ? '_single' : '');
        }
        
        $com = $this->getComponent();
        $entity_atts['data-fx_entity_name'] = fx::util()->ucfirst($com->getItemName('one'));
        
        $is_placeholder = $this->isAdderPlaceholder();

        if ($is_placeholder) {
            $entity_atts['class'] .= ' fx_entity_adder_placeholder';
        }
        if (isset($this['_meta'])) {
            $c_meta = $this['_meta'];
            if ($is_placeholder) {
                $c_meta['has_page'] = $this->hasPage();
                $c_meta['publish'] = $this->getDefaultPublishState();
            }
            $entity_atts['data-fx_entity_meta'] = $c_meta;
        }
        
        // fields to edit in panel
        $att_fields = array();
        
        $forced = $this->getForcedEditableFields();
        
        if (is_array($forced) && count($forced)) {
            foreach ($forced as $field_keyword) {
                $field_meta = $this->getFieldMeta($field_keyword);
                if (!is_array($field_meta)) {
                    continue;
                }
                // !!! hardcode
                if ($is_placeholder && $field_keyword === 'is_published') {
                    $field_meta['current_value'] = $this->getDefaultPublishState();
                } else {
                    $field_meta['current_value'] = $this[$field_keyword];
                }
                $att_fields []= $field_meta;
            }
        }
        
        if ($linkers && $linkers->linkedBy) {
            if (!$linker) {
                fx::log($collection,$linkers);
                return $entity_atts;
            }
            $linker_field = $linker->getFieldMeta($linkers->linkedBy);
            $linker_collection_field = $linkers->selectField;
            
            if (!$is_placeholder && $linker_collection_field && $linker_collection_field['params']['content_type']) {
                $linker_type = $linker_collection_field['params']['content_type'];
            } else {
                $linker_type = $this['type'];
                $linker_field['params']['conditions'] = array(
                    array('type', $linker_type)
                );
            }
            $linker_field['params']['content_type'] = $linker_type;
            $linker_field['label'] = fx::alang('Select').' '. mb_strtolower(fx::component($linker_type)->getItemName('add'));
            
            if (!$linker_collection_field || !$linker_collection_field['allow_select_doubles']) {
                $linker_field['params']['skip_ids'] = array();
                foreach ($collection->getValues('id') as $col_id) {
                    if ($col_id !== $this['id']) {
                        $linker_field['params']['skip_ids'][]= $col_id;
                    }
                }
            }
            $linker_field['current_value'] = $linker[ $linkers->linkedBy ];
            $att_fields []= $linker_field;
        }
        
        
        if (!$this['id'] && (!$this['parent_id'] || !$this['infoblock_id']) && !$this->hasPage()) {
            $att_fields = array_merge(
                $this->getStructureFields(),
                $att_fields
            );
        }
        
        foreach ($att_fields as $field_key => $field_meta) {
            $field_meta['in_att'] = true;
            // real field
            if (isset($field_meta['id']) && isset($field_meta['content_id'])) {
                $field_keyword = $field_meta['id'].'_'.$field_meta['content_id'];
            } 
            // something else
            else {
                $field_keyword = $field_key;
                $field_meta['id'] = $field_key;
            }
            
            $template_field = new \Floxim\Floxim\Template\Field($field_meta['current_value'], $field_meta);
            $entity_atts['data-fx_force_edit_'.$field_keyword] = $template_field->__toString();
        }
        
        return $entity_atts;
    }
    
    public function hasPage()
    {
        return isset($this['url']);
    }
    
    public function getDefaultPublishState()
    {
        // entities without own page can be published immediately by default
        if (!$this->hasPage()) {
            return true;
        }
        // @todo:
        // if we are inside hidden branch, make this entity visible by default
        // so user can publish the whole branch at once when ready
        /*
        if ($this['parent'] && !$this['parent']['is_branch_published']) {
            return true;
        }
         * 
         */
        return false;
    }
    
    
    /**
     * Check if the entity is adder placeholder or set this property to $switch_to value
     * @param bool $switch_to set true or false
     * @return bool
     */
    public function isAdderPlaceholder($switch_to = null)
    {
        if (func_num_args() == 1) {
            $this->_is_adder_placeholder = $switch_to;
        }
        return isset($this->_is_adder_placeholder) && $this->_is_adder_placeholder;

    }

    public function fake()
    {
        $fields = $this->getFields();
        foreach ($fields as $f) {
            if (!$this[$f['keyword']]) {
                $this[$f['keyword']] = $f->fakeValue();
            }
        }
    }
    
    public function validate() {
        $fields = $this->getComponent()->getAllFields();
        foreach ($fields as $f) {
            if ($f['is_required'] && !$this[$f['keyword']]) {
                $this->invalid($f['name'].': '.fx::lang('This field is required'), $f['keyword']);
            }
        }
        return parent::validate();
    }
    
    public function hasAvailableInfoblock()
    {
        if ($this['infoblock_id']) {
            return true;
        }
        
        $structure_fields = $this->getStructureFields();
                    
        if (
            (
                !isset($structure_fields['infoblock_id']) || 
                count($structure_fields['infoblock_id']['values']) === 0
            ) 
            && !$this->canHaveNoInfoblock()
        ) {
            return false;
        }
        return true;
    }
    
    public function isAvailableInSelectedBlock()
    {
        return $this->hasAvailableInfoblock();
    }
}