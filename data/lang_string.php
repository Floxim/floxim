<?php
class fx_data_lang_string extends fx_data {
    protected $loaded = array();
    protected $lang = null;

    const DEFAULT_DICT = 'system';


    public function set_lang ($lang=null) {
        if (!$lang) {
            $this->lang = fx::config()->ADMIN_LANG;
        } else {
            $this->lang = $lang;
        }
    }
    
    public function get_multi_lang_fields() {
        return array(
            'lang'
        );
    }


    public function get_string($string, $dict = null) {
        if ($dict === null) {
            $dict = self::DEFAULT_DICT;
        }
        if (!isset($this->lang)) {
            $this->set_lang();
        }

        if (!isset($this->loaded[$dict][$this->lang])) {
            $this->load_dictionary($dict);
        }
        if (array_key_exists($string, $this->loaded[$dict][$this->lang])) {
            $res = $this->loaded[$dict][$this->lang][$string];
            return empty($res) ? $string : $res;
        }
    }

    public function check_string($string, $dict) {

        if (!isset($this->lang)) {
            $this->set_lang();
        }

        if (!isset($this->loaded[$dict][$this->lang])) {
            $this->load_dictionary($dict);
        }
        return array_key_exists($string, $this->loaded[$dict][$this->lang]);
    }

    public function get_dict_file($dict) {

        if (!isset($this->lang)) {
            $this->set_lang();
        }
        return fx::path('files', '/php_dictionaries/'.$this->lang.'.'.$dict.'.php');
    }

    public function drop_dict_files($dict) {
        $files = glob(fx::path('files', '/php_dictionaries/*.'.$dict.'.php'));
        if (!$files) {
            return;
        }
        foreach($files as $file) {
            unlink($file);
        }
    }

    protected function load_dictionary($dict) {

        if (!isset($this->lang)) {
            $this->set_lang();
        }
        $dict_file = self::get_dict_file($dict);
        if (!file_exists($dict_file)) {
            $this->dump_dictionary($dict, $dict_file);
        }
        $this->loaded[$dict][$this->lang] = @include($dict_file);
    }

    protected function dump_dictionary($dict, $file) {
        if (!isset($this->lang)) {
            $this->set_lang();
        }
        $data = fx::data('lang_string')->where('dict', $dict)->all();
        $res = array();
        foreach ($data as $s) {
            $res[$s['string']] = $s['lang_'.$this->lang];
        }

        fx::files()->writefile($file, "<?php\nreturn ".var_export($res,1).";");
    }

    public function add_string($string, $dict = null) {

        if (!isset($this->lang)) {
            $this->set_lang();
        }
        if ($dict === null) {
            $dict = self::DEFAULT_DICT;
        }
        $this->create(
            array(
                'string' => $string,
                'dict' => $dict,
                'lang_en' => $string
            )
        )->save();
    }
    
}