<?php

namespace Floxim\Floxim\Controller;

use Floxim\Floxim\System;
use Floxim\Floxim\Admin;
use Floxim\Floxim\System\Fx as fx;

class Layout extends System\Controller
{

    public function show()
    {

        $page_id = $this->getParam('page_id', fx::env('page_id'));
        $layout_id = $this->getParam('layout_id', fx::env('layout_id'));

        // add admin files bundle BEFORE site scripts/styles
        if (!$this->getParam('ajax_mode') && fx::isAdmin()) {
            Admin\Controller\Admin::addAdminFiles();
        }
        $page_infoblocks = fx::router('front')->getPageInfoblocks($page_id, $layout_id);
        fx::page()->setInfoblocks($page_infoblocks);
        
        
        $ib = fx::data('infoblock', $this->getParam('infoblock_id'));
        $layout_keyword = 'default';
        if ($ib) {
            $vis = $ib->getVisual();
            if ($vis) {
                $c_keyword = preg_replace("~^.+?\:~", '', $vis['template']);
                if ($c_keyword !== '_layout_body') {
                    $layout_keyword = $c_keyword;
                }
            }
        }
        
        $path = fx::env('page')->getPath();
        $current_page = $path->last();
        $res = array(
            'page_id'      => $page_id,
            'path'         => $path,
            'current_page' => $current_page,
            'layout_keyword' => $layout_keyword
        );
        return $res;
    }

    public function postprocess($html)
    {
        if ($this->getParam('ajax_mode')) {
            $html = preg_replace("~^.+?<body[^>]*?>~is", '', $html);
            $html = preg_replace("~</body>.+?$~is", '', $html);
        } else {
            $page = fx::env('page');
            $meta_title = empty($page['title']) ? $page['name'] : $page['title'];
            $this->showAdminPanel();
            $html = fx::page()
                ->setMetatags('title', $meta_title)
                ->setMetatags('description', $page['description'])
                ->setMetatags('keywords', $page['keywords'])
                ->postProcess($html);
        }
        return $html;
    }

    protected $_layout = null;


    protected function getLayout()
    {
        if ($this->_layout) {
            return $this->_layout;
        }
        $page = fx::data('floxim.main.page', $this->getParam('page_id'));
        if ($page['layout_id']) {
            $layout_id = $page['layout_id'];
        } else {
            $site = fx::data('site', $page['site_id']);
            $layout_id = $site['layout_id'];
        }
        $this->_layout = fx::data('layout', $layout_id);
        return $this->_layout;
    }

    public function findTemplate()
    {
        $layout = $this->getLayout();
        $tpl_name = 'layout_' . $layout['keyword'];
        return fx::template($tpl_name);
    }

    protected function showAdminPanel()
    {
        if (!fx::isAdmin()) {
            return;
        }
        // initialize the admin panel

        $p = fx::page();
        $js_config = new Admin\Configjs();
        $p->addJsText("\$fx.init(" . $js_config->getConfig() . ");");
        $p->setAfterBody(Admin\Controller\Adminpanel::panelHtml());
    }

    /*
     * Returns an array with options controller that you can use to find the template
     * Default - only controller itself,
     * For components overridden by adding inheritance chain
     */
    protected function getControllerVariants()
    {
        return array('floxim.component.layout');
    }
}