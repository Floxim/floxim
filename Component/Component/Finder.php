<?php
namespace Floxim\Floxim\Component\Component;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Finder extends System\Finder
{

    public function relations()
    {
        return array(
            'fields'   => array(
                self::HAS_MANY,
                'field',
                'component_id'
            ),
            'children' => array(
                self::HAS_MANY,
                'component',
                'parent_id'
            ),
            'parent'   => array(
                self::BELONGS_TO,
                'component',
                'parent_id'
            )
        );
    }

    public function getMultiLangFields()
    {
        return array(
            'name',
            'description',
            'item_name',
        );
    }

    public static function getKeywordField()
    {
        return 'keyword';
    }

    public function getById($id)
    {
        if (!is_numeric($id)) {
            $this->where('keyword', self::prepareSearchKeyword($id));
        } else {
            $this->where('id', $id);
        }
        return $this->one();
    }

    public static function prepareSearchKeyword($keyword)
    {
        if (!strstr($keyword, '.')) {
            $keyword = 'floxim.main.'.$keyword;
        }
        return $keyword;
    }

    public function getSelectValues($com_id = null)
    {
        //$items = $this->all();

        static $tree = null;
        static $items = null;
        if (is_null($tree)) {
            $items = static::getStaticCache();
            $recursive_get = function ($comp_coll, $result = array(), $level = 0) use (&$recursive_get, $items) {
                if (count($comp_coll) == 0) {
                    return $result;
                }
                foreach ($comp_coll as $comp) {
                    $result[] = array(
                        $comp['id'], 
                        str_repeat(" - ", $level) . $comp['name']. ' ('.$comp['keyword'].')', 
                        $level
                    );
                    $result = $recursive_get($items->find('parent_id', $comp['id']), $result, $level + 1);
                }
                return $result;
            };
            $root = $items->find('parent_id', 0);
            $tree = $recursive_get($root);
        }
        if (!$com_id) {
            return $tree;
        }
        if (!is_numeric($com_id)) {
            $com_id = $items->findOne('keyword', self::prepareSearchKeyword($com_id))->get('id');
        }
        $res = array();
        $found = false;
        $com_level = null;
        foreach ($tree as $item) {
            if ($item[0] === $com_id) {
                $res [] = $item;
                $found = true;
                $com_level = $item[2];
                continue;
            }
            if (!$found) {
                continue;
            }
            if ($found && $item[2] <= $com_level) {
                break;
            }
            $res [] = $item;
        }
        return $res;
    }

    public function getTree()
    {
        $items = $this->all();
        return $items->makeTree('parent_id', 'children');
    }

    public function createFull($data)
    {
        $result = array(
            'status'          => 'successful',
            'validate_result' => true,
            'validate_errors' => array(),
            'error'           => null,
            'component'       => null
        );
        $component = $this->create($data);

        if (!$component->validate()) {
            $result['status'] = 'error';
            $result['validate_result'] = false;
            $result['validate_errors'] = $component->getValidateErrors();
            return $result;
        }

        try {
            $component->save();
            $result['component'] = $component;

            fx::console('component scaffold --keyword=' . $component['id']);
            return $result;
        } catch (\Exception $e) {
            $result['status'] = 'error';
            $result['error'] = $e->getMessage();
            if ($component['id']) {
                $component->delete();
            }
        }
        return $result;
    }


    public static $isStaticCacheUsed = true;
    public static $fullStaticCache = true;
    public static $storeStaticCache = true;

    public static function prepareFullDataForCacheFinder($finder)
    {
        $finder->with('fields');
    }
    
    public static function loadFullDataForCache()
    {
        // get all components
        $collection = parent::loadFullDataForCache();
        // set it as current finder cache
        // to avoid sql queries from Component\Entity::getChain()
        static::setStaticCache($collection);
        // enable static cache manually
        static::$isStaticCacheUsed = true;
        $collection->indexUnique('keyword')->apply(function($com) {
            $com->getChain();
            $com->getAllFields();
            $com->getAvailableEntityOffsets();
        });
        return $collection;
    }
    
    public static function dropStoredStaticCache()
    {
        parent::dropStoredStaticCache();
        fx::cache('meta')->delete('schema');
    }

}