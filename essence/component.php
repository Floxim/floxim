<?php
class fx_component extends fx_essence {
    
    public function get_content_table() {
        return $this['keyword'] == 'content' ? $this['keyword'] : 'content_'.$this['keyword'];
    }
    
    public function get_chain($up_to_down = true) {
        $chain = array($this);
        $c_pid = $this->get('parent_id');
        while ($c_pid != 0) {
            $c_parent = fx::data('component', $c_pid);
            $chain []= $c_parent;
            $c_pid = $c_parent['parent_id'];
        }
        
        return $up_to_down ? array_reverse($chain) : $chain;
    }
    
    public function get_ancestors() {
        return array_slice($this->get_chain(false), 1);
    }

    protected $_class_id;

    public function __construct($input = array()) {
        parent::__construct($input);

        $this->_class_id = $this->data['id'];
    }

    public function validate() {
        $res = true;

        if (!$this['name']) {
            $this->validate_errors[] = array('field' => 'name', 'text' => fx::alang('Component name can not be empty','system'));
            $res = false;
        }

        if (!$this['keyword']) {
            $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('Specify component keyword','system'));
            $res = false;
        }

        if ($this['keyword'] && !preg_match("/^[a-z][a-z0-9_-]*$/i", $this['keyword'])) {
            $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('Keyword can only contain letters, numbers, symbols, "hyphen" and "underscore"','system'));
            $res = false;
        }

        if ($this['keyword']) {
            $components = fx::data('component')->all();
            foreach ($components as $component) {
                if ($component['id'] != $this['id'] && $component['keyword'] == $this['keyword']) {
                    $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('This keyword is used by the component','system') . ' "'.$component['name'].'"');
                    $res = false;
                }
            }
        }


        return $res;
    }

    protected $_stored_fields = null;
    public function fields() {
        if (!$this->_stored_fields) {
            $this->_stored_fields = fx::data('field')->get_by_component($this->_class_id);
        }
        return $this->_stored_fields;
    }
    
    public function all_fields() {
        $fields = new fx_collection();
        foreach ($this->get_chain() as $component) {
            $fields->concat($component->fields());
        }
        return $fields;
    }

    public function get_sortable_fields() {
        //$this->_load_fields();

        $result = array();

        $result['created'] = fx::alang('Created','system');
        $result['id'] = 'ID';
        $result['priority'] = fx::alang('Priority','system');


        foreach ($this->fields() as $v) {
            $result[$v['name']] = $v['description'];
        }

        return $result;
    }

    public function is_user_component() {
        return $this['keyword'] == 'user';
    }

    protected function _after_insert() {
        $this->create_content_table();
    }
    
    protected function create_content_table() {
        $sql = "DROP TABLE IF  EXISTS `{{content_".$this['keyword']."}}`;
            CREATE TABLE IF NOT EXISTS `{{content_".$this['keyword']."}}` (
            `id` int(11) NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
        fx::db()->query($sql);
    }

    protected function _before_delete() {
        $this->delete_fields();
        $this->delete_content_table();
        $this->delete_infoblocks();
    }

    protected function delete_fields() {
        foreach ($this->fields() as $field) {
            $field->delete();
        }
    }

    protected function delete_content_table() {
        $contents = fx::data('content_'.$this['keyword'])->all();
        foreach ($contents as $content) {
            $content->delete();
        }
        $sql = "DROP TABLE `{{content_".$this['keyword']."}}`";
        fx::db()->query($sql);
    }

    protected function delete_infoblocks() {
        $infoblocks = fx::data('infoblock')->where('controller', 'component_'.$this['keyword'])->all();
        foreach ($infoblocks as $infoblock) {
            $infoblock->delete();
        }
    }
    /**
     * Get collection of all component's descendants
     * @return fx_collection
     */
    public function get_all_children() {
        $res = fx::collection()->concat($this['children']);
        foreach ($res as $child) {
            $res->concat($child->get_all_children());
        }
        return $res;
    }
    
    /**
     * Get collection of all component's descendants and the component itself
     * @return fx_collection
     */
    public function get_all_variants() {
        $res = fx::collection($this);
        $res->concat($this->get_all_children());
        return $res;
    }
}