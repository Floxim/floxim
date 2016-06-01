<?php

namespace Floxim\Floxim\Asset\Less\Tweaker\Style;

use \Floxim\Floxim\Asset\Less;

use \Floxim\Floxim\System\Fx as fx;


class MixinCallGenerator
{
    protected $vars = null;

    public $isPreEvalVisitor = true;

    public static function generateMixinCall($vars, $style)
    {
        $saved_plugins = \Less_Parser::$options['plugins'];
        $instance = new MixinCallGenerator($vars);
        $p = new \Less_Parser(
            array(
                'plugins' => array( $instance )
            )
        );
        $block_name = $style[0];
        $style_name = $style[1];
        ob_start();?>
        .<?= $block_name ?>_style_<?=$style_name?>-tweaked {
        .<?= $block_name ?>_style_<?=$style_name?> (
        <?php
        foreach ($vars as $var) {
            echo '@'.$var['name'].':0; ';
        }
        ?>
        );
        }
        <?php
        $less = ob_get_clean();
        $p->parse($less);
        try {
            $p->getCss();
        } catch (\Exception $e) {

        }
        $res = $instance->getMixinCall();
        \Less_Parser::$options['plugins'] = $saved_plugins;
        return $res;
    }

    protected $mixin_call_token = null;

    public function getMixinCall()
    {
        return $this->mixin_call_token;
    }


    public function __construct($vars)
    {
        $this->vars = $vars;
    }

    public function run($root)
    {
        $call =& $root->rules[0]->rules[0];
        $args =& $call->arguments;
        foreach ($args as &$arg) {
            $var_name = substr($arg['name'], 1);
            $hard_var = new Less\Tweaker\HardVar($var_name);
            $arg['value']->value = array( $hard_var );
        }
        $call->is_style_mixin_call_token = true;
        $this->mixin_call_token = $root;
    }
}