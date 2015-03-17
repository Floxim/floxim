<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System\Fx as fx;

class Context {
    protected $stack = array();
    
    protected $meta = array();
    
    public static function create($data = null) {
        $obj = new self;
        if ($data) {
            $obj->push($data);
        }
        return $obj;
    }

    public function push($data = array(), $meta = array())
    {
        $this->stack [] = $data;
        $meta = array_merge(array(
            'transparent' => false,
            'autopop'     => false
        ), $meta);
        $this->meta[] = $meta;
    }
    
    public function pop()
    {
        array_pop($this->stack);
        $meta = array_pop($this->meta);
        if ($meta['autopop']) {
            array_pop($this->stack);
            array_pop($this->meta);
        }
    }
    
    public function set($var, $val)
    {
        $stack_count = count($this->stack);
        if ($stack_count == 0) {
            $this->push(array(), array('transparent' => true));
        }
        if (!is_array($this->stack[$stack_count - 1])) {
            $this->push(array(), array('transparent' => true, 'autopop' => true));
            $stack_count++;
        }
        $this->stack[$stack_count - 1][$var] = $val;
    }
    
    public function getVarMeta($var_name = null, $source = null)
    {
        if ($var_name === null) {
            return array();
        }
        if ($source && $source instanceof Entity) {
            $meta = $source->getFieldMeta($var_name);
            return is_array($meta) ? $meta : array();
        }
        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            if (!($this->stack[$i] instanceof Entity)) {
                continue;
            }
            if (($meta = $this->stack[$i]->getFieldMeta($var_name))) {
                return $meta;
            }
        }
        return array();
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
    
    public function get($name = null, $context_offset = null)
    {
        $need_local = false;
        if ($context_offset === 'local') {
            $need_local = true;
            $context_offset = null;
        }
        // neither var name nor context offset - return current context
        if (!$name && !$context_offset) {
            for ($i = count($this->stack) - 1; $i >= 0; $i--) {
                $c_meta = $this->meta[$i];
                if (!$c_meta['transparent']) {
                    return $this->stack[$i];
                }
            }
            return end($this->stack);
        }

        if (!is_null($context_offset)) {
            $context_position = -1;
            for ($i = count($this->stack) - 1; $i >= 0; $i--) {
                $cc = $this->stack[$i];
                $c_meta = $this->meta[$i];
                if (!$c_meta['transparent']) {
                    $context_position++;
                }
                if ($context_position == $context_offset) {
                    if (!$name) {
                        return $cc;
                    }

                    if (is_array($cc)) {
                        if (array_key_exists($name, $cc)) {
                            return $cc[$name];
                        }
                    } elseif ($cc instanceof \ArrayAccess) {
                        if (isset($cc[$name])) {
                            return $cc[$name];
                        }
                    } elseif (is_object($cc) && isset($cc->$name)) {
                        return $cc->$name;
                    }
                    continue;
                }
                if ($context_position > $context_offset) {
                    return null;
                }
            }
            return null;
        }

        for ($i = count($this->stack) - 1; $i >= 0; $i--) {
            $cc = $this->stack[$i];
            if (is_array($cc)) {
                if (array_key_exists($name, $cc)) {
                    return $cc[$name];
                }
            } elseif ($cc instanceof \ArrayAccess) {
                if (isset($cc[$name])) {
                    return $cc[$name];
                }
            } elseif (is_object($cc) && isset($cc->$name)) {
                return $cc->$name;
            }
        }
        return null;
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
}