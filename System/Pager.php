<?php

namespace Floxim\Floxim\System;

use Floxim\Form;

class Pager {
    protected $finder;
    protected $total_items = null;
    protected $items_per_page = null;
    protected $url_template = null;
    protected $current_page = 1;
    
    public function __construct($finder, $params = array()) 
    {
        $this->finder = $finder;
        $this->finder->calcFoundRows();
        
        $params = array_merge(
            array(
                'items_per_page' => 20
            ), 
            $params
        );
        $this->setItemsPerPage($params['items_per_page']);
        if (isset($params['current_page'])) {
            $this->setCurrentPage($params['current_page']);
        }
        if (isset($params['url_template'])) {
            $this->setUrlTemplate($params['url_template']);
        }
    }
    
    public function setCurrentPage($current_page) 
    {
        $this->current_page = (int) $current_page;
        $this->updateFinderLimit();
    }
    
    public function getCurrentPage()
    {
        return $this->current_page;
    }
    
    public function setUrlTemplate($template) 
    {
        $this->url_template = $template;
    }
    
    public function setItemsPerPage($items_per_page) 
    {
        $this->items_per_page = (int) $items_per_page;
        $this->updateFinderLimit();
    }
    
    protected function updateFinderLimit()
    {
        $this->finder->page($this->getCurrentPage(), $this->getItemsPerPage());
    }
    
    public function getItemsPerPage()
    {
        return $this->items_per_page;
    }
    
    public function getTotalItems()
    {
        if (is_null($this->total_items)) {
            $this->total_items = $this->finder->getFoundRows();
        }
        return $this->total_items;
    }

    public function getPageUrl($page_num)
    {
        if (is_callable($this->url_template)) {
            return call_user_func($this->url_template, $page_num);
        }
        return preg_replace_callback(
            "~\[\[(.+?)\]\]~", 
            function($matches) use ($page_num) {
                if ($page_num == 1) {
                    return '';
                }
                return str_replace("#page_number#", $page_num, $matches[1]);
            },
            $this->url_template
        );
    }
    
    public function getTotalPages()
    {
        return ceil($this->getTotalItems() / $this->getItemsPerPage());
    }

    public function getPages() 
    {
        $total_pages = $this->getTotalPages();
        $res = array();
        foreach (range(1, $total_pages) as $page_num) {
            $res[$page_num] = $this->getPageUrl($page_num);
        }
        return $res;
    }
    
    public function getNextPageUrl()
    {
        if ($this->getCurrentPage() == $this->getTotalPages()) {
            return false;
        }
        return $this->getPageUrl($this->getCurrentPage() + 1);
    }
    public function getPreviousPageUrl()
    {
        if ($this->getCurrentPage() == 1) {
            return false;
        }
        return $this->getPageUrl($this->getCurrentPage() - 1);
    }
    
    public function getData()
    {
        return array(
            'current_page' => $this->getCurrentPage(),
            'items_per_page' => $this->getItemsPerPage(),
            'total_items' => $this->getTotalItems(),
            'total_pages' => $this->getTotalPages(),
            'pages' => $this->getPages(),
            'next_page' => $this->getNextPageUrl(),
            'previous_page' => $this->getPreviousPageUrl()
        );
    }
}