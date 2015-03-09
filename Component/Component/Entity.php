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
            if ($parent_id) {
                $parent = fx::component($parent_id);
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

            $offsets = array();
            foreach ($fields as $f) {
                $keyword = $f['keyword'];
                $offsets[$keyword] = array(
                    'type' => self::OFFSET_FIELD
                );
                if ( $f->getTypeKeyword() === 'select') {
                    $vals = array();
                    foreach ($f['format']['values'] as $val) {
                        $vals[$val['id']] = $val['value'];
                    }
                    $offsets[$keyword.'_name'] = array(
                        'type' => self::OFFSET_SELECT,
                        'values' => $vals,
                        'real_offset' => $keyword
                    );
                }
            }

            $finder = fx::data($this['keyword']);
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
        }
        return $this->entity_offsets;
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
        return fx::path('@module/' . fx::getComponentPath($this['keyword']));
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
                $fields->concat($component->fields());
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
        //$this->_load_fields();

        $result = array();

        $result['created'] = fx::alang('Created', 'system');
        $result['id'] = 'ID';
        $result['priority'] = fx::alang('Priority', 'system');


        foreach ($this->fields() as $v) {
            $result[$v['name']] = $v['description'];
        }

        return $result;
    }

    public function isUserComponent()
    {
        return $this['keyword'] == 'user';
    }

    protected function afterInsert()
    {
        $this->createContentTable();
    }

    public function createContentTable()
    {
        //$table = str_replace('.', '_', $this['keyword']);
        $table = $this->getContentTable();
        $sql = "DROP TABLE IF  EXISTS `{{{$table}}}`;
            CREATE TABLE IF NOT EXISTS `{{{$table}}}` (
            `id` int(11) NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
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
        $infoblocks = fx::data('infoblock')->where('controller', 'component_' . $this['keyword'])->all();
        foreach ($infoblocks as $infoblock) {
            $infoblock->delete();
        }
    }

    /**
     * Get collection of all component's descendants
     * @return \Floxim\Floxim\System\Collection
     */
    public function getAllChildren()
    {
        $res = fx::collection()->concat($this['children']);
        foreach ($res as $child) {
            $res->concat($child->getAllChildren());
        }
        return $res;
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
    
    public function getItemName() {
        $item_name = $this['item_name'];
        return empty($item_name) ? $this['name'] : $item_name;
    }
}