<?php

namespace Floxim\Floxim\Component\Basic;

use Floxim\Floxim\System\Fx as fx;

class Controller extends \Floxim\Floxim\Controller\Frontoffice {
    protected function getConfigSources()
    {
        $sources = array();
        //$sources [] = fx::path('@module/' . fx::getComponentPath('floxim.main.content') . '/cfg.php');
        $com = $this->getComponent();
        $chain = $com->getChain();
        foreach ($chain as $com) {
            $com_file = fx::path('@module/' . fx::getComponentPath($com['keyword']) . '/cfg.php');
            if (file_exists($com_file)) {
                $sources[] = $com_file;
            }
        }
        return $sources;
    }
    
    /**
     * $_content_type may be one of the values
     * the table fx_component in the keyword field
     * @var string
     */
    protected $_content_type = null;

    /**
     * @return string
     */
    public function getContentType()
    {
        if (!$this->_content_type) {
            $com_name = fx::getComponentNameByClass(get_class($this));
            $this->_content_type = $com_name;
        }
        return $this->_content_type;
    }

    /**
     * Returns the component at the value of the property _content_type
     * @return fx_data_component
     */
    public function getComponent()
    {
        return fx::component($this->getContentType());
    }


    protected $_finder = null;

    /**
     * @return \Floxim\Floxim\System\Finder data finder
     */
    public function getFinder()
    {
        if (!is_null($this->_finder)) {
            return $this->_finder;
        }
        
        $com = $this->getComponent();
        
        $finder = fx::data($com['keyword']);
        
        if ($com->getFieldByKeyword('site_id')) {
            $finder->where('site_id', fx::env('site_id'));
        }
        if (!fx::isAdmin()) {
            $finder
                ->where('is_published', 1)
                ->where('is_branch_published', 1);
        }
        $show_pagination = $this->getParam('pagination');
        $c_page = $this->getCurrentPageNumber();
        $limit = $this->getParam('limit');
        if ($show_pagination && $limit) {
            $finder->calcFoundRows();
        }
        if ($limit) {
            if ($show_pagination && $c_page != 1) {
                $finder->limit(
                    $limit * ($c_page - 1),
                    $limit
                );
            } else {
                $finder->limit($limit);
            }
        }
        if (($sorting = $this->getParam('sorting'))) {
            $dir = $this->getParam('sorting_dir');
            if ($sorting === 'manual') {
                $sorting = 'priority';
                $dir = 'ASC';
            }
            if (!$dir) {
                $dir = 'ASC';
            }
            $finder->order($sorting, $dir);
        }
        $this->_finder = $finder;
        return $finder;
    }

    protected function getControllerVariants()
    {
        $com = $this->getComponent();
        if (!$com) {
            return [];
        }
        return  $com->getChain()->getValues('keyword');
    }

    public function getActions()
    {
        $actions = parent::getActions();
        $com = $this->getComponent();
        if (!$com) {
            return [];
        }
        foreach ($actions as $action => &$info) {
            if (!isset($info['name'])) {
                $info['name'] = $com['name'] . ' / ' . $action;
            }
        }
        return $actions;
    }
    
    public function hasTypedAction()
    {
        return preg_match("~^list|record~", $this->action);
    }
    
    public function getTemplateAvailForTypeField()
    {
        if (!$this->hasTypedAction()) {
            return;
        }
        
        $com = $this->getComponent();
        
        $chain = $com->getChain();
        
        $avail_coms = [];
        foreach ($chain as $level) {
            $avail_coms []= [
                'id' => $level['keyword'],
                'name' => $level['name']
            ];
        }
        
        $res = [
            'name' => 'avail_for_type',
            'label' => 'Подходит для данных',
            'type' => 'livesearch',
            'values' => $avail_coms,
            //'is_multiple' => true,
            'value' => $com['keyword']
        ];
        return $res;
    }
    
    public function checkTemplateAvailForType($tv)
    {
        if (!$this->hasTypedAction()) {
            return true;
        }
        $val = $tv['avail_for_type'];
        if (!$val || $val === 'any') {
            return true;
        }
        $com = $this->getComponent();
        if ($com->isInstanceOfComponent($val)) {
            return true;
        }
        return false;
    }
}