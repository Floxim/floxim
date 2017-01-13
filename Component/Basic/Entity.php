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
    
    public function __construct($data = array(), $component_id = null)
    {
        $this->component_id = $component_id;
        $this->available_offsets_cache = fx::getComponentById($component_id)->getAvailableEntityOffsets();
        parent::__construct($data);
        return $this;
    }
    
    public function getCascadeLinkingEntities()
    {
        $entity = $this;
        $link_fields = fx::data('field')->all()->find( 
            function($f) use ($entity) {
                if ( $f['type'] != 'link') {
                    return false;
                }
                if (!isset($f['format']['cascade_delete']) || !$f['format']['cascade_delete']) {
                    return false;
                }
                $linked_com = fx::getComponentById($f['format']['target']);
                return $entity->isInstanceOf($linked_com['keyword']);
            }
        );
        $res = fx::collection();
        foreach  ($link_fields as $lf) {
            try {
                $finder = fx::data( $lf['component']->get('keyword') );
                $items = $finder->where($lf['keyword'], $this['id'])->all();
                $res = $res->concat($items);
            } catch (\Exception $e) {
                fx::log($e);
            }
        }
        return $res;
    }
    
    protected function saveFiles()
    {
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
    }
    
    protected function beforeSave() {
        $this->saveFiles();
        //$this->saveLinks();
        $this->handleMove();
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
                if ($field['type'] == 'multilink') {
                    $value = array();
                } elseif ($field['type'] === 'link') {
                    $prop_name = $field->getPropertyName();
                    if (!isset($values[$prop_name])) {
                        continue;
                    }
                    $linked_entity_props = $values[$prop_name];
                    $linked_entity_type = $field->getTargetName();
                    if (isset($linked_entity_props['id'])) {
                        $linked_entity = fx::data($linked_entity_type, $linked_entity_props['id']);
                    } else {
                        $linked_entity = fx::data($linked_entity_type)->create();
                    }
                    $linked_entity->setFieldValues($linked_entity_props);
                    $this[$prop_name] = $linked_entity;
                    unset($val_keys[$prop_name]);
                    continue;
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
        if ($field_keyword[0] === ':') {
            $field_keyword = mb_substr($field_keyword, 1);
            $path = explode('.', $field_keyword);
            if (count($path) > 1) {
                $real_prop = end($path);
                $real_owner = $this->dig(array_slice($path, 0, -1));
                if ($real_owner && $real_owner instanceof \Floxim\Floxim\System\Entity) {
                    return $real_owner->getFieldMeta($real_prop);
                }
                return false;
            }
        }
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
            if ($cf && !$cf['is_editable']) {
                $field_meta['editable'] = false;
            }
        }
        $field_meta['label'] = $cf && $cf['name'] ? $cf['name'] : $field_keyword;
        if ($cf && $cf['type']) {
            if ($cf['type'] === 'text') {
                $field_meta['type'] = isset($cf['format']['html']) && $cf['format']['html'] ? 'html' : 'text';
            } else {
                $field_meta['type'] = $cf['type'];
            }
            if ($field_meta['type'] === 'html') {
                $field_meta['linebreaks'] = isset($cf['format']['nl2br']) && $cf['format']['nl2br'];
            }
            if ($cf['type'] === 'select') {
                $field_meta['values'] = $cf->getSelectValues();
                $field_meta['value'] = $this[$cf['keyword']];
            }
            if ($cf['type'] === 'link') {
                $field_meta = array_merge(
                    $field_meta,
                    $cf->getJsField($this)
                );
            }
        }
        return $field_meta;
    }
    
    public function getFormField($field)
    {
        if (is_string($field)) {
            $field =  $this->getField($field);
        }
        $is_editable = $field['is_editable'];
        
        if (!$is_editable && !$field['parent_field_id']) {
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
        foreach ($all_fields as $field) {
            $jsf = $this->getFormField($field);
            if (!$jsf) {
                continue;
            }
            if ($field['group_id']) {
                $group_field = fx::data('field', $field['group_id']);
                $jsf['group'] = $group_field['keyword'];
            }
            $form_fields[] = $jsf;
        }
        $form_fields = fx::collection($form_fields)->sort('priority');
        return $form_fields;
    }
    
    
    public function getFields()
    {
        $com_id = $this->getComponentId();
        $infoblock_id = $this['infoblock_id'];
        static $cache = array();
        $cc = $com_id.'/'.$infoblock_id;
        if (!isset($cache[$cc])) {
            $fields = array();
            foreach ($this->getComponent()->getAllFields() as $field) {
                $proper_field = $field->getForContext($infoblock_id, $com_id);
                $fields[$field['keyword']] = $proper_field;
            }
            $cache[$cc] = fx::collection($fields);
        }
        return $cache[$cc];
    }

    public function hasField($field_keyword)
    {
        $fields = $this->getFields();
        return isset($fields[$field_keyword]);
    }
    
    public function getField($field_keyword) {
        $fields = $this->getFields();
        if (!isset($fields[$field_keyword])) {
            return null;
        }
        return $fields[$field_keyword];
    }
    
    protected function afterDelete() {
        parent::afterDelete();
        // delete images and files when deleting content
        $image_fields = $this->getFields()->find('type', array(
            'image',
            'file'
        ));
        foreach ($image_fields as $f) {
            $c_prop = $this[$f['keyword']];
            if (fx::path()->isFile($c_prop)) {
                fx::files()->rm($c_prop);
            }
        }
        // cascade delete for linking items
        $linking = $this->getCascadeLinkingEntities();
        foreach ($linking as $l) {
            $l->delete();
        }
    }
    
    protected function afterUpdate() {
        parent::afterUpdate();
        // modified image fields
        $image_fields = $this->getFields()->
            find('keyword', $this->modified)->
            find('type', array(
                'image',
                'file'
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
    
    public function isVisible()
    {
        return true;
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
        
        if (isset($this['url'])) {
            $url = $this['url'];
            if ($url) {
                $entity_atts ['data-fx_url'] = $url;
            }
        }
        
        if (!$this->isVisible()) {
            $is_single = 
                !$collection || 
                count($collection) === 1 || 
                (isset($collection->show_hidden_items) && $collection->show_hidden_items);
            $entity_atts['class'] .= ' fx_entity_hidden'.( $is_single ? '_single' : '');
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
                $linker_field['conditions'] = array(
                    array('type', $linker_type)
                );
            }
            $linker_field['content_type'] = $linker_type;
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
    
    public function getStructureFields()
    {
        return array();
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
        return true;
    }
    
    public function isAvailableInSelectedBlock()
    {
        return $this->hasAvailableInfoblock();
    }
    
    public function handleMove()
    {
        if (!$this->hasField('priority')) {
            return;
        }
        $rel_item_id = null;
        
        $finder = $this->getFinder();
        
        $table = current($finder->getTables());
        
        if (isset($this['__move_before'])) {
            $rel_item_id = $this['__move_before'];
            $rel_dir = 'before';
        } elseif (isset($this['__move_after'])) {
            $rel_item_id = $this['__move_after'];
            $rel_dir = 'after';
        }
        
        //fx::log($rel_item_id, $rel_dir);
        
        if (!$rel_item_id) {
            return;
        }
        
        $rel_item = $finder->where('id', $rel_item_id)->one();
        if (!$rel_item) {
            return;
        }
        $rel_priority = fx::db()->getVar(array(
            'select priority from {{'.$table.'}} where id = %d',
            $rel_item_id
        ));
        
        if ($rel_priority === false) {
            return;
        }
        // 1 2 3 |4| 5 6 7 (8) 9 10
        $old_priority = $this['priority'];
        $this['priority'] = $rel_dir == 'before' ? $rel_priority : $rel_priority + 1;
        $q_params = array();
        $q = 'update {{'.$table.'}} ' .
            'set priority = ( IF(priority IS NULL, 0, priority + 1) ) ' .
            'where ';
        
        if ($this->hasField('parent_id') && isset($this['parent_id'])) {
            $q .= 'parent_id = %d and ';
            $q_params []= $this['parent_id'];
        }
        
        if ($this->hasField('infoblock_id') && isset($this['infoblock_id'])) {
            $q .= 'infoblock_id = %d and ';
            $q_params []= $this['infoblock_id'];
        }

        $q .=
            'priority >= %d ' .
            'and id != %d';
        
        $q_params []= $this['priority'];
        $q_params []= $this['id'];
        
        if ($old_priority !== null) {
            $q .= ' and priority < %d';
            $q_params [] = $old_priority;
        }
        array_unshift($q_params, $q);

        fx::db()->query($q_params);
    }
    
    public function getBoxFields()
    {
        return array();
    }
    
    public function _getShortType()
    {
        preg_match("~[^\.]+$~", $this['type'], $short_type);
        return $short_type[0];
    }
}