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
    
    public function getDeclensionField($value = array())
    {
        $decl = $this['declension'];
        $f = array(
            'type' => 'set',
            'without_add' => true,
            'without_delete' => true,
            'labels' => array(
                ''
            ),
            'tpl' => array(
                array(
                    'type' => 'html',
                    'name' => 'desc'
                )
            ),
            'values' => array(
                
            )
        );
        foreach ($decl as $num => $num_props) {
            $f['labels'][]= $num_props['description'];
            $f['tpl'][]= array(
                'name' => $num
            );
            foreach ($num_props['values'] as $case => $case_props) {
                if (!isset($f['values'][$case])) {
                    $f['values'][$case] = array('desc' => $case_props['name']);
                }
                $f['values'][$case][$num] = isset($value[$case][$num]) ? $value[$case][$num] : "";
            }
        }
        return $f;
    }
}

/**
 array(
  'singular' => array(
    'description' => 'Единственное число',
    'values' => array(
      'nom' => array('name' => 'Именительный', 'description' => 'Кто? Что?', 'placeholder' => 'Новость', 'required' => true),
      'gen' => array('name' => 'Родительный', 'description' => 'Кого? Чего?', 'placeholder' => 'Новости', 'required' => true),
      'dat' => array('name' => 'Дательный', 'description' => 'Кому? Чему?', 'placeholder' => 'Новости'),
      'acc' => array('name' => 'Винительный', 'description' => 'Кого? Что?', 'placeholder' => 'Новость', 'required' => true),
      'inst' => array('name' => 'Творительный', 'description' => 'Кем? Чем?', 'placeholder' => 'Новостью'),
      'prep' => array('name' => 'Предложный', 'description' => 'О ком? О чём?', 'placeholder' => 'Новости')
  )
  ),
  'plural' => array(
    'description' => 'Множественное число',
    'values' => array(
      'nom' => array('name' => 'Именительный', 'description' => 'Кто? Что?', 'placeholder' => 'Новости', 'required' => true),
      'gen' => array('name' => 'Родительный', 'description' => 'Кого? Чего?', 'placeholder' => 'Новостей', 'required' => true),
      'dat' => array('name' => 'Дательный', 'description' => 'Кому? Чему?', 'placeholder' => 'Новостям'),
      'acc' => array('name' => 'Винительный', 'description' => 'Кого? Что?', 'placeholder' => 'Новости', 'required' => true),
      'inst' => array('name' => 'Творительный', 'description' => 'Кем? Чем?', 'placeholder' => 'Новостями'),
      'prep' => array('name' => 'Предложный', 'description' => 'О ком? О чём?', 'placeholder' => 'Новостях')
  )
)
);
 * 
 */