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
        
        if ($com->getFieldByKeyword('site_id', true)) {
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
        if (is_string($conds)) {
            $conds = json_decode($conds, true);
            $this->listen('query_ready', function ($e) use ($conds) {
                $q = $e['query'];
                if ($conds) {
                    $q->applyConditions($conds);
                }
            });
        }
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
        $this->trigger('result_ready');
    }
    
    public function getParentConfigFields()
    {
        return [];
    }
    
    
    protected function getPaginationUrlTemplate()
    {
        $url = $_SERVER['REQUEST_URI'];
        $url = preg_replace("~[\?\&]page=\d+~", '', $url);
        return $url . '##' . (preg_match("~\?~", $url) ? '&' : '?') . 'page=%d##';
    }

    protected function getCurrentPageNumber()
    {
        return isset($_GET['page']) ? $_GET['page'] : 1;
    }

    public function getPagination()
    {
        $limit = $this->getParam('limit');
        if ($limit == 0) {
            return;
        }
        $finder = clone $this->getFinder();
        $finder
            ->order(null)
            ->select(null)
            ->limit(null)
            ->calcFoundRows(false)
            ->select('count(*) as total');
        
        $total_rows = fx::db()->getVar( $finder->buildQuery() );
        if ($total_rows === 0) {
            return;
        }
        $total_pages = ceil($total_rows / $limit);  
        if ($total_pages == 1) {
            //return "hm ".$total_pages.' - '.$total_rows.' - '.$limit;
            return;
        }
        $links = [];
        
        $url_tpl = $this->getPaginationUrlTemplate();
        $base_url = preg_replace('~##.*?##~', '', $url_tpl);
        $url_tpl = str_replace("##", '', $url_tpl);
        $c_page = $this->getCurrentPageNumber();
        
        
        foreach (range(1, $total_pages) as $page_num) {
            $links[$page_num] = array(
                'active' => $page_num == $c_page,
                'page'   => $page_num,
                'url'    =>
                    $page_num == 1 ?
                        $base_url :
                        //sprintf($url_tpl, $page_num)
                        str_replace("%d", $page_num, $url_tpl)
            );
        }
        $res = array(
            'links'        => fx::collection($links),
            'total_pages'  => $total_pages,
            'total_items'  => $total_rows,
            'current_page' => $c_page
        );
        if ($c_page != 1) {
            $res['prev'] = $links[$c_page - 1]['url'];
        }
        if ($c_page != $total_pages) {
            $res['next'] = $links[$c_page + 1]['url'];
        }
        return $res;
        
        return $total_pages;
        /* ---- */
        return;
        $param_value = $this->getParam('pagination');
        if (!$param_value || $param_value === 'undefined' || $param_value === '0') {
            return null;
        }
        $total_rows = $this->getFinder()->getFoundRows();
        
        if ($total_rows == 0) {
            return null;
        }
        $limit = $this->getParam('limit');
        if ($limit == 0) {
            return null;
        }
        $total_pages = ceil($total_rows / $limit);
        if ($total_pages == 1) {
            return null;
        }
        $links = array();
        $url_tpl = $this->getPaginationUrlTemplate();
        $base_url = preg_replace('~##.*?##~', '', $url_tpl);
        $url_tpl = str_replace("##", '', $url_tpl);
        $c_page = $this->getCurrentPageNumber();
        foreach (range(1, $total_pages) as $page_num) {
            $links[$page_num] = array(
                'active' => $page_num == $c_page,
                'page'   => $page_num,
                'url'    =>
                    $page_num == 1 ?
                        $base_url :
                        //sprintf($url_tpl, $page_num)
                        str_replace("%d", $page_num, $url_tpl)
            );
        }
        $res = array(
            'links'        => fx::collection($links),
            'total_pages'  => $total_pages,
            'total_items'  => $total_rows,
            'current_page' => $c_page
        );
        if ($c_page != 1) {
            $res['prev'] = $links[$c_page - 1]['url'];
        }
        if ($c_page != $total_pages) {
            $res['next'] = $links[$c_page + 1]['url'];
        }
        return $res;
    }
}