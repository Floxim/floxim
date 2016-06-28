<?php
/**
 * Created by PhpStorm.
 * User: Админ без пароля
 * Date: 30.05.2016
 * Time: 18:49
 */

namespace Floxim\Floxim\Asset\Less\Tweaker;

use \Floxim\Floxim\System\Fx as fx;


class Output extends \Less_Output
{
    protected $chunks = array();
    public function add($chunk)
    {
        $this->chunks []= $chunk;
    }

    public function get(\Less_Tree $node, $add_separator = true)
    {
        $res = '';
        if ($node instanceof \Less_Tree_Expression || $node instanceof HardExpression) {
            $node->genCSS($this);
            $res = join($this->chunks, '');
            if ($res !== '' && $add_separator) {
                $res .= ';';
            }
            $this->chunks = array();
        }
        return $res;
    }

    public static function grab($token)
    {
        $instance = new self;
        $token->genCSS($instance);
        unset($token);
        return join('', $instance->chunks);
    }
}