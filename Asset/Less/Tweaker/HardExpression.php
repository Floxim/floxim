<?php
/**
 * Created by PhpStorm.
 * User: Админ без пароля
 * Date: 30.05.2016
 * Time: 16:27
 */

namespace Floxim\Floxim\Asset\Less\Tweaker;


class HardExpression extends \Less_Tree_Quoted
{
    public function __construct($str)
    {
        parent::__construct('"'.$str.'"', $str, true);
    }

    public function compile($env)
    {
        return $this;
    }
}