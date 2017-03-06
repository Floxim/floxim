<?php

namespace Floxim\Floxim\Component\Basic;

use Floxim\Floxim\System\Fx as fx;

class Controller extends \Floxim\Floxim\Controller\Frontoffice {
    
    protected function getConfigSources()
    {
        $sources = array();
        $sources [] = fx::path('@floxim/Component/Basic/cfg.php');
        $com = $this->getComponent();
        // component has been removed from DB but for some reason hasen't been removed from source code
        if (!$com) {
            return [];
        }
        $chain = $com->getChain();
        foreach ($chain as $com) {
            $com_file = fx::path('@module/' . fx::getComponentPath($com['keyword']) . '/cfg.php');
            if (file_exists($com_file)) {
                $sources[] = $com_file;
            }
        }
        return $sources;
    }
    
    public function gcs()
    {
        return $this->getConfigSources();
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
    
    public function getConditionsField() {
        $com = $this->getComponent();
        $cond_fields = array(
            $com->getFieldForFilter('entity')
        );
        $context = fx::env()->getFieldsForFilter();
        
        foreach ($context as $context_prop) {
            $cond_fields []= $context_prop;
        }
        
        $field = array(
            'name' => 'conditions',
            'type' => 'condition',
            'context' => $context,
            'fields' => $cond_fields,
            'label' => false,
            'types' => fx::data('component')->getTypesHierarchy(),
            'tab' => array(
                'icon' => 'ib-list-filtered',
                'key' => 'conditions',
                'label' => fx::alang('Conditions', 'controller_component')
            )
        );
        return $field;
    }
    
    public function doListFiltered()
    {
        $conds = $this->getParam('conditions');
        if (!is_string($conds)) {
            return;
        }
        $conds = json_decode($conds, true);
        $this->listen('query_ready', function ($e) use ($conds) {
            $q = $e['query'];
            if ($conds) {
                $q->applyConditions($conds);
            }
        });
        $this->doList();
    }
    
    public function doList()
    {
        $items = $this->getResult('items');
        
        if (!$items) {
            $f = $this->getFinder();
            $this->trigger('query_ready', array('query' => $f));
            $items = $f->all();

            if (count($items) === 0) {
                $this->_meta['hidden'] = true;
            }
        }
        
        $items_event = fx::event('items_ready', array('items' => $items));
        
        $this->trigger($items_event);
        
        $items = $items_event['items'];
        
        $this->assign('items', $items);
        
        $items->limit = $this->getParam('limit');
        
        if (($pagination = $this->getPagination())) {
            $this->assign('pagination', $pagination);
        }
        $this->trigger('result_ready');
    }
    
    public function getParentConfigFields()
    {
        return [];
    }
}