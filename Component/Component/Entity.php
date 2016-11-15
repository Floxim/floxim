<?php
namespace Floxim\Floxim\Component\Component;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{

    protected $content_table = null;
    
    public function getContentTable()
    {
        if (is_null($this->content_table)) {
            $this->content_table = str_replace(".", "_", $this['keyword']);
        }
        return $this->content_table;
    }
    
    public function getAllTables()
    {
        $tables = array();
        foreach ($this->getChain() as $com) {
            $tables []= $com->getContentTable();
        }
        return $tables;
    }

    protected $chain = null;
    /**
     * 
     * @return \Floxim\Floxim\System\Collection
     */
    public function getChain()
    {
        if (is_null($this->chain)) {
            $parent_id = $this['parent_id'];
            if ($parent_id && ($parent = fx::getComponentById($parent_id)) ) {
                $parent_chain = $parent->getChain()->copy();
                $parent_chain[]= $this;
                $this->chain = $parent_chain;
            } else {
                $this->chain = fx::collection(array($this));
            }
        }
        return $this->chain;
    }
    
    
    protected $entity_offsets = null;
    /**
     * Get list of offsets available for the entity belongs to this component
     * @return array
     */
    public function getAvailableEntityOffsets()
    {
        if (is_null($this->entity_offsets)) {
            $fields = $this->getAllFields();

            $offsets = array(
                'id' => array(
                    'type' => self::OFFSET_FIELD
                ),
                'type' => array(
                    'type' => self::OFFSET_FIELD
                )
            );
            
            $finder = fx::data($this['keyword']);
            $encoded_fields = $finder->getJsonEncodedFields();
            foreach ($fields as $f) {
                $keyword = $f['keyword'];
                $offsets[$keyword] = array(
                    'type' => self::OFFSET_FIELD
                );
                if (!in_array($keyword, $encoded_fields)) {
                    $cast = $f->getCastType();
                    if ($cast) {
                        $offsets[$keyword]['cast'] = $cast;
                    }
                }
                if ( $f['type'] === 'select') {
                    $vals = array();
                    foreach ($f['select_values'] as $val) {
                        $vals[$val['keyword']] = $val['id'];
                    }
                    $offsets[$keyword.'_entity'] = array(
                        'type' => self::OFFSET_SELECT,
                        'values' => $vals,
                        'real_offset' => $keyword
                    );
                }
            }
            
            try {
                
                $relations = $finder->relations();

                foreach ($relations as $rel_code => $rel) {
                    $offsets[$rel_code] = array(
                        'type' => self::OFFSET_RELATION,
                        'relation' => $rel
                    );
                }

                $entity_class = $finder->getEntityClassName();
                $reflection = new \ReflectionClass($entity_class);
                $methods = $reflection->getMethods();
                foreach ($methods as $method) {
                    if ($method::IS_PUBLIC && preg_match("~^_get(.+)$~", $method->name, $getter_offset)) {
                        $getter_offset = fx::util()->camelToUnderscore($getter_offset[1]);
                        $offsets[ $getter_offset ] = array(
                            'type' => self::OFFSET_GETTER,
                            'method' => $method->name
                        );
                    }
                }
                $this->entity_offsets = fx::collection($offsets);
            } catch (\Exception $e) {
                fx::log($e->getMessage(), $this['keyword']);
            }
        }
        return $this->entity_offsets;
    }
    
    public function registerOffsetCallback($offset, $callback)
    {
        $offsets = $this->getAvailableEntityOffsets();
        $offsets[$offset] = array(
            'type' => self::OFFSET_CALLBACK,
            'callback' => $callback
        );
        $this->entity_offsets = $offsets;
    }

    public function getNamespace()
    {
        return fx::getComponentNamespace($this['keyword']);
    }

    protected $nsParts = null;

    protected function getNamespacePart($number = null)
    {
        if (is_null($this->nsParts)) {
            $ns = $this->getNamespace();
            $this->nsParts = explode("\\", trim($ns, "\\"));
        }
        return $this->nsParts[$number];
    }

    public function getVendorName()
    {
        return $this->getNamespacePart(0);
    }

    public function getModuleName()
    {
        return $this->getNamespacePart(1);
    }

    public function getOwnName()
    {
        return $this->getNamespacePart(2);
    }

    public function getPath()
    {
        $kw = $this['keyword'];
        if (strstr($kw, '.')) {
            return fx::path('@module/' . fx::getComponentPath($kw));
        }
        return fx::path('@floxim/Component/'.fx::util()->underscoreToCamel($kw));
    }

    public function validate()
    {
        $res = true;

        if (!$this['name']) {
            $this->validate_errors[] = array(
                'field' => 'name',
                'text'  => fx::alang('Component name can not be empty', 'system')
            );
            $res = false;
        }

        if (!$this['keyword']) {
            $this->validate_errors[] = array(
                'field' => 'keyword',
                'text'  => fx::alang('Specify component keyword', 'system')
            );
            $res = false;
        }

        if ($this['keyword'] && !preg_match("/^[a-z][\.a-z0-9_-]*$/i", $this['keyword'])) {
            $this->validate_errors[] = array(
                'field' => 'keyword',
                'text'  => fx::alang('Keyword can only contain letters, numbers, symbols, "hyphen" and "underscore"',
                    'system')
            );
            $res = false;
        }

        if ($this['keyword']) {
            $components = fx::data('component')->all();
            foreach ($components as $component) {
                if ($component['id'] != $this['id'] && $component['keyword'] == $this['keyword']) {
                    $this->validate_errors[] = array(
                        'field' => 'keyword',
                        'text'  => fx::alang('This keyword is used by the component',
                                'system') . ' "' . $component['name'] . '"'
                    );
                    $res = false;
                }
            }
        }


        return $res;
    }

    public function fields()
    {
        return $this['fields'];
    }

    protected $all_fields = null;
    public function getAllFields()
    {
        if (is_null($this->all_fields)) {
            $fields = new System\Collection();
            foreach ($this->getChain() as $component) {
                $com_fields = $component->fields();
                foreach ($com_fields as $com_field) {
                    if (!$com_field['infoblock_id']) {
                        $fields[$com_field['keyword']] = $com_field;
                    }
                }
            }
            $fields->indexUnique('keyword');
            $this->all_fields = $fields;
        }
        return $this->all_fields;
    }
    
    public function getAllFieldsWithChildren($types = null)
    {
        $all_variants = $this->getAllVariants();
        if ($types) {
            $all_variants = $all_variants->find('keyword', $types);
        }
        $fields = fx::collection();
        foreach ($all_variants as $com) {
            $fields->concat($com->getAllFields());
        }
        $fields->unique('id');
        return $fields;
    }

    public function getFieldByKeyword($keyword, $use_chain = false)
    {
        if ($use_chain) {
            $fields = $this->getAllFields();
        } else {
            $fields = $this->fields();
        }
        foreach ($fields as $field) {
            if (strtolower($field['keyword']) == strtolower($keyword)) {
                return $field;
            }
        }
        return null;
    }

    public function getSortableFields()
    {
        $result = array();

        $result['created'] = fx::alang('Created', 'system');
        $result['id'] = 'ID';
        $result['priority'] = fx::alang('Priority', 'system');


        foreach ($this->fields() as $v) {
            $result[$v['name']] = $v['description'];
        }

        return $result;
    }
    
    protected function afterInsert()
    {
        $this->createContentTable();
        $root = $this->getRootComponent();
        if ( $this['parent_id'] && !$root->getFieldByKeyword('type') ) {
            $content_type_field = fx::component('floxim.main.content')->getFieldByKeyword('type');
            $props = $content_type_field->get();
            unset($props['id']);
            $new_field = $content_type_field->getFinder()->create($props);
            $new_field['component_id'] = $root['id'];
            $new_field->save();
            $q = 'update {{'.$root->getContentTable().'}} set type = "'.$root['keyword'].'"';
            fx::db()->query($q);
        }
        fx::cache('meta')->delete('schema');
    }
    
    public function getRootComponent()
    {
        return $this->getChain()->first();
    }

    public function createContentTable()
    {
        $table = $this->getContentTable();
        $sql = "CREATE TABLE IF NOT EXISTS `{{".$table."}}` (";
        $sql .= '`id` int(11) unsigned NOT NULL';
        if (!$this['parent_id']) {
            $sql .= ' AUTO_INCREMENT';
        }
        $sql .= ', PRIMARY KEY (`id`) ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;';
        fx::db()->query($sql);
    }

    protected function beforeDelete()
    {
        if ($this['children']) {
            foreach ($this['children'] as $child_com) {
                $child_com->delete();
            }
        }
        $this->deleteFields();
        $this->deleteInfoblocks();
        $this->deleteContentTable();
        $this->deleteFiles();
    }

    protected function deleteFields()
    {
        foreach ($this->fields() as $field) {
            $field->delete();
        }
    }

    protected function deleteFiles()
    {
        $path = $this->getPath();
        fx::files()->rm($path);
    }

    protected function deleteContentTable()
    {
        try {
            $contents = fx::data($this['keyword'])->all();
            foreach ($contents as $content) {
                $content->delete();
            }
        } catch (\Exception $e) {
            fx::log('Delete content error:', $e->getMessage());
        }
        $sql = "DROP TABLE `{{" . $this->getContentTable() . "}}`";
        fx::db()->query($sql);
    }

    protected function deleteInfoblocks()
    {
        $infoblocks = fx::data('infoblock')->where('controller', $this['keyword'])->all();
        foreach ($infoblocks as $infoblock) {
            $infoblock->delete();
        }
    }
    
    public function getChildren()
    {
        if (!isset($this->data['children'])) {
            $this->data['children'] = fx::component()->find('parent_id', $this['id']);
        }
        return $this->data['children'];
    }
    

    /**
     * Get collection of all component's descendants
     * @return \Floxim\Floxim\System\Collection
     */
    public function getAllChildren()
    {
        $res = $this->getChildren()->copy();
        foreach ($res as $child) {
            $res->concat($child->getAllChildren());
        }
        return $res;
    }
    
    public function isInstanceOfComponent($com)
    {
        if (is_scalar($com)) {
            $com = fx::component($com);
        }
        if (!$com) {
            return false;
        }
        if ($com['id'] === $this['id']) {
            return true;
        }
        $com_children = $com->getAllChildren();
        $is_child = $com_children->findOne('id', $this['id']);
        return (bool) $is_child;
    }

    /**
     * Get collection of all component's descendants and the component itself
     * @return \Floxim\Floxim\System\Collection
     */
    public function getAllVariants()
    {
        $res = fx::collection($this);
        $res->concat($this->getAllChildren());
        return $res;
    }
    
    public function getItemName($scenario = 'one') {
        $lang = fx::alang()->getLang();
        $decl = $this['declension'];
        // this should be done via some config in future
        $declension_map = array(
            'ru' => array(
                'add' => 'singular.acc',
                'add_many' => 'plural.acc',
                'one' => 'singular.nom',
                'list' => 'plural.nom',
                'with' => 'plural.inst',
                'in'    => 'singular.prep', // e.g. "All pages in this [type]"
                'of'    => 'singular.gen'
            )
        );
        if (isset($declension_map[$lang])) {
            $map = $declension_map[$lang];
            if ($scenario && isset($map[$scenario])) {
                $form = explode(".", $map[$scenario]);
                if (isset($decl[$form[1]]) && isset($decl[$form[1]][$form[0]])) {
                    $res = $decl[$form[1]][$form[0]];
                    if ($res) {
                        return $res;
                    }
                }
            }
        }
        
        $item_name = $this['item_name'];
        return empty($item_name) ? $this['name'] : $item_name;
    }
    
    public function getFieldForFilter($keyword) {
        return array(
            'keyword' => $keyword,
            'name' => fx::util()->ucfirst($this->getItemName()),
            'type' => 'entity',
            'is_page'  => $this->isInstanceOfComponent('floxim.main.page'),
            'content_type' => $this['keyword'],
            'children' => $this->getFieldsForFilter($keyword),
            'has_types' => count ( $this->getAllVariants() ) > 1,
            'has_tree' => $this->isInstanceOfComponent('floxim.main.content')
        );
    }
    
    public function getFieldsForFilter($prefix = 'entity', $c_level = 0, $own_only = false) {
        
        if ($own_only) {
            $fields = $this->fields()->find(function($f) {
                return !$f['parent_field_id'];
            });
        } else {
            $fields = $this->getAllFields();
        }
                
        $fields = $fields->find(function($f) {
            if ($f['is_editable']) {
                return true;
            }
            if ($f['type'] === 'datetime') {
                return true;
            }
            return false;
        });
        
        
        
        $entity_fields = array();
        foreach ($fields as $f) {
            if (
                in_array($f['type'], array('group')) 
            ) {
                continue;
            }
            $res_f = array(
                'name' => $f['name'],
                'keyword' => $prefix.'.'.$f['keyword'],
                'type' => $f['type'],
                'id' => $f['id']
            );
            switch ($f['type']) {
                case 'link':
                case 'multilink':
                    $target_keyword = $f->getTargetName();
                    $res_f['real_keyword'] = $res_f['keyword'];
                    $res_f['keyword'] = $prefix.'.'.$f->getPropertyName();
                    $res_f['content_type'] = $target_keyword;
                    $res_f['linking_entity_type'] = $this['keyword'];
                    if ($c_level < 1) {
                        $target_com = fx::getComponentByKeyword($target_keyword);
                        if ($target_com) {
                            $res_f['children'] = $target_com->getFieldsForFilter($res_f['keyword'], $c_level + 1);
                            $res_f['collapsed'] = true;
                        }
                        $res_f['has_types'] = $target_com && count ( $target_com->getAllVariants() ) > 1;
                        $res_f['has_tree'] = $target_com && $target_com->isInstanceOfComponent('floxim.main.content');
                    }
                    $res_f['type'] = 'entity';
                    break;
            }
            $entity_fields []= $res_f;
        }
        
        if ($c_level === 0) {
            $child_coms = $this->getAllChildren();
            foreach ($child_coms as $child_com) {
                $child_com_prefix = $prefix.':'.str_replace('.', ':', $child_com['keyword']);
                $child_com_fields = $child_com->getFieldsForFilter($child_com_prefix, 1, true);
                // temporary remove relation fields
                // @todo: implement queries like $f->where('[floxim.blog.news'].tags', $tag_id)
                $child_com_fields_with_no_links = array();
                foreach ( $child_com_fields as $child_com_field) {
                    if (!in_array($child_com_field['type'], array('entity'))) {
                        $child_com_fields_with_no_links []= $child_com_field;
                    }
                }
                if (count($child_com_fields_with_no_links) > 0) {
                    $res_f = array(
                        'type' => 'subtype',
                        'collapsed' => true,
                        'children' => $child_com_fields_with_no_links,
                        'name' => '['.fx::util()->ucfirst($child_com->getItemName('one')).']',
                        'id' => $child_com_prefix,
                        'disabled' => true
                    );
                    $entity_fields[]= $res_f;
                }
            }
        }
        return $entity_fields;
    }
    
    public function getEntityFinder()
    {
        return fx::data($this['keyword']);
    }
}