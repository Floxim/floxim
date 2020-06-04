<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class FieldGroup extends \Floxim\Floxim\Component\Field\Entity
{
    public function getJsField($content) {
        $res = parent::getJsField($content);
        $res['keyword'] = $this['keyword'];
        $type = $this->getFormat('render_type', 'collapsable');
        $res['render_type'] = $type;
        switch ($type) {
            case 'collapsable': default:
                $res['is_expanded'] = $this->getFormat('expand', false);
                break;
        }
        return $res;
    }

    public function formatSettings()
    {
        $fields = [
            'render_type' => [
                'label' => 'Способ оторажения',
                'type' => 'livesearch',
                'values' => [
                    ['collapsable', 'Раскрывашка'],
                    ['line', 'Строка'],
                    ['tab', 'Вкладка']
                ]
            ]
        ];
        return $fields;
    }

    public function getSqlType()
    {
        return false;
    }
}