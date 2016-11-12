<?php

namespace Floxim\Floxim\Controller;

use Floxim\Floxim\System;
use Floxim\Form;
use Floxim\Floxim\System\Fx as fx;

/**
 * Front office controller - common base for widgets and components
 */
class Frontoffice extends System\Controller
{
    protected $_meta = array();
    protected $_action_prefix = 'do_';

    protected $_result = array();

    public function assign($key, $value = null)
    {
        if (func_num_args() === 1 && (is_array($key) || $key instanceof \Traversable)) {
            foreach ($key as $real_key => $value){ 
                $this->assign($real_key, $value);
            }
            return;
        }
        $this->_result[$key] = $value;
    }
    
    public function getResult($key = null) {
        if (func_num_args() === 0) {
            return $this->_result;
        }
         if (isset($this->_result[$key]) ) {
             return $this->_result[$key];
         }
    }
    
    public function getInfoblock() 
    {
        return fx::data('infoblock', $this->getParam('infoblock_id'));
    }
    
    public function isNewBlock()
    {
        $ib = $this->getInfoblock();
        return !$ib || !$ib['id'];
    }
    
    protected function getProfiler()
    {
        $profile = fx::config('dev.profile_controllers');
        if ($profile) {
            return fx::profiler();
        }
    }

    public function process()
    {
        $profiler = $this->getProfiler();
        if ($profiler) {
            $profiler->block('<b style="color:#900;">ctr:</b> '.$this->getSignature());
        }
        $result = parent::process();
        if (is_string($result) || is_bool($result)) {
            return $result;
        }
        if ($result === null) {
            $result = array();
        }
        if (is_array($result) || $result instanceof \ArrayAccess) {
            $result = array_merge_recursive($result, $this->_result);
            if (!isset($result['_meta'])) {
                $result['_meta'] = array();
            }
            $result['_meta'] = array_merge_recursive($result['_meta'], $this->_meta);
        }
        if ($profiler) {
            $profiler->stop();
        }
        return $result;
    }

    public function ajaxForm($form = null)
    {
        if (!$form) {
            //$form = new Form\Form();
            $form = fx::data('floxim.form.form')->generate();
        } elseif (is_array($form)) {
            //$form = new Form\Form($form);
            $form = fx::data('floxim.form.form')->generate($form);
        }
        $form['action'] = '~ajax/';
        $form['method'] = 'POST';
        $form['ajax'] = true;
        $form->addField(array(
            'type'  => 'hidden',
            'name'  => '_ajax_infoblock_id',
            'value' => $this->getParam('infoblock_id')
        ));
        if (isset($_POST['_ajax_base_url'])) {
            $form->addField(array(
                'type' => 'hidden',
                'name' => '_ajax_base_url',
                'value' => $_POST['_ajax_base_url']
            ));
        }
        $this->_meta['ajax_access'] = true;
        return $form;
    }
}