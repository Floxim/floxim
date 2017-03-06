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
        $this->initLinks();
        $this->loadExisting();
        foreach ( $this->source as $item) {
            $this->importItem($item);
        }
    }
    
    public function initLinks()
    {
        foreach ($this->source as $item) {
            $type = $item[1];
            if (isset(self::$links[$type])) {
                continue;
            }
            $com = fx::component($type);
            $links = [];
            if (!$com) {
                self::$links[$type] = $links;
                continue;
            }
            $fields = $com->getAllFields()->find('type', 'link');
            foreach ($fields as $field) {
                $links[$field['keyword']] = $field->getTargetName();
            }
            self::$links[$type] = $links;
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
            default:
                return true;
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
            'format.linking_datatype' => 'component',
            'format.linking_component_id' => 'component',
            'format.linking_field_id' => 'field',
            'format.linking_field' => 'field',
            'format.list_fields.*' => 'field',
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
        $this->createItem($id, $type, $props);
    }
    
    protected $created = array();
    
    protected function createItem($id, $type, $props)
    {
        
        
        $append_res = $this->appendLinks($props, $type);
        
        $props = $append_res[0];
        $delayed = $append_res[1];
        
        $item = fx::data($type)->create($props);
        
        $item->setPayload('is_imported', true);
        
        if (count($delayed) > 0) {
            foreach ($delayed as $delayed_prop) {
                $delayed_prop []= $item;
                $this->delayed []= $delayed_prop;
            }
            
        }
        
        $item->save();
        
        $new_id = $item['id'];
        
        $this->id_map[$type][$id] = $new_id;
        if (!isset($this->created[$type])) {
            $this->created[$type] = array();
        }
        $this->created[$type][$new_id]= $props;
        
        foreach ($this->delayed as $c_delayed) {
            if ($c_delayed[2] != $id || $c_delayed[1] !== $type) {
                continue;
            }
            $delayed_item = $c_delayed[3];
            
            $delayed_item->digSet($c_delayed[0], $new_id);
            $delayed_item->save();
        }
        
    }
    
    protected $delayed = array();
    
    protected function appendLinks($props, $type) 
    {
        if (!isset(self::$links[$type])) {
            return [$props, []];
        }
        $links = self::$links[$type];
        
        $delayed = array();
        
        foreach ($links as $field => $target_type) {
            $path = explode(".", $field);
            $is_list = end($path) === '*';
            if ($is_list) {
                array_pop($path);
            }
            $value = fx::dig($props, $path);
            
            if (!$value) {
                continue;
            }
            
            if ($is_list) {
                if (!is_array($value)) {
                    continue;
                }
                foreach ($value as $c_key => $c_value) {
                    $c_path = $path;
                    $c_path []= $c_key;
                    
                    if (!isset($this->id_map[$target_type][$c_value])) {
                        if (!isset($this->source[$c_value.':'.$target_type])) {
                            continue;
                        }
                        $delayed []= array(join(".", $c_path), $target_type, $c_value);
                        continue;
                    }

                    $new_value = $this->id_map[$target_type][$c_value];
                    fx::digSet($props, $c_path, $new_value);
                }
            } else {
                if (!isset($this->id_map[$target_type][$value])) {
                    if (!isset($this->source[$value.':'.$target_type])) {
                        continue;
                    }
                    $delayed []= array($field, $target_type, $value);
                    continue;
                }
                $new_value = $this->id_map[$target_type][$value];
                fx::digSet($props, $path, $new_value);
            }
        }
        return [$props, $delayed];
    }
}