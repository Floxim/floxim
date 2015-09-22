<?php

namespace Floxim\Floxim\System;

use Floxim\Floxim\Template;

abstract class Entity implements \ArrayAccess, Template\Entity
{

    // reference to the class object fx_data_
    //protected $finder;
    // field values
    protected $data = array();
    // the set of fields that have changed
    protected $modified = array();
    protected $modified_data = array();

    protected $validate_errors = array();

    protected $_form = null;
    
    // Extra data from forms etc.
    protected $payload = array();
    
    /** template entity interface */
    

    public function getFinder()
    {
        return fx::data($this->getType());
    }
    
    public function getPayload($key = null) 
    {
        return is_null($key) ? $this->payload : (isset($this->payload[$key]) ? $this->payload[$key] : null);
    }
    
    public function setPayload($key, $value)
    {
        $this->payload[$key] = $value;
        return $this;
    }

    // virtual field types
    const VIRTUAL_RELATION = 0;
    const VIRTUAL_MULTILANG = 1;
    
    const OFFSET_FIELD = 0;
    const OFFSET_RELATION = 1;
    const OFFSET_LANG = 2;
    const OFFSET_GETTER = 3;
    const OFFSET_SELECT = 4;
    const OFFSET_CALLBACK = 5;


    protected $is_loaded = false;

    public function __construct($input = array())
    {
        if (isset($input['data'])) {
            foreach ($input['data'] as $k => $v) {
                $this[$k] = $v;
            }
        }
        $this->is_loaded = true;
    }

    
    
    protected static $offset_meta = array();
    
    public function getAvailableOffsets()
    {
        $c_class = get_called_class();
        if (!isset(self::$offset_meta[$c_class])) {
            $res = array();
            $finder = $this->getFinder();
            $db_fields = array_keys(fx::schema($finder->getTable()));
            foreach ($db_fields as $field) {
                $res[$field] = array(
                    'type' => self::OFFSET_FIELD
                );
            }
            foreach ($finder->relations() as $rel_name => $rel) {
                $res[$rel_name] = array(
                    'type' => self::OFFSET_RELATION, 
                    'relation' => $rel
                );
            }
            foreach ($finder->getMultiLangFields() as $f) {
                $res[$f] = array(
                    'type' => self::OFFSET_LANG
                );
            }
            
            $entity_class = $c_class;
            $reflection = new \ReflectionClass($entity_class);
            $methods = $reflection->getMethods();
            foreach ($methods as $method) {
                if ($method::IS_PUBLIC && preg_match("~^_get(.+)$~", $method->name, $getter_offset)) {
                    $getter_offset = fx::util()->camelToUnderscore($getter_offset[1]);
                    $res[ $getter_offset ] = array(
                        'type' => self::OFFSET_GETTER,
                        'method' => $method->name
                    );
                }
            }
            
            self::$offset_meta[$c_class] = fx::collection($res);
        }
        return self::$offset_meta[$c_class];
    }

    public function save()
    {
        fx::trigger('before_save', array('entity' => $this));
        $this->beforeSave();
        $pk = $this->getPk();
        // update
        if (isset($this->data[$pk]) && $this->data[$pk]) {
            $this->beforeUpdate();
            if ($this->validate() === false) {
                $this->throwInvalid();
                return false;
            }
            // updated only fields that have changed
            $data = array();
            foreach ($this->modified as $v) {
                $data[$v] = $this->data[$v];
            }
            $this->getFinder()->update($data, array($pk => $this->data[$pk]));
            $this->saveMultiLinks();
            $this->afterUpdate();
        } // insert
        else {
            $this->beforeInsert();
            if ($this->validate() === false) {
                $this->throwInvalid();
                return false;
            }
            $id = $this->getFinder()->insert($this->data);
            $this->data['id'] = $id;
            $this->saveMultiLinks();
            $this->afterInsert();
        }
        $this->afterSave();
        fx::trigger('after_save', array('entity' => $this));
        $this->modified = array();
        $this->modified_data = array();
        $this->afterSaveDone();
        return $this;
    }
    
    protected function afterSaveDone()
    {
        
    }

    /**
     * Throw validation exception or append errors to form if it exists
     * @throws fx_entity_validation_exception
     */
    protected function throwInvalid()
    {
        $exception = new Exception\EntityValidation(
            fx::lang("Unable to save entity \"" . $this->getType() . "\"")
        );
        $exception->entity = $this;
        $exception->addErrors($this->validate_errors);
        $form = $this->getBoundForm();
        if ($form) {
            $exception->toForm($form);
        } else {
            throw $exception;
        }
    }

    protected function invalid($message, $field = null)
    {
        $error = array(
            'text' => $message
        );
        if ($field) {
            $error['field'] = $field;
        }
        $this->validate_errors[] = $error;
    }


    /*
     * Saves the fields links is determined in fx_data_content
     */
    protected function saveMultiLinks()
    {

    }

    protected function beforeSave()
    {

    }

    protected function afterSave()
    {
        $finder_class = get_class($this->getFinder());
        $finder_class::dropStoredStaticCache();
    }

    /**
     * Get a property data or an entire set of properties
     * @param strign $prop_name
     * @return mixed
     */
    public function get($prop_name = null)
    {
        if ($prop_name) {
            if (is_array($prop_name)) {
                $res = array();
                foreach ($prop_name as $real_name) {
                    if (is_scalar($real_name)) {
                        $res[$real_name]= $this->get($real_name);
                    }
                }
                return $res;
            }
            return $this->offsetGet($prop_name);
        }
        return $this->data;
    }

    public function set($item, $value = '')
    {
        if (is_array($item) || $item instanceof \Traversable) {
            foreach ($item as $k => $v) {
                $this->set($k, $v);
            }
            return $this;
        }
        $this->offsetSet($item, $value);
        return $this;
    }

    public function digSet($path, $value)
    {
        $parts = explode(".", $path, 2);
        if (count($parts) == 1) {
            $this->offsetSet($path, $value);
            return $this;
        }
        $c_value = $this[$parts[0]];
        if (!is_array($c_value)) {
            $c_value = array();
        }
        fx::digSet($c_value, $parts[1], $value);
        $this->offsetSet($parts[0], $c_value);
        return $this;
    }

    public function getId()
    {
        return $this->data[$this->getPk()];
    }

    public function delete()
    {
        $pk = $this->getPk();
        $this->beforeDelete();
        $this->getFinder()->delete($pk, $this->data[$pk]);
        $this->modified_data = $this->data;
        fx::trigger('after_delete', array('entity' => $this));
        $this->afterDelete();
    }
    
    public function isInstanceOf($type)
    {
        return $type === $this->getType();
    }

    public function validate()
    {
        return count($this->validate_errors) == 0;
    }

    public function loadFromForm($form, $fields = null)
    {
        $vals = $this->getFromForm($form, $fields);
        $this->set($vals);
        $this->bindForm($form);
        return $this;
    }

    public function bindForm(\Floxim\Form\Form $form)
    {
        $this->_form = $form;
    }
    
    public function getBoundForm()
    {
        return isset($this->_form) ? $this->_form : null;
    }


    protected function getFromForm($form, $fields = null)
    {
        if (is_array($fields)) {
            $vals = array();
            foreach ($fields as $f) {
                $vals[] = $form->$f;
            }
        } else {
            $vals = $form->getValues();
        }
        return $vals;
    }

    /**
     * 
     * @param \Floxim\Form\Form $form
     * @param type $fields
     * @return boolean
     * @throws \Exception
     */
    public function validateWithForm($form = null, $fields = null)
    {
        if ($form === null) {
            $form = $this->getBoundForm();
        } elseif ($form) {
            $this->bindForm($form);
        }
        if (!$form) {
            throw new \Exception('No form to validate with');
        }
        $this->loadFromForm($form, $fields);
        if (!$this->validate()) {
            $this->throwInvalid();
            return false;
        }
        if ($form->hasErrors()) {
            return false;
        }
        return true;
    }

    public function getValidateErrors()
    {
        return $this->validate_errors;
    }

    protected function getPk()
    {
        return 'id';
    }

    public function __toString()
    {
        $res = '';
        foreach ($this->data as $k => $v) {
            $res .= "$k = $v " . PHP_EOL;
        }
        return $res;
    }

    protected function beforeInsert()
    {
        return false;
    }

    protected function afterInsert()
    {
        $finder_class = get_class($this->getFinder());
        $finder_class::dropStoredStaticCache();
        return false;
    }

    protected function beforeUpdate()
    {
        return false;
    }

    protected function afterUpdate()
    {
        return false;
    }

    protected function beforeDelete()
    {
        return false;
    }

    protected function afterDelete()
    {
        $finder_class = get_class($this->getFinder());
        $finder_class::dropStoredStaticCache();
        return false;
    }

    protected static function isTemplateVar($var)
    {
        return $var[0] === '%';
        return mb_substr($var, 0, 1) === '%';
    }
    
    protected $allowTemplateOverride = true;

    /* Array access */
    public function offsetGet($offset)
    {

        if ($offset === 'id') {
            return isset($this->data['id']) ? $this->data['id'] : null;
        }
        
        // handle template-content vars like $item['%description']
        if ($offset[0] === '%') {
            $offset = mb_substr($offset, 1);
            if (!isset($this[$offset]) || $this->allowTemplateOverride) {
                $template = fx::env()->getCurrentTemplate();
                if ($template && $template instanceof Template\Template) {
                    $template_value = $template->v($offset . "_" . $this['id']);
                    if ($template_value) {
                        return $template_value;
                    }
                }
            }
        }
        
        
        
        $offset_type = null;
        $offsets = $this->getAvailableOffsets();
        if (isset($offsets[$offset])) {
            $offset_meta = $offsets[$offset];
            $offset_type = $offset_meta['type'];
        }
        
        // execute getter everytime
        if ($offset_type === self::OFFSET_GETTER) {
            return call_user_func(array($this, $offset_meta['method']));
        }
        
        // execute external callback
        if ($offset_type === self::OFFSET_CALLBACK) {
            return call_user_func($offset_meta['callback'], $this);
        }
        
        // we have stored value, so return it
        if (array_key_exists($offset, $this->data)) {
            return $this->data[$offset];
        }
        
        // multi-lang value
        if ($offset_type === self::OFFSET_LANG) {
            $lang_offset = $offset . '_' . fx::config('lang.admin');
            if (!empty($this->data[$lang_offset])) {
                return $this->data[$lang_offset];
            }
            return $this->data[$offset . '_en'];
        }
        
        // relation lazy-loading
        if ($offset_type === self::OFFSET_RELATION) {
            $finder = $this->getFinder();
            $finder->addRelated($offset, new Collection(array($this)));
            if (!isset($this->data[$offset])) {
                return null;
            }
            return $this->data[$offset];
        }
        
        if ($offset_type === self::OFFSET_SELECT) {
            $real_value = $this->data[$offset_meta['real_offset']];
            return $offset_meta['values'][$real_value];
        }
    }

    public function offsetSet($offset, $value)
    {
        
        
        $offset_exists = array_key_exists($offset, $this->data);
        
        $offsets = $this->getAvailableOffsets();
        $offset_type = null;
        if (isset($offsets[$offset])) {
            $offset_meta = $offsets[$offset];
            $offset_type = $offset_meta['type'];
        }
        
        switch($offset_type) {
            case self::OFFSET_LANG:
                $offset = $offset.'_'.fx::config('lang.admin');
                break;
            case self::OFFSET_RELATION:
                $relation = $offset_meta['relation'];
                if ($relation[0] === Finder::BELONGS_TO && $value instanceof Entity) {
                    $c_rel_field = $relation[2];
                    $value_id = $value['id'];
                    if ($c_rel_field && $value_id) {
                        $this[$c_rel_field] = $value_id;
                    }
                }
                break;
        }
        
        if (!$this->is_loaded) {
            $this->data[$offset] = $value;
            return;
        }
        
        // use non-strict '==' because int values from db becomes strings - should be fixed
        if ($offset_exists && $this->data[$offset] == $value) {
            return;
        }

        if (!isset($this->modified_data[$offset])) {
            $this->modified_data[$offset] = isset($this->data[$offset]) ? $this->data[$offset] : null;
            $this->modified[] = $offset;
        }
        $this->data[$offset] = $value;
    }
    
    public function offsetExists($offset)
    {
        if (array_key_exists($offset, $this->data)) {
            return true;
        }
        //if (self::isTemplateVar($offset)) {
        if ($offset[0] === '%') {
            return true;
        }
        $offsets = $this->getAvailableOffsets();
        if (isset($offsets[$offset])) {
            return true;
        }
        return false;
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    protected $type_keyword = null;

    public function getType()
    {
        if (is_null($this->type_keyword)) {
            $class = array_reverse(explode("\\", get_class($this)));
            $type = $class[1];
            $this->type_keyword = strtolower($type);
        }
        return $this->type_keyword;
    }

    /*
     * Add meta-data to be edited from the front
     * @param string $html the html code of record
     * @return string string with added meta-data
     */
    public function addTemplateRecordMeta($html, $collection, $index, $is_subroot)
    {
        return $html;
    }

    /**
     * Get meta info for the field in template
     * Here we handle only template vars, more complex implementation is in fx_content
     * @param string $field_keyword
     * @return array Meta info
     */
    public function getFieldMeta($field_keyword)
    {
        if (!self::isTemplateVar($field_keyword)) {
            return array();
        }
        $field_keyword = mb_substr($field_keyword, 1);
        return array(
            'var_type' => 'visual',
            'id'       => $field_keyword . '_' . $this['id'],
            'name'     => $field_keyword . '_' . $this['id'],
            // we need some more sophisticated way to guess the var type =)
            'type'     => 'string'
        );
    }

    public function isModified($field = null)
    {
        if ($field === null) {
            return count($this->modified) > 0;
        }
        if (!$this['id']) {
            return true;
        }
        return is_array($this->modified) && in_array($field, $this->modified);
    }
    
    public function setNotModified($field)
    {
        if (!$this->isModified($field)) {
            return $this;
        }
        unset ( $this->modified [array_search($field, $this->modified)]);
        return $this;
    }

    public function getOld($field)
    {
        if (!$this->isModified($field)) {
            return null;
        }
        return $this->modified_data[$field];
    }

    public function getModified()
    {
        return $this->modified;
    }
}

