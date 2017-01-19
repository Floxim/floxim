<?php

namespace Floxim\Floxim\Component\Infoblock;

use Floxim\Floxim\System;
use Floxim\Floxim\Template;
use Floxim\Floxim\Component;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity implements Template\Entity
{

    protected $_visual = array();
    
    public function beforeSave() {
        if ($this['is_preset']) {
            $this['site_id'] = null;
            $this['page_id'] = null;
            $this['scope_id'] = null;
        }
        return parent::beforeSave();
    }
    
    public function setVisual(Component\InfoblockVisual\Entity $visual)
    {
        $this->_visual[$visual['theme_id']] = $visual;
    }
    
    public function getVisual($theme_id = null)
    {
        if (!$theme_id) {
            if ($this['site_id'] === fx::env('site_id')) {
                $theme_id = fx::env('theme_id');
            } else {
                $theme_id = $this['site']['theme_id'];
            }
        }
        
        if (isset($this->_visual[$theme_id])) {
            return $this->_visual[$theme_id];
        }
        $visual = $this['visuals']->findOne('theme_id', $theme_id);
        if (!$visual) {
            $visual = fx::data('infoblock_visual')->create(
                array(
                    'theme_id' => $theme_id,
                    'is_stub' => true,
                    'infoblock_id' => $this['id']
                )
            );
            $this['visuals'][]= $visual;
            $this->_visual[$theme_id] = $visual;
        }
        return $visual;
    }

    /*
    public function setVisual(Component\InfoblockVisual\Entity $visual)
    {
        $this->_visual[$visual['layout_id'].','.$visual['layout_style_id']] = $visual;
    }

    public function getVisual($layout_id = null)
    {
        if (!$layout_id) {
            $layout_id = fx::env('layout_id').','.fx::env()->getLayoutStyleVariantId();
        }
        $layout_parts = explode(',', $layout_id);
        if (!isset($this->_visual[$layout_id])) {
            $stored = $this['visuals']->findOne(
                function($vis) use ($layout_parts) {
                    return $vis['layout_id'] == $layout_parts[0] && $vis['layout_style_id'] == $layout_parts[1];
                }
            );
            if ($stored) {
                $this->_visual[$layout_id] = $stored;
            } else {
                $i2l_params = array(
                    //'layout_id' => $layout_id,
                    'layout_id' => $layout_parts[0],
                    'layout_style_id' => $layout_parts[1],
                    'is_stub'   => true
                );
                if (($ib_id = $this->get('id'))) {
                    $i2l_params['infoblock_id'] = $ib_id;
                }
                $i2l = fx::data('infoblock_visual')->create($i2l_params);
                $this->_visual[$layout_id] = $i2l;
                $this['visuals'][]= $i2l;
            }
        }
        return $this->_visual[$layout_id];
    }
     * 
     */

    public function getType()
    {
        return 'infoblock';
    }

    public function isLayout()
    {
        return $this->getPropInherited('controller') == 'layout' && $this->getPropInherited('action') == 'show';
    }

    protected function afterDelete()
    {
        $killer = function ($cv) {
            $cv->delete();
        };
        fx::data('infoblock_visual')->where('infoblock_id', $this['id'])->all()->apply($killer);
        fx::data('infoblock')->where('parent_infoblock_id', $this['id'])->all()->apply($killer);
    }

    public function getOwnedContent()
    {
        $content = fx::data('floxim.main.content')->where('infoblock_id', $this['id'])->all();
        return $content;
    }

    public function getParentInfoblock()
    {
        if (!($parent_ib_id = $this->get('parent_infoblock_id'))) {
            return;
        }
        return fx::data('infoblock', $parent_ib_id);
    }

    public function getPropInherited($path_str, $layout_id = null)
    {
        $own_result = null;
        $parent_result = null;
        $path = explode(".", $path_str);
        if ($path[0] == 'visual') {
            $c_i2l = $this->getVisual($layout_id);
            $vis_path_str = join(".", array_slice($path, 1));
            $own_result = fx::dig($c_i2l, $vis_path_str);
        } else {
            $own_result = fx::dig($this, $path_str);
        }
        if ($own_result && !is_array($own_result)) {
            return $own_result;
        }
        if (($parent_ib = $this->getParentInfoblock())) {
            $parent_result = $parent_ib->getPropInherited($path_str, $layout_id);
        }
        if (is_array($own_result) && is_array($parent_result)) {
            return array_merge($parent_result, $own_result);
        }
        return $own_result ? $own_result : $parent_result;
    }

    public function getRootInfoblock()
    {
        $cib = $this;
        while ($cib['parent_infoblock_id']) {
            $cib = $cib->getParentInfoblock();
        }
        return $cib;
    }

    public function initController()
    {
        $controller = $this->getPropInherited('controller');
        $action = $this->getPropInherited('action');
        if (!$controller || !$action) {
            return null;
        }
        $ctr = fx::controller($controller . ':' . $action, $this->getPropInherited('params'));
        $ctr->setParam('infoblock_id', $this['id']);
        return $ctr;
    }

    protected $controller_cache = null;

    public function getIbController()
    {
        if (!$this->controller_cache) {
            $this->controller_cache = $this->initController();
        }
        return $this->controller_cache;
    }

    public function addParams($params)
    {
        $c_params = $this['params'];
        if (!is_array($c_params)) {
            $c_params = array();
        }
        $this['params'] = array_merge($c_params, $params);
        return $this;
    }
    
    public function getParam($param) {
        return isset($this['params'][$param]) ? $this['params'][$param] : null;
    }

    /**
     * Check if infoblock's scope.visibility allows the current user to see this block
     * @return bool Is the block available
     */
    public function isAvailableForUser()
    {
        $c_user = fx::user();
        
        $c_scope = $this['user_scope'];
        if (!$c_scope || !is_array($c_scope) || count($c_scope) === 0) {
            return true;
        }
        
        if ($c_user->isAdmin()) {
            return true;
        }
        
        foreach ($c_scope as $role) {
            switch ($role) {
                case 'admin':
                    if ($c_user->isAdmin()) {
                        return true;
                    }
                    break;
                case 'guest':
                    if ($c_user->isGuest()) {
                        return true;
                    }
                    break;
                case 'user':
                    if (!$c_user->isGuest()) {
                        return true;
                    }
                    break;
            }
        }
        return false;
    }

    public function isAvailableOnPage($page)
    {
        if ($this['site_id'] != $page['site_id']) {
            return;
        }

        if ($page->hasVirtualPath()) {
            $ids = $page->getPath()->getValues('id');
        } else {
            $ids = $page->getParentIds();
        }
        
        $ids [] = $page['id'];
        $ids [] = 0; // root
        
        if ($this['scope_type'] === 'one_page' && !in_array($this['page_id'], $ids)) {
            return false;
        }

        // if page_id=0 blunt - all pages, ignored by the filter scope.pages
        if ($this['page_id'] != 0) {
            // scope - "this page only"
            if (fx::dig($this, 'scope.pages') == 'this' && $this['page_id'] != $page['id']) {
                return false;
            }
            // scope - "this level, and we look parent
            if (fx::dig($this, 'scope.pages') == 'children' && $this['page_id'] == $page['id']) {
                return false;
            }
        }
        // check for compliance with the filter type page
        $scope_page_type = fx::dig($this, 'scope.page_type');
        if ($scope_page_type && fx::getComponentFullName($scope_page_type) != fx::getComponentFullName($page['type'])) {
            return false;
        }
        return true;
    }

    public function getScopeString()
    {
        list($cib_page_id, $cib_pages, $cib_page_type) = array(
            $this['page_id'],
            $this['scope']['pages'],
            $this['scope']['page_type']
        );

        if ($cib_page_id == 0) {
            $cib_page_id = fx::data('site', $this['site_id'])->get('index_page_id'); //$path[0]['id'];
        }
        if ($cib_pages == 'this') {
            $cib_page_type = '';
        }
        if ($cib_pages == 'all' || empty($cib_pages)) {
            $cib_pages = 'descendants';
        }

        return $cib_page_id . '-' . $cib_pages . '-' . $cib_page_type;
    }

    public function readScopeString($str)
    {
        list($scope_page_id, $scope_pages, $scope_page_type) = explode("-", $str);
        return array(
            'pages'     => $scope_pages,
            'page_type' => $scope_page_type,
            'page_id'   => $scope_page_id
        );
    }


    public function setScopeString($str)
    {
        $ss = $this->readScopeString($str);
        $new_scope = array(
            'pages'     => $ss['pages'],
            'page_type' => $ss['page_type']
        );
        $this['scope'] = $new_scope;
        $this['page_id'] = $ss['page_id'];
    }

    /**
     * Returns number meaning "strength" (exactness) of infoblock's scope
     */
    public function getScopeWeight()
    {
        $s = $this['scope'];
        $pages = isset($s['pages']) ? $s['pages'] : 'all';
        $page_type = isset($s['page_type']) ? $s['page_type'] : '';
        if ($pages == 'this') {
            return 4;
        }
        if ($page_type) {
            if ($pages == 'children') {
                return 3;
            } else {
                return 2;
            }
        }
        if ($pages == 'children') {
            return 1;
        }
        return 0;
    }

    public function isFake()
    {
        return $this['is_fake'];
        return preg_match("~^fake~", $this['id']);
    }

    public function overrideParam($param, $value)
    {
        $params = $this->getPropInherited('params');
        $params[$param] = $value;
        $this['params'] = $params;
    }

    public function override($data)
    {
        if (isset($data['params'])) {
            $data['params']['is_overriden'] = true;
            $this['params'] = $data['params'];
        }
        if (isset($data['visual'])) {
            $vis = $this->getVisual();
            
            $defined_variants = array();
            
            $template_fields = array('template', 'wrapper');
            
            $visuals = array();
            
            foreach ($data['visual'] as $k => $v) {
                if (in_array($k, array('template_visual', 'wrapper_visual'))) {
                    //$vis[$k] = array_merge($vis[$k], $v);
                    $visuals[$k] = array_merge( (array) $vis[$k], $v);
                } else {
                    if (in_array($k, $template_fields) && is_numeric($v)) {
                        $defined_variants[$k] = true;
                        $k = $k.'_variant_id';
                    }
                    $vis[$k] = $v;
                }
            }
            
            
            foreach ($template_fields as $f) {
                if (!isset($defined_variants[$f])) {
                    $vis[$f.'_variant_id'] = null;
                    unset($vis[$f.'_variant']);
                }
                $vis_key = $f.'_visual';
                if (isset($visuals[$vis_key])) {
                    $vis[$vis_key] = $visuals[$vis_key];
                }
            }
        }
        if (isset($data['controller']) && $data['controller'] && $data['controller'] !== 'null') {
            $ctr = explode(":", $data['controller']);
            $this['controller'] = $ctr[0];
            $this['action'] = $ctr[1];
        }
        if (isset($data['name'])) {
            $this['name'] = $data['name'];
        }
    }


    public function render()
    {
        $output = '';
        if (!$this->isAvailableForUser()) {
            return $output;
        }
        if (fx::isAdmin() || (!$this->isDisabled() && !$this->isHidden())) {
            $output = $this->getWrappedOutput();
        }
        $output = $this->addInfoblockMeta($output);
        if (($controller = $this->getIbController())) {
            $output = $controller->postprocess($output);
        }
        return $output;
    }

    protected $result_is_cached = false;
    protected $result_cache = null;

    /**
     * get result (plain data) from infoblock's controller
     */
    public function getResult()
    {
        if ($this->result_is_cached) {
            return $this->result_cache;
        }
        if ($this->isFake()) {
            $this->data['params']['is_fake'] = true;
        }
        $controller = $this->getIbController();
        if (!$controller) {
            $res = false;
        } else {
            try {
                $res = $controller->process();
            } catch (\Exception $e) {
                fx::log('controller exception', $controller, $e->getMessage(), $e);
                $res = '';
            }
        }
        $this->result_is_cached = true;
        $this->result_cache = $res;
        return $res;
    }

    /**
     * get controller_meta from the result
     */
    protected function getResultMeta()
    {
        $res = $this->getResult();
        if (!(is_array($res) || $res instanceof \ArrayAccess)) {
            return array();
        }
        return isset($res['_meta']) ? $res['_meta'] : array();
    }


    public function isDisabled()
    {
        $res = $this->getResult();
        if ($res === false) {
            return true;
        }
        $meta = $this->getResultMeta();
        return isset($meta['disabled']) && $meta['disabled'];
    }

    public function getTemplate()
    {
        $tpl_name = $this->getPropInherited('visual.template');
        if (!$tpl_name) {
            return false;
        }
        $parts = explode(":", $tpl_name);
        $tpl = \Floxim\Floxim\Template\Loader::loadTemplateVariant($parts[0], $parts[1]);
        
        // Assign template into env if this infoblock is the layout infoblock
        if ($this['controller'] === 'layout' && $this['action'] === 'show') {
            fx::env('theme_template', $tpl);
        }
        return $tpl;
    }

    protected $output_is_cached = false;
    protected $output_cache = null;
    protected $output_is_subroot = false;
    
    protected function applyParentContainerProps(\Floxim\Floxim\Template\Context $context)
    {
        if ($this->parent_container_props) {
            $rw = null;
            if (isset($this->parent_container_props['rw'])) {
                $rw = $this->parent_container_props['rw'];
                unset($this->parent_container_props['rw']);
            }
            $context->pushContainerProps($this->parent_container_props);
            if ($rw) {
                $rw = explode('-', $rw, 2);
                $rw[1] = (float) str_replace('-', '.', $rw[1]);
                $context->pushContainerWidth($rw[1], $rw[0]);
            }
        }
        $this->parent_container_props = null;
    }

    /**
     * get result rendered by ib's template (with no wrapper and meta)
     */
    public function getOutput()
    {
        if ($this->output_is_cached) {
            return $this->output_cache;
        }
        $result = $this->getResult();
        if ($result === false) {
            return false;
        }
        $meta = $this->getResultMeta();
        if (isset($meta['disabled']) && $meta['disabled']) {
            return false;
        }

        $tpl = $this->getTemplate();
        if (!$tpl) {
            return '';
        }
        $tpl_params = $this->getPropInherited('visual.template_visual');
        if (!is_array($tpl_params)) {
            $tpl_params = array();
        }

        // @todo: what if the result is object?
        if (is_array($result)) {
            $tpl_params = array_merge($tpl_params, $result);
        }
        $tpl_params['infoblock'] = $this;
        $is_admin = fx::isAdmin();
        
        $this->applyParentContainerProps($tpl->getContext());
        
        try {
            $this->output_cache = $tpl->render($tpl_params);
        } catch (\Exception $e) {
            fx::log('error while rendering...', $e->getMessage());
            $this->output_cache = $is_admin ? 'Error, see logs' : ';(';
        }
        if ($is_admin) {
            $this->infoblock_meta['template_params'] = $tpl->getRegisteredParams();
        }
        $this->output_is_subroot = $tpl->is_subroot;
        $this->output_is_cached = true;
        return $this->output_cache;
    }
    
    protected $infoblock_meta = array();

    /**
     * wrap ib's output
     */
    protected function getWrappedOutput()
    {
        $wrapper = $this->getPropInherited('visual.wrapper');
        if (!$wrapper) {
            return $this->getOutput();
        }
        $tpl_wrap = fx::template($wrapper);
        if (!$tpl_wrap) {
            return $this->getOutput();
        }
        $tpl_wrap->isWrapper(true);
        $wrap_params = $this->getPropInherited('visual.wrapper_visual');
        
        if (!is_array($wrap_params)) {
            $wrap_params = array();
        }
        $wrap_params['infoblock'] = $this;
        
        $is_admin = fx::isAdmin();
        
        try {
            $this->applyParentContainerProps($tpl_wrap->getContext());
            $result = $tpl_wrap->render($wrap_params);
            if ($is_admin) {
                $this->infoblock_meta['wrapper_params'] = $tpl_wrap->getRegisteredParams();
            }
            $this->output_is_subroot = $tpl_wrap->is_subroot;
        } catch (\Exception $e) {
            fx::log('error while wrapping ib #'.$this['id'], $e->getMessage());
            $result = $this->getOutput();
        }
        return $result;
    }

    public function isHidden()
    {
        $controller_meta = $this->getResultMeta();
        return isset($controller_meta['hidden']) && $controller_meta['hidden'];
    }

    protected function addInfoblockMeta($html_result)
    {
        $controller_meta = $this->getResultMeta();

        if (!fx::isAdmin() && (!isset($controller_meta['ajax_access']) || !$controller_meta['ajax_access'])) {
            return $html_result;
        }
        $ib_info = array('id' => $this['id']);
        if (($vis = $this->getVisual()) && $vis['id']) {
            $ib_info['visual_id'] = $vis['id'];
        }

        $ib_info['controller'] = $this->getPropInherited('controller') . ':' . $this->getPropInherited('action');
        $ib_info['name'] = $this['name'];
        
        if ($this['is_preset']) {
            $ib_info['is_preset'] = true;
        }
        
        $ib_info['scope_type'] = $this['scope_type'];

        $meta = array(
            'data-fx_infoblock' => $ib_info, // todo: psr0 need fix
            'class'             => 'fx_infoblock fx_infoblock_' . $this['id']
        );
        
        if ($this['user_scope'] && is_array($this['user_scope'])) {
            $meta['class'] .= ' fx-infoblock_has-user-scope';
            foreach ($this['user_scope'] as $scope_item) {
                $meta['class'] .= ' fx-infoblock_user-scope_'.$scope_item;
            }
        }
        
        foreach ($this->infoblock_meta as $meta_key => $meta_val) {
            // register only non-empty props
            if ($meta_val && !is_array($meta_val) || count($meta_val) > 0) {
                $meta['data-fx_'.$meta_key] = $meta_val;
            }
        }
        
        if (isset($_POST['_ajax_base_url'])) {
            $meta['data-fx_ajax_base_url'] = $_POST['_ajax_base_url'];
        }

        if ($this->isFake()) {
            $meta['class'] .= ' fx_infoblock_fake';
            if (!$this->getIbController()) {
                $controller_meta['hidden_placeholder'] = fx::alang('Fake infoblock data', 'system');
            }
        }

        if (isset($controller_meta['hidden']) && $controller_meta['hidden']) {
            $meta['class'] .= ' fx_infoblock_hidden';
        }
        if (count($controller_meta) > 0 && fx::isAdmin()) {
            $meta['data-fx_controller_meta'] = $controller_meta;
        }
        if ($this->isLayout()) {
            $meta['class'] .= ' fx_unselectable';
            $html_result = preg_replace_callback('~<body[^>]*?>~is', function ($matches) use ($meta) {
                $body_tag = Template\HtmlToken::createStandalone($matches[0]);
                $body_tag->addMeta($meta);
                return $body_tag->serialize();
            }, $html_result);
        } elseif ($this->output_is_subroot && preg_match("~^(\s*?)(<[^>]+?>)~", $html_result)) {
            $html_result = preg_replace_callback("~^(\s*?)(<[^>]+?>)~", function ($matches) use ($meta) {
                $tag = Template\HtmlToken::createStandalone($matches[2]);
                $tag->addMeta($meta);
                return $matches[1] . $tag->serialize();
            }, $html_result);
        } else {
            $html_proc = new Template\Html($html_result);
            $html_result = $html_proc->addMeta($meta,
                mb_strlen($html_result) > 10000 // auto wrap long html blocks without parsing
            );
        }
        return $html_result;
    }

    /**
     * Return all pages ID where presents this infoblock
     *
     * @return array
     */
    public function getPages()
    {
        list($page_id, $scope_pages, $scope_page_type) = array(
            $this['page_id'],
            $this['scope']['pages'],
            $this['scope']['page_type']
        );

        $result_pages = array();

        if ($scope_pages == 'this') {
            /**
             * Only current page
             */
            $result_pages[] = $page_id;
        } elseif (in_array($scope_pages, array('descendants', 'children'))) {
            /**
             * All descendants
             */
            $finder = fx::data('floxim.main.content')->descendantsOf($page_id);
            if ($scope_page_type) {
                $finder->where('type', $scope_page_type);
            }
            $result_pages = array_merge($result_pages, $finder->all()->getValues('id'));
            /**
             * With self page
             */
            if ($scope_pages == 'descendants') {
                $result_pages[] = $page_id;
            }
        }
        return array_unique($result_pages);
    }
    
    public function _getShortType()
    {
        $ctr = $this['controller'];
        if (!$ctr) {
            return null;
        }
        return preg_replace('~.+\..+\.~', '', $ctr);
    }
    
    public function getParentFinderConditions()
    {
        return $this->initController()->getParentFinderConditions();
    }
    
    public function createFromPreset()
    {
        if (!$this['is_preset']) {
            return;
        }
        $ib_params = $this->get();
        unset($ib_params['id']);
        unset($ib_params['is_preset']);
        $new_ib = $this->getFinder()->create( $ib_params );
        $c_page = fx::env('page');
        if ($c_page) {
            $new_ib['page_id'] = $c_page['id'];
            $new_ib['site_id'] = $c_page['site_id'];
        }
        $vis = $this->getVisual();
        $vis_params = $vis->get();
        unset($vis_params['id']);
        unset($vis_params['infoblock_id']);
        $new_vis = $vis->getFinder()->create($vis_params);
        //$new_ib->setVisual($new_vis);
        $new_ib['visuals'] = fx::collection($new_vis);
        return $new_ib;
    }
    
    protected $parent_container_props = null;
    
    public function bindLayoutContainerProps($props)
    {
        $this->parent_container_props = $props;
    }
    
    /*
     * Get a finder to collect all pages where the infoblock is present
     */
    public function getPageFinder()
    {
        if ($this['scope_type'] === 'custom') {
            return $this['scope_entity']
                ->getPageFinder()
                ->where('site_id', $this['site_id']);
        }
        $finder = fx::data('floxim.main.page')->where('site_id', $this['site_id']);
        switch ($this['scope_type']) {
            case 'all_pages':
            default:
                return $finder;
            case 'one_page':
                return $finder->where('id', $this['page_id']);
            case 'infoblock_pages':
                return $finder->where('infoblock_id', $this['scope_infoblock_id']);
        }
    }
    
    public function getSummary()
    {
        $vis = $this->getVisual();
        $area = $vis['area'];
        $template = fx::template($vis['template']);
        if ($template) {
            $template = $template->getInfo();
            $template = $template['name'];
        } else {
            $template = $vis['template'];
        }
        
        $res = array(
            'name' => $this['name'],
            'template_name' => $template,
            'area_id' => $area,
            'controller' => $this['controller']
        );
        
        switch ($this['scope_type']) {
            case 'one_page':
                $page = fx::data('floxim.main.page', $this['page_id']);
                $res['scope_type'] = 'Страница';
                $res['scope_extra'] = $page['name'];
                break;
            case 'all_pages':
                $res['scope_type'] = 'На всех страницах';
                break;
            case 'infoblock_pages':
                $scope_ib = fx::data('infoblock', $this['scope_infoblock_id']);
                $res['scope_type'] = 'Страницы блока';
                $res['scope_extra'] = $scope_ib['name'];
                break;
        }
        
        $page_finder = $this->getPageFinder();
        $example_page = $page_finder->one();
        
        if ($example_page) {
            $example_site = fx::data('site', $example_page['site_id']);
            $res['example_url'] = 'http://'
                                    .$example_site->getLocalDomain()
                                    .$example_page['url']
                                    .'#fx-locate-infoblock_'.$this['id'];
        }
        
        
        if ($this['site_id'] !== fx::env('site_id')) {
            $res['site'] = fx::data('site', $this['site_id'])->get('domain');
        }
        
        $ctr_names = array(
            'list_infoblock' => 'Собственные данные',
            'list_selected' => 'Данные, отобранные вручную',
            'list_filtered' => 'Данные по фильтру'
        );
        
        if (isset($ctr_names[$this['action']])) {
            $res['controller_name'] = $ctr_names[$this['action']];
        } else {
            $res['controller_name'] = 'Виджет';
        }
        
        if ($this['action'] === 'list_infoblock' || $this['action'] === 'list_selected') {
            $res['count_items'] = fx::db()->getVar(
                array(
                    'select count(*) as cnt from {{floxim_main_content}} where infoblock_id = %d',
                    $this['id']
                ),
                'cnt'
            );
        }
        
        return $res;
    }
    
    public function showSummary()
    {
        $summary = $this->getSummary();
        ob_start();?>
        <div>
            <a href="<?=$summary['example_url']?>" target="_blank"><?=$this['id']?></a>
        </div>
        <?php
        return ob_get_clean();
    }
}