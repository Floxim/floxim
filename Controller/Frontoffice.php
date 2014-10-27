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

    public function assign($key, $value)
    {
        $this->_result[$key] = $value;
    }

    public function process()
    {
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
        return $result;
    }

    public function ajaxForm($form = null)
    {
        if (!$form) {
            $form = new Form\Form();
        }
        $form['action'] = '/~ajax/';
        $form['method'] = 'POST';
        $form['ajax'] = true;
        $form->addField(array(
            'type'  => 'hidden',
            'name'  => '_ajax_infoblock_id',
            'value' => $this->getParam('infoblock_id')
        ));
        $this->_meta['ajax_access'] = true;
        return $form;
    }
}