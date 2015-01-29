<?php

namespace Floxim\Floxim\System;

use Floxim\Floxim\Template;

class Tree extends Collection {
    
    protected $childrenKey = 'children';
    protected $parentKey = 'parent_id';
    protected $extraRootIds = array();

    public function __construct($data, $children_key = 'children', $extra_root_ids = array())
    {
        if (!$data instanceof Collection) {
            $data = fx::collection($data);
        }
        $data->fork($this);
        $this->childrenKey = $children_key;
        $this->extraRootIds = $extra_root_ids;
        $this->build($data);
    }
    
    public function build($data) 
    {
        $index_by_parent = array();
        
        $children_key = $this->childrenKey;

        foreach ($data as $item) {
            if (in_array($item['id'], $this->extraRootIds)) {
                continue;
            }
            $pid = $item[$this->parentKey];
            if (!isset($index_by_parent[$pid])) {
                $index_by_parent[$pid] = $this->fork();
                $index_by_parent[$pid]->addFilter($this->parentKey, $pid);
            }
            $index_by_parent[$pid] [] = $item;
        }
        
        $non_root = array();

        foreach ($data as $item) {
            if (isset($index_by_parent[$item['id']])) {
                if (isset($item[$children_key]) && $item[$children_key] instanceof \Floxim\Floxim\System\Collection) {
                    $item[$children_key]->concat($index_by_parent[$item['id']]);
                } else {
                    $item[$children_key] = $index_by_parent[$item['id']];
                }
                $non_root = array_merge($non_root, $index_by_parent[$item['id']]->getValues('id'));
            } elseif (!isset($item[$children_key])) {
                $item[$children_key] = fx::collection();
            }
        }
        $this->data = $data->findRemove('id', $non_root)->getData();
        if ($this->count() > 0) {
            $first_item = $this->first();
            $this->addFilter($this->parentKey, $first_item[$this->parentKey]);
        }
    }
}