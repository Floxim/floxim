<?php
class fx_lang_string extends fx_essence {
    public function validate() {
        if (!parent::validate()){
            return false;
        }
        $existing = fx::data('lang_string')
                    ->where('string', $this['string'])
                    ->where('dict', $this['dict'])
                    ->where('id', $this['id'], '!=')
                    ->all();
        foreach ($existing as $double) {
            if ($double['string'] == $this['string']) {
                fx::log('nop', $double['string'], $this['string']);
                $this->validate_errors []= 
                        'String "'.$this['string'].'" already exists in the "'.
                            $this['dict'].'" dictionary';
                return false;
            }
            fx::log('double by case?', $this, $double);
        }
        return true;
    }
    
    protected function _after_save() {
        parent::_after_save();
        fx::alang()->drop_dict_files($this['dict']);
    }
    
    protected function _after_delete() {
        parent::_after_delete();
        fx::alang()->drop_dict_files($this['dict']);
    }
}