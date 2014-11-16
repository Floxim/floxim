<?php

namespace Floxim\Floxim\Template;

use Floxim\Floxim\System;
use Floxim\Form;
use Floxim\Floxim\System\Fx as fx;

class Template
{

    public $action = null;
    protected $_parent = null;
    protected $_inherit_context = false;
    protected $_level = 0;
    protected $_admin_disabled = false;
    
    public $context;

    public function __construct($action, $data = array())
    {
        if ($data instanceof Context) {
            $context = $data;
        } else {
            $context = new Context();
            if (count($data) > 0) {
                $context->push($data);
            }
        }
        $this->context = $context;
        $this->action = $action;
    }

    public function setParent($parent_template, $inherit = false)
    {
        $this->_parent = $parent_template;
        $this->_inherit_context = $inherit;
        $this->_level = $parent_template->getLevel() + 1;
        return $this;
    }

    public function isAdmin($set = null)
    {
        if ($set === null) {
            return !$this->_admin_disabled && fx::isAdmin();
        }
        $this->_admin_disabled = !$set;
        return $this;
    }

    public function getLevel()
    {
        return $this->_level;
    }
    
    protected $mode_stack = array();

    public function pushMode($mode, $value)
    {
        if (!isset($this->mode_stack[$mode])) {
            $this->mode_stack[$mode] = array();
        }
        $this->mode_stack[$mode] [] = $value;
    }

    public function popMode($mode)
    {
        if (isset($this->mode_stack[$mode])) {
            array_pop($this->mode_stack[$mode]);
        }
    }

    public function getMode($mode)
    {
        if (isset($this->mode_stack[$mode])) {
            return end($this->mode_stack[$mode]);
        }
        if ($this->_parent) {
            return $this->_parent->getMode($mode);
        }
    }

    protected function printVar($val, $meta = null)
    {
        $tf = null;
        if ($meta && isset($meta['var_type'])) {
            $tf = new Field($val, $meta);
        }
        $res = $tf ? $tf : $val;
        return (string)$res;
    }

    public function getHelp()
    {
        ini_set('memory_limit', '1G');
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
        $context_stack = array_reverse($this->context_stack);
        echo "<div class='fx_help_template_title'>" . $this->getTemplateSign() . "</div>";
        foreach ($context_stack as $level => $stack) {
            echo $this->getItemHelp($stack, 0);
        }
        if ($this->_parent && $this->_inherit_context) {
            echo "<hr />";
            $this->_parent->printStackHelp();
        }
    }

    public function getItemHelp($item, $level = 0, $c_path = array())
    {
        $c_path [] = $item;
        $item_type = is_array($item) ? 'Array' : get_class($item);
        if ($item instanceof System\Entity || $item instanceof Form\Field\Field || $item instanceof Form\Form) {
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

    protected function getVarMeta($var_name = null, $source = null)
    {
        return $this->context->getVarMeta($var_name, $source);
    }
    
    public function v($v = null, $l = null) {
        return $this->context->get($v, $l);
    }

    protected $is_wrapper = false;

    public function isWrapper($set = null)
    {
        if (func_num_args() == 0) {
            return $this->is_wrapper ? true : ($this->_parent ? $this->_parent->isWrapper() : false);
        }
        $this->is_wrapper = (bool)$set;
    }

    protected $context_stack = array();


    public static $v_count = 0;

    /*
    public function v($name = null, $context_offset = null)
    {
        $need_local = false;
        if ($context_offset === 'local') {
            $need_local = true;
            $context_offset = null;
        }
        // neither var name nor context offset - return current context
        if (!$name && !$context_offset) {
            for ($i = count($this->context_stack) - 1; $i >= 0; $i--) {
                $c_meta = $this->context_stack_meta[$i];
                if (!$c_meta['transparent']) {
                    return $this->context_stack[$i];
                }
            }
            return end($this->context_stack);
        }

        if (!is_null($context_offset)) {
            $context_position = -1;
            for ($i = count($this->context_stack) - 1; $i >= 0; $i--) {
                $cc = $this->context_stack[$i];
                $c_meta = $this->context_stack_meta[$i];
                //if ( ! $cc instanceof fx_template_loop) {
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
            if ($this->_parent) {
                return $this->_parent->v($name, $context_offset - $context_position - 1);
            }
            return null;
        }

        for ($i = count($this->context_stack) - 1; $i >= 0; $i--) {
            $cc = $this->context_stack[$i];
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
        if ($this->_parent && $this->_inherit_context && !$need_local) {
            return $this->_parent->v($name);
        }
        return null;
    }
    */
    
    public static function beautifyHtml($html)
    {
        $level = 0;
        $html = preg_replace_callback(
            '~\s*?<(/?)([a-z0-9]+)[^>]*?(/?)>\s*?~',
            function ($matches) use (&$level) {
                $is_closing = $matches[1] == '/';
                $is_single = in_array(strtolower($matches[2]), array('img', 'br', 'link')) || $matches[3] == '/';

                if ($is_closing) {
                    $level = $level == 0 ? $level : $level - 1;
                }
                $tag = trim($matches[0]);
                $tag = "\n" . str_repeat(" ", $level * 4) . $tag;

                if (!$is_closing && !$is_single) {
                    $level++;
                }
                return $tag;
            },
            $html
        );
        return $html;
    }

    protected function getTemplateSign()
    {
        $template_name = get_class($this);
        return $template_name . ':' . $this->action;
    }

    public static $area_replacements = array();

    /*
     * @param $mode - marker | data | both
     */
    public static function renderArea($area, $context, $mode = 'both')
    {
        $is_admin = fx::isAdmin();
        fx::log('ia', $is_admin);
        if ($mode != 'marker') {
            fx::trigger('render_area', array('area' => $area));
            if ($context->get('_idle')) {
                return;
            }
        }


        if ($is_admin) {
            ob_start();
        }
        if (
            $mode != 'marker' &&
            (!isset($area['render']) || $area['render'] != 'manual')
        ) {
            $area_blocks = fx::page()->getAreaInfoblocks($area['id']);
            $pos = 1;
            foreach ($area_blocks as $ib) {
                $ib->addParams(array('infoblock_area_position' => $pos));
                $result = $ib->render();
                echo $result;
                $pos++;
            }
        }
        if ($is_admin) {
            $area_result = ob_get_clean();
            self::$area_replacements [] = array($area, $area_result);
            $marker = '###fxa' . (count(self::$area_replacements) - 1);
            if ($mode != 'both') {
                $marker .= '|' . $mode;
            }
            $marker .= '###';
            echo $marker;
        }
    }

    public function getAreas()
    {
        $areas = array();
        ob_start();
        fx::listen('render_area.get_areas', function ($e) use (&$areas) {
            $areas[$e->area['id']] = $e->area;
        });
        $this->render(array('_idle' => true));
        fx::unlisten('render_area.get_areas');
        // hm, since IB render res is cached, we can not just remove added files
        // because they won't be added again
        // may be we should switch off caching for idle mode
        //fx::page()->clear_files();
        ob_get_clean();
        return $areas;
    }

    public function hasAction($action = null)
    {
        if (is_null($action)) {
            $action = $this->action;
        }
        return method_exists($this, self::getActionMethod($action));
    }

    protected static function getActionMethod($action)
    {
        return 'tpl_' . $action;
    }


    public function render($data = array())
    {
        if ($this->_level > 10) {
            return '<div class="fx_template_error">bad recursion?</div>';
        }
        if (count($data) > 0) {
            $this->context->push($data);
        }
        //fx::debug('rendring', $this);
        ob_start();
        $method = self::getActionMethod($this->action);
        if ($this->hasAction()) {
            try {
                $this->$method($this->context);
            } catch (\Exception $e) {
                fx::log('template exception', $e);
            }
        } else {
            fx::debug('No template: ' . get_class($this) . '.' . $this->action, $this);
            die();
        }
        $result = ob_get_clean();

        if ($this->context->get('_idle')) {
            return $result;
        }
        if (fx::isAdmin() && !$this->_parent) {
            self::$count_replaces++;
            $result = Template::replaceAreas($result);
            $result = Field::replaceFields($result);
        }
        return $result;
    }

    public static $count_replaces = 0;

    // is populated when compiling
    protected $_templates = array();


    public function getTemplateVariants()
    {
        return $this->_templates;
    }

    public function getInfo()
    {
        if (!$this->action) {
            throw new \Exception('Specify template action/variant before getting info');
        }
        foreach ($this->_templates as $tpl) {
            if ($tpl['id'] == $this->action) {
                return $tpl;
            }
        }
    }

    public static function replaceAreas($html)
    {
        if (!strpos($html, '###fxa')) {
            return $html;
        }
        $html = self::replaceAreasWrappedByTag($html);
        $html = self::replaceAreasInText($html);
        return $html;
    }

    protected static function replaceAreasWrappedByTag($html)
    {
        //$html = preg_replace("~<!--.*?-->~s", '', $html);
        $html = preg_replace_callback(
        /*"~(<[a-z0-9_-]+[^>]*?>)\s*###fxa(\d+)###\s*(</[a-z0-9_-]+>)~s",*/
            "~(<[a-z0-9_-]+[^>]*?>)\s*###fxa(\d+)\|?(.*?)###~s",
            function ($matches) use ($html) {
                $replacement = Template::$area_replacements[$matches[2]];
                $mode = $matches[3];
                if ($mode == 'data') {
                    Template::$area_replacements[$matches[2]] = null;
                    $res = $matches[1] . $replacement[1];
                    if (!$replacement[1]) {
                        $res .= '<span class="fx_area_marker"></span>';
                    }
                    return $res;
                }

                $tag = HtmlToken::createStandalone($matches[1]);
                $tag->addMeta(array(
                    'class'        => 'fx_area',
                    'data-fx_area' => $replacement[0]
                ));
                $tag = $tag->serialize();

                if ($mode == 'marker') {
                    return $tag;
                }

                Template::$area_replacements[$matches[2]] = null;
                return $tag . $replacement[1] . $matches[3];
            },
            $html
        );
        return $html;
    }

    protected static function replaceAreasInText($html)
    {
        $html = preg_replace_callback(
            "~###fxa(\d+)\|?(.*?)###~",
            function ($matches) {
                $mode = $matches[2];
                $replacement = Template::$area_replacements[$matches[1]];
                if ($mode == 'data') {
                    if (!$replacement[1]) {
                        return '<span class="fx_area_marker"></span>';
                    }
                    return $replacement[1];
                }
                $tag_name = 'div';
                $tag = HtmlToken::createStandalone('<' . $tag_name . '>');
                $tag->addMeta(array(
                    'class'        => 'fx_area fx_wrapper',
                    'data-fx_area' => $replacement[0]
                ));
                $tag = $tag->serialize();
                Template::$area_replacements[$matches[1]] = null;
                return $tag . $replacement[1] . '</' . $tag_name . '>';
            },
            $html
        );
        return $html;
    }
}