<?php

namespace Floxim\Floxim\System;

class Export {
    
    protected $result = array();
    
    protected $params = array();
    
    protected $exported = array();
    
    
    public static function exportComponents($components) {
        $export = new Export(
            array(
                'related' => array(
                    'component' => 'fields',
                    'field' => 'select_values'
                )
            )
        );

        $export->export($components);
        $res = $export->getResult();
        return $res;
    }


    public function __construct($params = array(), $items = array())
    {
        $this->params = array_merge(
            array(
                'related' => array()
            ),
            $params
        );
        $this->export($items);
    }
    
    protected function getType($item)
    {
        $type = null;
        preg_match("~\\\([^\\\]+)\\\[^\\\]+$~", get_class($item), $type);
        $type = fx::util()->camelToUnderscore($type[1]);
        return $type;
    }
    
    protected function getItemProps(\Floxim\Floxim\System\Entity $item) 
    {
        $offsets = $item->getAvailableOffsets();
        $res = array();
        foreach ($offsets as $offset_name => $params) {
            if ($params['type'] !== \Floxim\Floxim\System\Entity::OFFSET_FIELD) {
                continue;
            }
            if ($offset_name === 'id') {
                continue;
            }
            $res[$offset_name] = $item[$offset_name];
        }
        return $res;
    }
    
    public function export($item)
    {
        if (is_array($item) || $item instanceof \Traversable) {
            foreach ($item as $real_item) {
                $this->export($real_item);
            }
            return;
        }
            
        $id = $item['id'];
        $type = $this->getType($item);
        $hash = $type.':'.$id;
        if (isset($this->exported[$hash])) {
            return;
        }
        $props = $this->getItemProps($item);
        $res = array($id, $type, $props);
        $this->result[]= $res;
        if (isset($this->params['related'][$type])) {
            foreach ( (array) $this->params['related'][$type] as $relation) {
                $related = $item[$relation];
                if ($related) {
                    $this->export($related);
                }
            }
        }
    }
    
    public function getResult()
    {
        return $this->result;
    }
}