<?php

namespace Floxim\Floxim\System;

class Env
{
    protected $current = array();


    public function set($var, $val)
    {
        $setter = 'set' . fx::util()->underscoreToCamel($var);
        if (method_exists($this, $setter)) {
            call_user_func(array($this, $setter), $val);
        } else {
            $this->current[$var] = $val;
        }
    }

    public function get($var)
    {
        $getter = 'get' . fx::util()->underscoreToCamel($var);
        if (method_exists($this, $getter)) {
            return call_user_func(array($this, $getter));
        }
        return isset($this->current[$var]) ? $this->current[$var] : null;
    }

    public function setSite($env)
    {
        $this->current['site'] = $env;
    }
    
    public function getUrl() {
        if (isset($this->current['url'])) {
            return $this->current['url'];
        }
        if ( ($page = $this->getPage() ) ) {
            return $page['url'];
        }
        return fx::path()->removeBase($_SERVER['REQUEST_URI']);
    }
    
    public function setUrl($url) {
        $this->current['url'] = $url;
    }
    
    public function getPath() {
        return fx::router()->getPath($this->getUrl());
    }


    /**
     * @return \Floxim\Floxim\System\Site
     */
    public function getSite()
    {
        if (!isset($this->current['site'])) {
            $this->current['site'] = fx::data('site')->getByHostName($_SERVER['HTTP_HOST'], 1);
        }
        return $this->current['site'];
    }

    public function getHost()
    {
        if (!isset($this->current['host'])) {
            $this->current['host'] = $_SERVER['HTTP_HOST'];
        }
        return $this->current['host'];
    }

    public function setAction($action)
    {
        $this->current['action'] = $action;
    }

    public function getAction()
    {
        return $this->current['action'];
    }

    public function setPage($page)
    {
        if (is_numeric($page)) {
            $page = fx::data('floxim.main.page', $page);
        }
        $this->current['page'] = $page;
    }

    public function getPage()
    {
        if (isset($this->current['page'])) {
            return $this->current['page'];
        }
        if (isset($this->current['url'])) {
            $page = fx::data('floxim.main.page', $this->current['url']);
            if ($page) {
                $this->setPage($page);
                return $page;
            }
        }
    }

    public function getPageId()
    {
        if (isset($this->current['page']) && is_object($this->current['page'])) {
            return $this->current['page']->get('id');
        }
        if (isset($this->current['page_id'])) {
            $this->current['page'] = fx::data('floxim.main.page', $this->current['page_id']);
            return $this->current['page_id'];
        }
        return null;
    }

    public function getSiteId()
    {
        $site = $this->getSite();
        return $site ? $site->get('id') : null;
    }

    public function setUser($user)
    {
        $this->current['user'] = $user;
    }

    public function getUser()
    {
        if (!isset($this->current['user'])) {
            $this->current['user'] = \Floxim\User\User\Entity::load();
        }
        return $this->current['user'];
    }

    public function setMainContent($str)
    {
        $this->current['main_content'] = $str;
    }

    public function getMainContent()
    {
        return $this->current['main_content'];
    }

    public function getHomeId()
    {
        if (!isset($this->current['home_id'])) {
            $site = $this->getSite();
            $home_page = fx::data('floxim.main.page')
                ->where('parent_id', 0)
                ->where('site_id', $site['id'])
                ->one();
            $this->current['home_id'] = $home_page['id'];
        }
        return $this->current['home_id'];
    }

    public function getIsAdmin()
    {
        return ($user = $this->getUser()) ? $user->isAdmin() : false;
    }

    public function getLayout()
    {
        if (!isset($this->current['layout'])) {
            $layout_preview = self::getLayoutPreview();
            if ( $layout_preview ) {
                $this->current['layout'] = $layout_preview[0];
                $this->current['layout_style_variant'] = $layout_preview[1];
                return $layout_preview[0];
            }
            $page_id = $this->getPageId();
            if ($page_id) {
                $page = fx::data('floxim.main.page', $page_id);
                if ($page['layout_id']) {
                    $this->current['layout'] = $page['layout_id'];
                }
            }
            if (!isset($this->current['layout'])) {
                $site = $this->getSite();
                if ($site) {
                    $this->current['layout'] = $site['layout_id'];
                    $this->current['layout_style_variant'] = $site['layout_style_variant'];
                }
            }
            if (!isset($this->current['layout'])) {
                $this->current['layout'] = fx::data('layout')->one()->get('id');
            }
        }
        return $this->current['layout'];
    }
    
    public function getLayoutStyleVariant()
    {
        $this->getLayout();
        return isset($this->current['layout_style_variant']) ? $this->current['layout_style_variant'] : null;
    }
    
    protected static function getLayoutPreviewCookieName($site_id = null)
    {
        if (!$site_id) {
            $site_id = fx::env('site_id');
        }
        return 'fx_layout_preview_'.$site_id;
    }
    
    /**
     * 
     * @param type $layout_id drop cookie if false
     */
    public function setLayoutPreview($layout_id, $style_variant = 'default')
    {
        if ($layout_id === false) {
            $cookie_time = time() - 60*60*60;
            unset($this->current['layout']);
            unset($this->current['layout_style_variant']);
        } else {
            $this->current['layout'] = $layout_id;
            $this->current['layout_style_variant'] = $style_variant;
            $cookie_time = time() + 60*60*24*365;
        }
        setcookie(self::getLayoutPreviewCookieName(), $layout_id.':'.$style_variant, $cookie_time, '/');
    }
    
    public function getLayoutPreview()
    {
        $cookie = self::getLayoutPreviewCookieName();
        $value = isset($_COOKIE[$cookie]) ? $_COOKIE[$cookie] : null;
        if ($value) {
            $value = explode(":", $value);
        }
        return $value;
    }

    public function getLayoutId()
    {
        return $this->getLayout();
    }

    protected $current_template_stack = array();

    /**
     * Add template object to global stack
     * @param Template $template
     */
    public function addCurrentTemplate($template)
    {
        $this->current_template_stack[] = $template;
    }

    /**
     * Remove the last running template from stack
     */
    public function popCurrentTemplate()
    {
        array_pop($this->current_template_stack);
    }

    /**
     * Get currently runnnig template
     */
    public function getCurrentTemplate()
    {
        if (count($this->current_template_stack) === 0) {
            return null;
        }
        return end($this->current_template_stack);
    }
    
    public function getFieldsForFilter()
    {
        $context = array();
        $page = $this->getPage();
        if ($page) {
            $page_field = $page->getComponent()->getFieldForFilter('context.page');
            $page_field['name'] = 'Текущая страница';
            $context []= $page_field;
            /*
            $context []= array(
                'id' => 'context.page',
                'name' => 'Текущая страница',
                'type' => 'entity',
                'entity_type' => $page['type'],
                'children' => $page->getComponent()->getFieldsForFilter('context.page')
            );
             * 
             */
        }
        $user_field = fx::component('floxim.user.user')->getFieldForFilter('context.user');
        $user_field['name'] = 'Текущий пользователь';
        $context []= $user_field;
        /*
        $context []= array(
            'id' => 'context.user',
            'name' => 'Текущий пользователь',
            'type' => 'entity',
            'children' => fx::component('floxim.user.user')->getFieldsForFilter('context.user')
        );
         * 
         */
        return $context;
    }
    
    public function getContextProp($prop) {
        $parts = explode(".", $prop);
        $obj_key = array_shift($parts);
        $obj = $this->get($obj_key);
        if (!$obj) {
            return null;
        }
        while (count($parts) > 0) {
            $part = array_shift($parts);
            if (  !(is_array($obj) || ($obj instanceof \ArrayAccess)) || !isset($obj[$part])) {
                return null;
            }
            $obj = $obj[$part];
        }
        return $obj;
    }
    
    public function getLang()
    {
        return fx::config('lang.admin');
    }
}