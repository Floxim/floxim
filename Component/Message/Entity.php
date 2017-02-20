<?php
namespace Floxim\Floxim\Component\Message;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\Component\Basic\Entity
{
    public function getDefaultBoxFields()
    {
        return array(
            array(
                array('keyword' => 'name', 'template' => 'header_value'),
            ),
            array(
                array('keyword' => 'text', 'template' => 'text_value')
            )
        );
    }
}