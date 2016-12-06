<?php

namespace Floxim\Floxim\System;

class Import {
    
    protected $source = array();
    
    protected $id_map = array();
    
    protected $existing = array();
    
    public function __construct($source) {
        foreach ($source as $item) {
            $this->source[$this->getHash($item)] = $item;
        }
    }
    
    protected function getHash($item)
    {
        return $item[0].':'.$item[1];
    }
    
    public function run()
    {
        $this->loadExisting();
        foreach ( $this->source as $item) {
            $this->importItem($item);
        }
    }
    
    protected function loadExisting()
    {
        $types = array();
        foreach ($this->source as $item) {
            $type = $item[1];
            if (!isset($types[$type])) {
                $types[$type] = array();
            }
            $types[$type][]= $item[0];
        }
        foreach ($types as $type => $ids) {
            $existing = fx::data($type, $ids);
            $this->existing[$type] = $existing->getValues(
                function($item) {
                    return $item;
                }, 
                'id'
            );
        }
    }
    
    protected function areTheSame($existing, $imported, $type)
    {
        switch ($type){
            case 'component':
                return $existing['keyword'] === $imported['keyword'];
            case 'field':
                return $existing['keyword'] === $imported['keyword'];
        }
    }
    
    protected function getExistingId($item)
    {
        list($id, $type, $props) = $item;
        
        if (
            isset($this->existing[$type][$id]) &&
            $this->areTheSame($this->existing[$type][$id], $props, $type)
        ) {
            return $id;
        }
        
        switch ($type) {
            case 'component':
                $com = fx::component($props['keyword']);
                if ($com) {
                    return $com['id'];
                }
                break;
            case 'field':
                $com_id = $props['component_id'];
                if (isset($this->id_map['component'][$com_id])) {
                    $real_com_id = $this->id_map['component'][$com_id];
                    if (isset($this->created['component'][$real_com_id])) {
                        return;
                    }
                    $existing_com = fx::component($this->id_map['component'][$com_id]);
                    if (!$existing_com) {
                        return;
                    }
                    $existing_field = $existing_com->getAllFields()->findOne('keyword', $props['keyword']);
                    if ($existing_field && $this->areTheSame($existing_field, $props, $type)) {
                        return $existing_field['id'];
                    }
                }
                break;
        }
    }
    
    protected static $links = array(
        'component' => array(
            'parent_id' => 'component'
        ),
        'field' => array(
            'component_id' => 'component',
            'format.target' => 'component',
            'parent_field_id' => 'field'
        ),
        'select_value' => array(
            'field_id' => 'field'
        )
    );


    protected function importItem($item)
    {
        list($id, $type, $props) = $item;
        
        $existing_id = $this->getExistingId($item);
        if ($existing_id) {
            if (!isset($this->id_map[$type])) {
                $this->id_map[$type] = array();
            }
            $this->id_map[$type][$id] = $existing_id;
            return;
        }
        
        $props = $this->appendLinks($props, $type);
        
        $this->createItem($id, $type, $props);
    }
    
    protected $created = array();
    
    protected function createItem($id, $type, $props)
    {
        $item = fx::data($type)->create($props);
        $item->save();
        
        $new_id = $item['id'];
        
        $this->id_map[$type][$id] = $new_id;
        if (!isset($this->created[$type])) {
            $this->created[$type] = array();
        }
        $this->created[$type][$new_id]= $props;
    }
    
    protected function appendLinks($props, $type) 
    {
        if (!isset(self::$links[$type])) {
            return $props;
        }
        $links = self::$links[$type];
        
        foreach ($links as $field => $target_type) {
            $path = explode(".", $field);
            $value = fx::dig($props, $path);
            if (!$value) {
                continue;
            }
            if (!isset($this->id_map[$target_type][$value])) {
                if (!isset($this->source[$value.':'.$target_type])) {
                    //fx::debug('use old');
                    continue;
                }
                throw new Exception('Lost id?');
            }
            
            $new_value = $this->id_map[$target_type][$value];
            fx::digSet($props, $path, $new_value);
            //fx::debug('replaced', $props, $path, $field, $value, $new_value);
        }
        return $props;
    }
}