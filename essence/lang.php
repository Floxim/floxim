<?php
class fx_lang extends fx_essence {

    public function validate() {
        $res = true;
        if (!$this['en_name']) {
            $this->validate_errors[] = array('field' => 'en_name', 'text' => fx::alang('Enter the name of the language','system'));
            $res = false;
        }
        if (!$this['lang_code']) {
            $this->validate_errors[] = array('field' => 'lang_code', 'text' => fx::alang('Enter the code language','system'));
            $res = false;
        }
        return $res;
    }
    
    protected function _get_multilang_essences() {
        return array('component', 'field', 'lang_string');
    }
    
    protected function _before_delete() {
        $essences = $this->_get_multilang_essences();
        
        foreach ($essences as $e) {
            $fields = fx::data($e)->get_multi_lang_fields();
            if (count($fields) > 0) {
                $q = 'ALTER TABLE `{{'.$e.'}}` ';
                $parts = array();
                foreach ($fields as $f) {
                    $parts []= ' DROP COLUMN `'.$f.'_'.$this['lang_code'].'` ';
                }
                $q .= join(", ", $parts);
                fx::db()->query($q);
            }
        }
    }

    protected function _before_insert() {
        $essences = $this->_get_multilang_essences();
        fx::log('ess', $essences);
        foreach ($essences as $e) {
            $fields = fx::data($e)->get_multi_lang_fields();
            fx::log('fld', $e, $fields);
            if (count($fields) > 0) {
                $q = "ALTER TABLE `{{".$e."}}` ";
                $parts = array();
                foreach ($fields as $f) {
                    $parts []= "ADD COLUMN `".$f."_".$this['lang_code']."` VARCHAR(255) ";
                }
                $q .= join(", ", $parts);
                fx::log('qr', $q);
                fx::db()->query($q);
            }
        }
    }
}