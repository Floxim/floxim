<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class Context {
    protected $stack = array();
    
    protected $meta = array();
    
    public $vars = array();
    protected $level = 0;
    
    protected $is_idle = false;
    
    public function isIdle($set = null) {
        if (is_null($set)) {
            return $this->is_idle;
        }
        $this->is_idle = $set;
    }
    
    public function getFrom($item, $key)
    {
        return isset($item[$key]) ? $item[$key] : null;
    }
    
    public static function create($data = null) {
        $obj = new static;
        if ($data) {
            $obj->push($data);
        }
        return $obj;
    }
    
    public function closestEntity($type = null) {
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            $cc = $this->stack[$i];
            if ($cc instanceof \Floxim\Floxim\System\Entity) {
                if (!$type || $cc->isInstanceOf($type)) {
                    return $cc;
                }
            }
        }
    }
    
    public function getHelp()
    {
        ob_start();
        ?>
        <div class="fx_help">
            <a class="fx_help_expander">?</a>

            <div class="fx_help_data" style="display:none;">
                <?php
                $this->printStackHelp();
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function printStackHelp()
    {
        $context_stack = array_reverse($this->stack);
        foreach ($context_stack as $level => $stack) {
            echo $this->getItemHelp($stack, 0);
        }
    }

    public function getItemHelp($item, $level = 0, $c_path = array())
    {
        $c_path [] = $item;
        $item_type = is_array($item) ? 'Array' : get_class($item);
        if ($item instanceof \Floxim\Floxim\System\Entity || $item instanceof \Floxim\Form\Field\Field || $item instanceof \Floxim\Form\Form) {
            $item = $item->get();
        }
        ob_start();
        if ($level === 0) {
            ?>
            <div class="fx_item_help_block">
            <table>
            <tr class="header">
                <td colspan="2"><?= $item_type ?></td>
            </tr>
            <tr class="header">
                <td>Prop</td>
                <td class="value_cell">Value</td>
            </tr>
        <?php
        }
        foreach ($item as $prop => $value) {
            $is_complex = is_object($value) || is_array($value);
            $is_recursion = false;
            if ($is_complex) {
                foreach ($c_path as $c_path_item) {
                    if ($value === $c_path_item) {
                        $is_recursion = true;
                        break;
                    }
                }
            }
            ?>
            <tr class="help_level_<?= $level ?>"
                <?php if ($level > 0) {
                    echo ' style="display:none;" ';
                } ?>>
                <td style="padding-left:<?= (2 + 10 * $level) ?>px !important;" class="prop_cell">
                    <?php
                    if ($is_complex) {
                        ?><a class="level_expander">
                        <b><?= $prop ?></b>
                        <span class="item_type"><?= is_array($value) ? 'Array' : get_class($value) ?></span>
                        </a>
                    <?php
                    } else {
                        echo $prop;
                    }
                    ?>
                </td>
                <td class="value_cell">
                    <?php
                    if (!$is_complex) {
                        echo htmlspecialchars($value);
                    } elseif ($is_recursion) {
                        ?><span class="fx_help_recursion">* recursion *</span><?php
                    }
                    ?>
                </td>
            </tr>
            <?php
            if ($is_complex && !$is_recursion) {
                if (!($value instanceof Loop)) {
                    echo $this->getItemHelp($value, $level + 1, $c_path);
                }
            }
        }
        if ($level === 0) {
            ?></table>
            </div>
        <?php
        }
        return ob_get_clean();
    }
    
    protected $forced_templates = array();
    
    // use $template_id as $target_id
    public function pushForcedTemplate($target_id, $template_id)
    {
        if (!isset($this->forced_templates[$target_id])) {
            $this->forced_templates[$target_id] = array();
        }
        $this->forced_templates[$target_id][]= explode(":", $template_id);
    }
    
    public function popForcedTemplate($target_id)
    {
        if (!isset($this->forced_templates[$target_id])) {
            return;
        }
        array_pop($this->forced_templates[$target_id]);
    }
    
    public function getForcedTemplate($target_id)
    {
        if (
            !isset($this->forced_templates[$target_id]) 
            || count($this->forced_templates[$target_id]) === 0 
        ) {
            return null;
        }
        return end($this->forced_templates[$target_id]);
    }
    
    
    protected static $container_props = array();
    
    public function pushContainerProps($props) 
    {
        $current_props = count(self::$container_props) > 0 ? end(self::$container_props) : array();
        $props = array_merge($current_props, $props);
        self::$container_props []= $props;
    }
    
    public function popContainerProps() 
    {
        array_pop(self::$container_props);
    }
    
    public function getContainerClasses($current_props = false) {
        $cnt = count(self::$container_props);
        if ($cnt === 0) {
            return '';
        }
        
        $last = self::$container_props[$cnt - 1];
        
        $prev = $cnt > 1 && $current_props ? self::$container_props[$cnt - 2] : array();
        
        $res = '';
        
        if (isset($last['darkness'])) {
            $res .= ' fx-block_darkness_'.$last['darkness'];
        }
        
        foreach ($last as $p => $v) {
            if ($p === 'lightness') {
                $res .= ' fx-block_lightness_'.$v;
                continue;
            }
            if (!$current_props || !isset($current_props[$p])) {
                $res .= ' fx-block_parent-'.$p.'_'.$v;
                continue;
            }
            if (isset($prev[$p])) {
                $res .= ' fx-block_parent-'.$p.'_'.$prev[$p];
            }
        }
        return $res.' ';
    }
    
    
    protected static $container_stack = array();
    
    protected static $content_classes_cache = array();
    
    public function pushContainer($name, $is_wrapper = false)
    {
        self::$content_classes_cache []= null;
        if ($name instanceof Container) {
            self::$container_stack []= $name;
            return;
        }
        $container = new Container(
            $this, 
            $name, 
            $is_wrapper ? 'wrapper_visual' : 'template_visual',
            self::$container_stack
        );
        self::$container_stack []= $container;
        return $container;
    }
    
    public function getContentClasses($with_self = false)
    {
        $cache_index = count(self::$content_classes_cache) - ($with_self ? 1 : 2);
        if ($cache_index < 0) {
            return '';
        }
        $cached = self::$content_classes_cache[$cache_index];
        if ($cached !== null) {
            return $cached;
        }
        $container = end(self::$container_stack);
        $res = $container->getContentClasses($with_self);
        self::$content_classes_cache[$cache_index] = $res;
        return $res;
    }
    
    public function popContainer()
    {
        array_pop(self::$container_stack);
        array_pop(self::$content_classes_cache);
    }
}