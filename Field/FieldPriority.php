<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class FieldPriority extends \Floxim\Floxim\Component\Field\Entity
{
    public function getSqlType()
    {
        return "decimal(6,3)";
    }

    public function getCastType()
    {
        return 'float';
    }

    public function afterInsert()
    {
        $res = parent::afterInsert();
        $this->reorder();
        return $res;
    }

    public function reorder ($dataQuery = null)
    {
        if (!$dataQuery) {
            $dataQuery = fx::data($this['component']['keyword'])->order('id');
        }
        $dataQuery->select('@row_number:=ifnull(@row_number, 0)+1 as pos');
        $dataQuery->select('id');

        $q = 'update 
                `{{'.$this->getTable().'}}` t 
                JOIN (
                  select @row_number:=0
                ) as init
                JOIN (
                  '.$dataQuery->showQuery().'
                ) as pos ON t.id = pos.id
                set t.`'.$this['keyword'].'` = pos.pos';
        fx::db()->query($q);
    }

    public function getNewValue($entity, $relId, $dir = 'after')
    {
        $type = $this['component']['keyword'];
        $field = $this['keyword'];
        $relEntity = fx::data($type, $relId);
        $currentValue = $entity[$this['keyword']];
        if (!$relEntity) {
            fx::log('no rel entity', $relId, $type);
            return;
        }

        $map = [
            'before' => ['<', -1, 'desc'],
            'after' => ['>', 1, 'asc']
        ];

        list ($op, $multiplier, $sortDir) = $map[$dir];
        $nextEntity = fx::data($type)
            ->order($field, $sortDir)
            ->where($field, $relEntity[$field], $op)
            ->where('id', [$entity['id'], $relId], 'NOT IN')
            ->one();
        $relValue = $relEntity[$field];
        $nextValue = $nextEntity ? $nextEntity[$field] : $relValue + 1 * $multiplier;
        $res = ($relValue + $nextValue ) / 2;
        /*
        fx::log(
            fx::data($type)
                ->order($field, $sortDir)
                ->where($field, $relEntity[$field], $op)
                ->where('id', [$entity['id'], $relId], 'NOT IN')->showQuery(),
            'move',
            'e: '. $entity['id'] .' / '.  $entity['name']. ' / '.$currentValue,
            'ne: '.($nextEntity ? $nextEntity['id'].' / '.$nextEntity['name'] : null).' / '.$nextValue,
            're: '.$relEntity['id']. '/' . $relEntity['name'].' / '.$relValue,
            $dir,
            $currentValue,
            $nextValue,
            $relValue,
            $res,
            $multiplier
        );
        */
        return $res;
    }
}