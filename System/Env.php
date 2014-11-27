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
            $page = fx::data('page', $page);
        }
        $this->current['page'] = $page;
    }

    public function getPage()
    {
        return isset($this->current['page']) ? $this->current['page'] : null;
    }

    public function getPageId()
    {
        if (isset($this->current['page']) && is_object($this->current['page'])) {
            return $this->current['page']->get('id');
        }
        if (isset($this->current['page_id'])) {
            $this->current['page'] = fx::data('page', $this->current['page_id']);
            return $this->current['page_id'];
        }
        return null;
    }

    public function getSiteId()
    {
        return $this->getSite()->get('id');
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
            $home_page = fx::data('page')
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
            $page_id = $this->getPageId();
            if ($page_id) {
                $page = fx::data('page', $page_id);
                if ($page['layout_id']) {
                    $this->current['layout'] = $page['layout_id'];
                }
            }
            if (!isset($this->current['layout'])) {
                $this->current['layout'] = $this->getSite()->get('layout_id');
            }
            if (!isset($this->current['layout'])) {
                $this->current['layout'] = fx::data('layout')->one()->get('id');
            }
        }
        return $this->current['layout'];
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
}