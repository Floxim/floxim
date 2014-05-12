<?php
class fx_content extends fx_essence {

    protected $component_id;

    public function __construct($input = array()) {
        if ($input['component_id']) {
            $this->component_id = $input['component_id'];
        }
        parent::__construct($input);
        return $this;
    }
    
    /*
     * Returns the type of the form "content_page"
     * And if $full = false type "page"
     */
    public function get_type($full = true) {
        if (is_null($this->_type)) {
            if (!$this->component_id) {
                $this->_type = parent::get_type();
            } else {
                $this->_type = fx::data('component', $this->component_id)->get('keyword');
            }
        }
        return ($full ? 'content_' : '').$this->_type;
    }
    
    public function set_component_id($component_id) {
        if ($this->component_id && $component_id != $this->component_id) {
            throw new Exception("Component id can not be changed");
        }
        $this->component_id = intval($component_id);
    }

    public function get_component_id() {
        return $this->component_id;
    }
    
    public function is_instanceof($type) {
        if ($this['type'] == $type) {
            return true;
        }
        $chain = fx::data('component', $this->get_component_id())->get_chain();
        foreach ($chain as $com) {
            if ($com['keyword'] == $type) {
                return true;
            }
        }
        return false;
    }

    public function get_upload_folder() {
        return "content/".$this->component_id;
    }

    /*
     * Populates $this->data based on administrative forms
     */
    public function set_field_values($values = array(), $save_fields = null) {
        if (count($values) == 0) {
            return;
        }
        $fields = $save_fields ? $this->get_fields()->find('keyword', $save_fields) : $this->get_fields();
        $result = array('status' => 'ok');
        foreach ($fields as $field) {
            $field_keyword = $field['keyword'];
            if (!isset($values[$field_keyword])) {
                if ($field['type'] == fx_field::FIELD_MULTILINK) {
                    $value = array();
                } else {
                    continue;
                }
            } else {
                $value = $values[$field_keyword];
            }
            
            if (!$field->check_rights()) {
                continue;
            }
            
            if ($field->validate_value($value)) {
                $field->set_value($value);
                $this[$field_keyword] = $field->get_savestring($this);
            } else {
                $field->set_error();
                $result['status'] = 'error';
                $result['text'][] = $field->get_error();
                $result['fields'][] = $field_keyword;
            }
        }
        return $result;
    }
    
    //protected $_fields_to_show = null;
    
    protected static $content_fields_by_component = array();

    protected $_fields_to_show = null;
    
    public function get_field_meta($field_keyword) {
        $fields = $this->get_fields();
        if (!isset($fields[$field_keyword])) {
            return false;
        }
        $cf = $fields[$field_keyword];
        fx::log('gfm', $cf);
        $field_meta = array(
            'var_type' => 'content', 
            'content_id' => $this['id'],
            'content_type_id' => $this->component_id,
            'id' => $cf['id'],
            'name' => $cf['keyword'],
            'label' => $cf['name'],
            'type' => $cf->type
        );
        if ($cf->type == 'text') {
            $field_meta['html'] = isset($cf['format']['html']) ? $cf['format']['html'] : 0;
        }
        return $field_meta;
    }
    
    public function get_form_fields() {
        $all_fields = $this->get_fields();
        $form_fields = array();
        $coms = array();
        foreach ($all_fields as $field) {
            if ($field['type_of_edit'] == fx_field::EDIT_NONE) {
                continue;
            }
            if (method_exists($this, 'get_form_field_'.$field['keyword'])) {
                $jsf = call_user_func(array($this, 'get_form_field_'.$field['keyword']), $field);
            } else {
                $jsf = $field->get_js_field($this);
            }
            if ($jsf) {
                if (!$jsf['tab']) {
                    if ($field['form_tab']) {
                        $jsf['tab'] = $field['form_tab'];
                    } else {
                        $coms [$field['component_id']] = true;
                        $jsf['tab'] = count($coms);
                    }
                }
                $form_fields[]= $jsf;
            }
        }
        return $form_fields;
    }
    
    public function get_form_field_parent_id($field) {
        if (!$this['id']) {
            return;
        }
        
        $finder = $this->get_avail_parents_finder();
        if (!$finder) {
            return;
        }
        $parents = $finder->get_tree('nested');
        $values = array();
        $c_id = $this['id'];
        $get_values = function($level, $level_num = 0) use (&$values, &$get_values, $c_id) {
            foreach ($level as $page) {
                if ($page['id'] == $c_id) {
                    continue;
                }
                $values []= array($page['id'], str_repeat('- ', $level_num*2).$page['name']);
                if ($page['nested']) {
                    $get_values($page['nested'], $level_num+1);
                }
            }
        };
        $get_values($parents);
        $jsf = $field->get_js_field($this);
        $jsf['values'] = $values;
        $jsf['tab'] = 1;
        return $jsf;
        //fx::log($parents);
    }
    
    /**
     * Returns a finder to get "potential" parents for the object
     */
    public function get_avail_parents_finder() {
        $ib = fx::data('infoblock', $this['infoblock_id']);
        if (!$ib) {
            return false;
        }
        /*
        if ($ib['params']['parent_type'] !== 'current_page_id') {
            return;
        }
         */
        $parent_type = $ib['scope']['page_type'];
        if (!$parent_type) {
            $parent_type = 'page';
        }
        $root_id = $ib['page_id'];
        if (!$root_id) {
            $root_id = fx::data('site', $ib['site_id'])->get('index_page_id');
        }
        $finder = fx::content($parent_type);
        $finder->descendants_of($root_id, $ib['scope']['pages'] != 'children');
        return $finder;
    }
    
    public function add_template_record_meta($html, $collection, $index, $is_subroot) {
        // do nothing if html is empty
        if (!trim($html)) {
            return $html;
        }
        $essence_meta = array(
            $this->get('id'),
            $this->get_type(false)
        );
        
        if ($collection->linker_map && isset($collection->linker_map[$index])) {
            $linker = $collection->linker_map[$index];
            $essence_meta[]= $linker['id'];
            $essence_meta[]= $linker['type'];
        }
        $essence_atts = array(
            'data-fx_essence' => $essence_meta, 
            'class' => 'fx_essence'. ($collection->is_sortable ? ' fx_sortable' : '')
        );
        if (isset($this['_meta'])) {
            $essence_atts ['data-fx_essence_meta'] = $this['_meta'];
        }
        
        if ($is_subroot) {
            $html = preg_replace_callback(
                "~^(\s*?)(<[^>]+>)~", 
                function($matches) use ($essence_atts) {
                    $tag = fx_template_html_token::create_standalone($matches[2]);
                    $tag->add_meta($essence_atts);
                    return $matches[1].$tag->serialize();
                }, 
                $html
            );
            return $html;
        }
        $proc = new fx_template_html($html);
        $html = $proc->add_meta($essence_atts);
        return $html;
    }
    
    protected function _before_save() {
        if ($this->is_modified('parent_id')) {
            $new_parent = fx::data('content', $this['parent_id']);
            $this['level'] = $new_parent['level']+1;
            //$this['materialized_path'] = trim($new_parent['materialized_path'].'.'.$new_parent['id'], '.');
            $this['materialized_path'] = $new_parent['materialized_path'].$new_parent['id'].'.';
        }
        
        $component = fx::data('component', $this->component_id);
        $link_fields = $component->fields()->find('type', fx_field::FIELD_LINK);
        foreach ($link_fields as $lf) {
            // save the cases of type $tagpost['tag'] -> $tagpost['most part']
            $lf_prop = $lf['format']['prop_name'];
            if (
                    isset($this[$lf_prop]) && 
                    $this[$lf_prop] instanceof fx_content && 
                    empty($this[$lf['keyword']])
                ) {
                if (!$this[$lf_prop]['id']) {
                    $this[$lf_prop]->save();
                }
                $this[$lf['keyword']] = $this[$lf_prop]['id'];
            }
            // synchronize the field bound to the parent
            if ($lf['format']['is_parent']) {
                $lfv = $this[$lf['keyword']];
                if ($lfv != $this['parent_id']) {
                    if (!$this['parent_id'] && $lfv) {
                        $this['parent_id'] = $lfv;
                    } elseif ($lfv != $this['parent_id']) {
                        $this[$lf['keyword']] = $this['parent_id'];
                    }
                }
            }
        }
        parent::_before_save();
    }
    /*
     * Store multiple links, linked to the entity
     */
    protected function _save_multi_links() {
        $link_fields = 
            $this->get_fields()->
            find('keyword', $this->modified)->
            find('type', fx_field::FIELD_MULTILINK);
        foreach ($link_fields as $link_field) {
            $val = $this[$link_field['keyword']];
            $relation = $link_field->get_relation();
            $related_field_keyword = $relation[2];
            
            switch ($relation[0]) {
                case fx_data::HAS_MANY:
                    $old_data = isset($this->modified_data[$link_field['keyword']]) ? 
                        $this->modified_data[$link_field['keyword']] :
                        new fx_collection();
                    foreach ($val as $linked_item) {
                        $linked_item[$related_field_keyword] = $this['id'];
                        $linked_item->save();
                    }
                    $old_data->find_remove('id', $val->get_values('id'));
                    $old_data->apply(function($i) {
                        $i->delete();
                    });
                    break;
                case fx_data::MANY_MANY:
                    $old_linkers = isset($this->modified_data[$link_field['keyword']]->linker_map) ? 
                        $this->modified_data[$link_field['keyword']]->linker_map : 
                        new fx_collection();
                    
                    // new linkers
                    // must be set
                    // @todo then we will cunning calculation
                    if (!isset($val->linker_map) || count($val->linker_map) != count($val)) {
                        throw new Exception('Wrong linker map');
                    }
                    foreach ($val->linker_map as $linker_obj) {
                        $linker_obj[$related_field_keyword] = $this['id'];
                        $linker_obj->save();
                    }
                    
                    $old_linkers->find_remove('id', $val->linker_map->get_values('id'));
                    $old_linkers->apply(function ($i) {
                        $i->delete();
                    });
                    break;
            }
        }
    }

    /*
     * Get the id of the information block where to add the linked objects on the field $link_field
     */
    public function get_link_field_infoblock($link_field_id) {
        // information block, where ourselves live
        $our_infoblock = fx::data('infoblock', $this['infoblock_id']);
        return $our_infoblock['params']['field_'.$link_field_id.'_infoblock'];
    }
    
    public function get_fields() {
        $com_id= $this->component_id;
        
        if (!isset(self::$content_fields_by_component[$com_id])) {
            $fields = array();
            foreach ( fx::data('component', $com_id)->all_fields()  as $f) {
                $fields[$f['keyword']] = $f;
            }
            self::$content_fields_by_component[$com_id] = fx::collection($fields);
        }
        return self::$content_fields_by_component[$com_id];
    }
    
    public function has_field($field_keyword) {
        $fields = $this->get_fields();
        return isset($fields[$field_keyword]);
    }

    protected function _after_delete() {
        parent::_after_delete();
        // delete images when deleting content
        $image_fields = $this->get_fields()->
                        find('type', fx_field::FIELD_IMAGE);
        foreach ($image_fields as $f) {
            $c_prop = $this[$f['keyword']];
            if (fx::path()->is_file($c_prop)) {
                fx::files()->rm($c_prop);
            }
        }
    }
    
    protected function _after_update() {
        parent::_after_update();
        // modified image fields
        $image_fields = $this->get_fields()->
                        find('keyword', $this->modified)->
                        find('type', fx_field::FIELD_IMAGE);
        foreach ($image_fields as $img_field) {
            $old_value = $this->modified_data[$img_field['keyword']];
            if (fx::path()->is_file($old_value)) {
                fx::files()->rm($old_value);
            }
        }
    }
    
    public function _after_save() {
        /*
         * Update level and mat.path for children if item moved somewhere
         */
        if ($this->is_modified('parent_id')) {
            $old_path = $this->modified_data['materialized_path'].$this['id'].'.';
            // new path for descendants
            $new_path = $this['materialized_path'].$this['id'].'.';
            $nested_items = fx::data('content')->where('materialized_path', $old_path.'%', 'LIKE')->all();
            $level_diff = 0;
            if ($this->is_modified('level')) {
                $level_diff = $this['level'] - $this->modified_data['level'];
            }
            foreach ($nested_items as $child) {
                $child['materialized_path'] = str_replace($child['materialized_path'], $old_path, $new_path);
                if ($level_diff !== 0) {
                    $child['level'] = $child['level'] + $level_diff;
                }
                $child->save();
            }
        }
    }
    
    public function fake() {
        $fields = $this->get_fields();
        foreach ($fields as $f) {
            $this[$f['keyword']] = $f->fake_value();
        }
    }
}