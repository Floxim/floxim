<?php

namespace Floxim\Floxim\Component\LangString;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Finder extends System\Finder
{
    protected $loaded = array();
    protected $lang = null;

    const DEFAULT_DICT = 'system';


    public function setLang($lang = null)
    {
        if (!$lang) {
            $this->lang = fx::config('lang.admin');
        } else {
            $this->lang = $lang;
        }
    }

    public function getMultiLangFields()
    {
        return array(
            'lang'
        );
    }
    
    public function getLang() 
    {
        if (is_null($this->lang)) {
            $this->setLang();
        }
        return $this->lang;
    }


    public function getString($string, $dict = null)
    {
        if ($dict === null) {
            $dict = self::DEFAULT_DICT;
        }
        if (!isset($this->lang)) {
            $this->setLang();
        }

        if (!isset($this->loaded[$dict][$this->lang])) {
            $this->loadDictionary($dict);
        }
        if (array_key_exists($string, $this->loaded[$dict][$this->lang])) {
            $res = $this->loaded[$dict][$this->lang][$string];
            return empty($res) ? $string : $res;
        }
    }

    public function checkString($string, $dict)
    {

        if (!isset($this->lang)) {
            $this->setLang();
        }

        if (!isset($this->loaded[$dict][$this->lang])) {
            $this->loadDictionary($dict);
        }
        return array_key_exists($string, $this->loaded[$dict][$this->lang]);
    }

    public function getDictFile($dict)
    {

        if (!isset($this->lang)) {
            $this->setLang();
        }
        return fx::path('@files/php_dictionaries/' . $this->lang . '.' . $dict . '.php');
    }

    public function dropDictFiles($dict)
    {
        $files = glob(fx::path('@files/php_dictionaries/*.' . $dict . '.php'));
        if (!$files) {
            return;
        }
        foreach ($files as $file) {
            unlink($file);
        }
    }

    protected function loadDictionary($dict)
    {

        if (!isset($this->lang)) {
            $this->setLang();
        }
        $dict_file = self::getDictFile($dict);
        if (!file_exists($dict_file)) {
            $this->dumpDictionary($dict, $dict_file);
        }
        $this->loaded[$dict][$this->lang] = @include($dict_file);
    }

    protected function dumpDictionary($dict, $file)
    {
        if (!isset($this->lang)) {
            $this->setLang();
        }
        $data = fx::data('lang_string')->where('dict', $dict)->all();
        $res = array();
        foreach ($data as $s) {
            $res[$s['string']] = $s['lang_' . $this->lang];
        }

        fx::files()->writefile($file, "<?php\nreturn " . var_export($res, 1) . ";");
    }

    public function addString($string, $dict = null)
    {

        if (!isset($this->lang)) {
            $this->setLang();
        }
        if ($dict === null) {
            $dict = self::DEFAULT_DICT;
        }
        $this->create(array(
            'string'  => $string,
            'dict'    => $dict,
            'lang_en' => $string
        ))->save();
    }

}