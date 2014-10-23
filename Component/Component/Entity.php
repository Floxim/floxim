<?php
namespace Floxim\Floxim\Component\Component;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity {

    public function getContentTable() {
        $parts = explode('.', $this['keyword']);
        if ($this['keyword'] == 'content') {
            return $this['keyword'];
        } elseif (count($parts) == 3) {
            return join('_', $parts);
        } else {
            return 'content_' . $this['keyword'];
        }
    }

    public function getChain($up_to_down = true) {
        $chain = array($this);
        $c_pid = $this->get('parent_id');
        while ($c_pid != 0) {
            $c_parent = fx::data('component', $c_pid);
            $chain [] = $c_parent;
            $c_pid = $c_parent['parent_id'];
        }
        return $up_to_down ? array_reverse($chain) : $chain;
    }
    
    public function getNamespace() {
        return fx::getComponentNamespace($this['keyword']);
    }
    
    protected $nsParts = null;
    protected function getNamespacePart($number = null) {
        if (is_null($this->nsParts)) {
            $ns = $this->getNamespace();
            $this->nsParts = explode("\\", trim($ns, "\\"));
        }
        return $this->nsParts[$number];
    }
    
    public function getVendorName() {
        return $this->getNamespacePart(0);
    }
    
    public function getModuleName() {
        return $this->getNamespacePart(1);
    }
    
    public function getOwnName() {
        return $this->getNamespacePart(2);
    }
    
    public function getPath() {
        return fx::path('module', fx::getComponentPath($this['keyword']));
    }

    public function getAncestors() {
        return array_slice($this->getChain(false), 1);
    }

    protected $_class_id;

    public function __construct($input = array()) {
        parent::__construct($input);

        $this->_class_id = $this->data['id'];
    }

    public function validate() {
        $res = true;

        if (!$this['name']) {
            $this->validate_errors[] = array('field' => 'name', 'text' => fx::alang('Component name can not be empty', 'system'));
            $res = false;
        }

        if (!$this['keyword']) {
            $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('Specify component keyword', 'system'));
            $res = false;
        }

        if ($this['keyword'] && !preg_match("/^[a-z][\.a-z0-9_-]*$/i", $this['keyword'])) {
            $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('Keyword can only contain letters, numbers, symbols, "hyphen" and "underscore"', 'system'));
            $res = false;
        }

        if ($this['keyword']) {
            $components = fx::data('component')->all();
            foreach ($components as $component) {
                if ($component['id'] != $this['id'] && $component['keyword'] == $this['keyword']) {
                    $this->validate_errors[] = array('field' => 'keyword', 'text' => fx::alang('This keyword is used by the component', 'system') . ' "' . $component['name'] . '"');
                    $res = false;
                }
            }
        }


        return $res;
    }

    protected $_stored_fields = null;

    public function fields() {
        return $this['fields'];
    }

    public function allFields() {
        $fields = new System\Collection();
        foreach ($this->getChain() as $component) {
            $fields->concat($component->fields());
        }
        return $fields;
    }

    public function getFieldByKeyword($keyword, $use_chain = false) {
        if ($use_chain) {
            $fields = $this->allFields();
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

    public function getSortableFields() {
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

    public function isUserComponent() {
        return $this['keyword'] == 'user';
    }

    protected function afterInsert() {
        $this->createContentTable();
    }

    public function createContentTable() {
        //$table = str_replace('.', '_', $this['keyword']);
        $table = $this->getContentTable();
        $sql = "DROP TABLE IF  EXISTS `{{{$table}}}`;
            CREATE TABLE IF NOT EXISTS `{{{$table}}}` (
            `id` int(11) NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
        fx::db()->query($sql);
    }

    protected function beforeDelete() {
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

    protected function deleteFields() {
        foreach ($this->fields() as $field) {
            $field->delete();
        }
    }

    protected function deleteFiles() {
        $path = $this->getPath();
        fx::files()->rm($path);
    }

    protected function deleteContentTable() {
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

    protected function deleteInfoblocks() {
        $infoblocks = fx::data('infoblock')->where('controller', 'component_' . $this['keyword'])->all();
        foreach ($infoblocks as $infoblock) {
            $infoblock->delete();
        }
    }

    /**
     * Get collection of all component's descendants
     * @return \Floxim\Floxim\System\Collection
     */
    public function getAllChildren() {
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
    public function getAllVariants() {
        $res = fx::collection($this);
        $res->concat($this->getAllChildren());
        return $res;
    }

    public function scaffold() {
        $keyword = $this['keyword'];
        $base_path = fx::path(($this['vendor'] === 'std') ? 'std' : 'root', 'component/' . $keyword . '/') . '/';

        $controller_file = $base_path . $keyword . '.php';

        $parent_com = fx::data('component', $this['parent_id']);
        $parent_ctr = fx::controller($parent_com['keyword']);
        $parent_ctr_class = get_class($parent_ctr);

        $parent_finder = fx::content($parent_com['keyword']);
        $parent_finder_class = get_class($parent_finder);

        $parent_entity = $parent_finder->create();

        $parent_entity_class = get_class($parent_entity);
        ob_start();
        // todo: psr0 need fix
        echo "<?php\n";?>
        class fx_controller_component_<?php echo $keyword; ?> extends <?php echo $parent_ctr_class; ?> {
        // create component controller logic
        }<?php
        $code = ob_get_clean();
        fx::files()->writefile($controller_file, $code);

        $finder_file = $base_path . $keyword . '.data.php';
        ob_start();
        echo "<?php\n";?>
        class fx_data_content_<?php echo $keyword; ?> extends <?php echo $parent_finder_class; ?> {
        // create component finder logic
        }<?php
        $code = ob_get_clean();
        fx::files()->writefile($finder_file, $code);

        $entity_file = $base_path . $keyword . '.entity.php';
        ob_start();
        echo "<?php\n";?>
        class fx_content_<?php echo $keyword; ?> extends <?php echo $parent_entity_class; ?> {
        // create component finder logic
        }<?php
        $code = ob_get_clean();
        fx::files()->writefile($entity_file, $code);
    }
}