<?php

namespace Floxim\Floxim\Component\Lang;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity
{

    public function getName()
    {
        return $this->data['en_name'];
    }

    public function validate()
    {
        $res = true;
        if (!$this['en_name']) {
            $this->validate_errors[] = array(
                'field' => 'en_name',
                'text'  => fx::alang('Enter the name of the language', 'system')
            );
            $res = false;
        }
        if (!$this['lang_code']) {
            $this->validate_errors[] = array(
                'field' => 'lang_code',
                'text'  => fx::alang('Enter the code language', 'system')
            );
            $res = false;
        }
        return $res;
    }

    protected function getMultilangEntities()
    {
        return array('component', 'field', 'lang_string');
    }

    protected function beforeDelete()
    {
        $entities = $this->getMultilangEntities();

        foreach ($entities as $e) {
            $fields = fx::data($e)->getMultiLangFields();
            if (count($fields) > 0) {
                $q = 'ALTER TABLE `{{' . $e . '}}` ';
                $parts = array();
                foreach ($fields as $f) {
                    $parts [] = ' DROP COLUMN `' . $f . '_' . $this['lang_code'] . '` ';
                }
                $q .= join(", ", $parts);
                fx::db()->query($q);
            }
        }
    }

    protected function beforeInsert()
    {
        $entities = $this->getMultilangEntities();
        fx::log('ess', $entities);
        foreach ($entities as $e) {
            $fields = fx::data($e)->getMultiLangFields();
            fx::log('fld', $e, $fields);
            if (count($fields) > 0) {
                $q = "ALTER TABLE `{{" . $e . "}}` ";
                $parts = array();
                foreach ($fields as $f) {
                    $parts [] = "ADD COLUMN `" . $f . "_" . $this['lang_code'] . "` VARCHAR(255) ";
                }
                $q .= join(", ", $parts);
                fx::log('qr', $q);
                fx::db()->query($q);
            }
        }
    }
}