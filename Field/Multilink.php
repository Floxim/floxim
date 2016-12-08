<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\Component\Field;
use Floxim\Floxim\System\Fx as fx;

class Multilink extends \Floxim\Floxim\Component\Field\Entity
{
    public function getSqlType()
    {
        return false;
    }

    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        $render_type = $this['format']['render_type'];
        if (!$render_type) {
            $render_type = 'table';
        }
        switch ($render_type) {
            case 'livesearch':
                $res['type'] = 'livesearch';
                $res['is_multiple'] = true;
                $res['params'] = array(
                    'content_type' => $this->getEndDataType()
                );
                $rel = $this->getRelation();
                $related_relation = fx::data($rel[1])->relations();
                $linker_field = $related_relation[$rel[3]][2];
                $res['name_postfix'] = $linker_field;
                if (isset($content[$this['keyword']])) {
                    $res['value'] = array();
                    $linkers = $content[$this['keyword']]->linkers;
                    foreach ($content[$this['keyword']] as $num => $v) {
                        $res['value'] [] = array(
                            'id'       => $v['id'],
                            'name'     => $v['name'],
                            'value_id' => $linkers[$num]['id']
                        );
                    }
                }
                break;
            case 'table':
                $res = $this->getJsFieldTable($content, $res);
                break;
        }
        return $res;
    }
    
    protected function getJsFieldTable($content, $res)
    {
        
        $res['type'] = 'set';
        $res['tpl'] = array();
        $res['values'] = array();
        $res['types'] = array();
        $res['labels'] = array();
        
        $rel = $this->getRelation();
        
        $res['relation'] = $rel;
        
        $com = fx::component($rel[1]);
        
        fx::log($rel, $com, $this);
        
        $com_variants = $com->getAllVariants()->find(function($c) {
            return !$c['is_abstract'];
        });
        
        foreach ($com_variants as $com_variant) {
            $res['types'] []= array(
                'name' => $com_variant->getItemName(),
                'name_add' => $com_variant->getItemName('add'),
                'keyword' => $com_variant['keyword'],
                'content_type_id' => $com_variant['id']
            );
        }
        
        $all_fields = $com->getAllFieldsWithChildren();
        
        $list_fields_type = $this->getFormat('list_fields_type', 'all');
        $listed_fields = $this->getFormat('list_fields', array());
        
        switch ($list_fields_type) {
            case 'all':
                $all_fields = $all_fields->find(function($f) {
                    return $f['is_editable'] !== 0;
                });
                break;
            case 'only_listed':
                $found_fields = array();
                foreach ($listed_fields as $listed_field_id) {
                    $found_field = $all_fields->findOne('id', $listed_field_id);
                    if ($found_field) {
                        $found_fields []= $found_field;
                    }
                }
                $all_fields = fx::collection($found_fields);
                break;
            case 'not_listed':
                $all_fields = $all_fields->find(function($f) use ($listed_fields) {
                    return !in_array($f['id'], $listed_fields);
                });
                break;
        }
        
        $fields_by_com = array();
        
        
        foreach ($all_fields as $field) {
            if ($field['keyword'] == $rel[2]) {
                continue;
            }
            if ($field['keyword'] === 'is_published') {
                continue;
            }
            $res['labels'][]= $field['name'];
            $field_com = $field['component'];
            $com_keyword = $field_com['keyword'];
            if (!isset($fields_by_com[$com_keyword])) {
                $entity = fx::data($com_keyword)->create(
                   array( $rel[2] => $content['id'] )
                );
                $fields_by_com[$com_keyword] = $entity->getFormFields();
            }
            $com_fields = $fields_by_com[$com_keyword];
            $c_form_field = $com_fields->findOne('name', $field['keyword']);
            if (!$c_form_field) {
                $c_form_field = array(
                    'type' => 'html',
                    'name' => $field['keyword']
                );
            }
            $res['tpl'][]= $c_form_field;
        }
        
        if (isset($content[$this['keyword']])) {
            if ($rel[0] === System\Finder::HAS_MANY) {
                $linkers = $content[$this['keyword']];
            } else {
                $linkers = $content[$this['keyword']]->linkers;
            }
            foreach ($linkers as $linker) {
                
                $val_array = array();
                $val_array['_meta'] = array(
                    'id' => $linker['id'],
                    'type' => $linker->getType(),
                    'type_id' => $linker->getComponent()->get('id')
                );
                
                $linker_fields = $linker->getFormFields();
                
                foreach ($all_fields as $field) {
                    $field_keyword = $field['keyword'];
                    if ($field_keyword === 'type') {
                        $val_array[$field_keyword] = $linker->getComponent()->getItemName();
                        continue;
                    }
                    $linker_field = $linker_fields->findOne('name', $field_keyword);
                    if ($linker_field) {
                        $val_array[$field_keyword] = $linker_field['value'];
                    } else {
                        $val_array[$field_keyword] = $linker[$field_keyword];
                    }
                }
                $res['values'] [] = $val_array;
            }
        }
        
        return $res;
        
        
        $entity = fx::data($rel[1])->create();

        $entity_fields = $entity->getFields();

        $res['tpl'] = array();
        $res['labels'] = array();

        $rel_field = $entity_fields[$rel[2]];

        if ($rel_field) {
            $rel_prop_name = $rel_field->getPropertyName();
            $entity[$rel_prop_name] = $content;
        }

        $entity_form_fields = $entity->getFormFields();

        foreach ($entity_form_fields as $ef) {
            if ($ef['name'] == $rel[2]) {
                continue;
            }
            // do not show "is published" flag in this table
            if ($ef['name'] == 'is_published') {
                continue;
            }
            $res['tpl'] [] = $ef;
            $res['labels'] [] = $ef['label'];
        }
        $res['values'] = array();
        if (isset($content[$this['keyword']])) {
            if ($rel[0] === System\Finder::HAS_MANY) {
                $linkers = $content[$this['keyword']];
            } else {
                $linkers = $content[$this['keyword']]->linkers;
            }
            foreach ($linkers as $linker) {
                $linker_fields = $linker->getFormFields();
                $val_array = array('_index' => $linker['id']);
                $val_array['_meta'] = array(
                    'id' => $linker['id'],
                    'type' => $linker->getType(),
                    'type_id' => $linker->getComponent()->get('id')
                );
                foreach ($linker_fields as $lf) {
                    // skip the relation field
                    if ($lf['name'] == $rel[2]) {
                        continue;
                    }
                    // do not show "is published" flag in this table
                    if ($lf['name'] == 'is_published') {
                        continue;
                    }
                    // form field has "name" prop instead of "keyword"
                    $val_array [$lf['name']] = $lf['value'];
                }
                $res['values'] [] = $val_array;
            }
        }
        $res['type'] = 'set';
        return $res;
    }

    public function formatSettings()
    {
        $fields = array();
        $com = $this['component'];
        $com_variant_ids = $com->getChain()->getValues('id');

        $field_filters = array();
        $avail_coms = array();

        $linking_fields = fx::data('field')
            ->where('type', 'link')
            ->whereIsNull('parent_field_id')
            ->all();

        $linking_field_values = array();
        
        foreach ($linking_fields as $f) {
            $target_id = (int) $f['format']['target'];
            if (!in_array($target_id, $com_variant_ids)) {
                continue;
            }
            $field_com = $f['component'];
            $owner_variant_ids = $field_com->getAllVariants()->getValues('id');
            $avail_coms = array_merge($avail_coms, $owner_variant_ids);
            $linking_field_values []= array(
                'id' => $f['id'], 
                'name' => $f['component']['name'].' &rarr; '.$f['name'],
                'component_id' => $f['component_id'],
                'components' => $owner_variant_ids
            );
        }
        
        $avail_coms = array_flip(array_unique($avail_coms));

        $all_coms = fx::collection(fx::data('component')->getSelectValues());
        
        $com_values = $all_coms
            ->findRemove(
                function($e) use ($avail_coms) {
                    return !isset($avail_coms[$e[0]]);
                }
            )
            ->getValues();
            
        $fields[]= array(
            'name' => 'linking_component_id',
            'label' => 'Тип связанных объектов',
            'type' => 'livesearch',
            'values' => $com_values
        );

        $fields[]= array(
            'name' => 'linking_field_id',
            'label' => 'Ссылающееся поле',
            'type' => 'livesearch',
            //'type' => 'hidden',
            'parent' => array('format[linking_component_id]'),
            'values' => $linking_field_values,
            'values_filter' => 'format[linking_component_id] in this.components'
        );
        
        $all_fields = array();
        
        foreach ($com_values as $com_value) {
            $c_com = fx::getComponentById($com_value[0]);
            //$c_com_variants = $c_com->getChain()->getValues('id');
            
            $c_com_fields = $c_com->getAllFieldsWithChildren();
            foreach ($c_com_fields as $c_com_field) {
                $c_id = $c_com_field['id'];
                if (isset($all_fields[$c_id])) {
                    continue;
                }
                $all_fields[$c_id] = array(
                    'id' => $c_id,
                    'name' => $c_com_field['name'],
                    'component_id' => $c_com_field['component_id'],
                    'components' => $c_com_field['component']->getAllVariants()->getValues('id')
                );
            }
        }
        
        $fields []= array(
            'name' => 'render_type',
            'type' => 'livesearch',
            'label' => 'Способ отображения',
            'values' => array(
                array('table', 'Таблица значений'),
                array('livesearch', 'Быстрый поиск')
            )
        );
        
        $fields []= array(
            'name' => 'list_fields_type',
            'label' => 'Отображаемые поля',
            'parent' => 'format[render_type] == table',
            'type' => 'radio_facet',
            'values' => array(
                array('all','все редактируемые'),
                array('only_listed', 'только указанные'),
                array('not_listed', 'кроме указанных')
            ),
            'default' => 'all',
            'value' => 'all'
        );
        
        $fields []= array(
            'name' => 'list_fields',
            'type' => 'livesearch',
            'is_multiple' => true,
            'parent' => 'format[list_fields_type] != all',
            'values' => array_values($all_fields),
            'values_filter' => 'format[linking_component_id] in this.components'
        );

        return $fields;
        
        /*
        $fields = array();

        if (!$this['component_id']) {
            return $fields;
        }

        $com = fx::data('component', $this['component_id']);
        $chain = $com->getChain();
        $chain_ids = $chain->getValues('id');
        $link_fields = fx::data('field')
            ->where('type', 'link')
            ->where('component_id', 0, '!=')
            ->all();

        // select from the available fields-links
        $linking_field_values = array();

        // array of InputB with specification of the data type
        $res_datatypes = array();

        // array of InputB with specification of the field for many-many
        $res_many_many_fields = array();

        // array of InputB with specification of the type for many-many
        $res_many_many_types = array();

        foreach ($link_fields as $lf) {
            if (in_array($lf['format']['target'], $chain_ids)) {
                // the component that owns the current box-link
                $linking_field_owner_component = fx::data('component', $lf['component_id']);

                $linking_field_values[] = array(
                    $lf['id'],
                    $linking_field_owner_component['keyword'] . '.' . $lf['keyword']
                );

                // get the list of references component and all of its descendants
                $component_tree = fx::data('component')->getSelectValues($lf['component_id']);

                $res_datatypes[$lf['id']] = array();
                foreach ($component_tree as $com_variant) {
                    $linking_component = fx::data('component', $com_variant[0]);
                    $res_datatypes[$lf['id']] [] = array(
                        $com_variant[0],
                        $com_variant[1]
                    );

                    // For links many_many relations
                    // get the field-component links that point to other components
                    $linking_component_links = $linking_component
                            ->getAllFields()
                            ->find('type', 'link')
                            ->find('id', $lf['id'], '!=');

                    // exclude fields, connected to the parent
                    if ($lf['format']['is_parent']) {
                        $linking_component_links = $linking_component_links->find('keyword', 'parent_id', '!=');
                    }
                    if (count($linking_component_links) === 0) {
                        continue;
                    }
                    // key for many-many
                    $mmf_key = $lf['id'] . '_' . $com_variant[0];

                    $res_many_many_fields[$mmf_key] = array(array('', '--'));
                    foreach ($linking_component_links as $linking_component_link) {
                        // skip pseudo-components
                        // @todo needs a better workaround
                        if (in_array($linking_component_link['format']['target'], array('lang', 'site', 'infoblock'))) {
                            continue;
                        }
                        $res_many_many_fields[$mmf_key] [] = array(
                            $linking_component_link['id'],
                            $linking_component_link['keyword'],
                        );

                        $target_component = fx::data('component', $linking_component_link['format']['target']);
                        $end_tree = fx::data('component')->getSelectValues($target_component['id']);
                        $mmt_key = $mmf_key . '|' . $linking_component_link['id'];
                        $res_many_many_types[$mmt_key] = array();
                        foreach ($end_tree as $end_com) {
                            $end_component = fx::data('component', $end_com[0]);
                            $res_many_many_types[$mmt_key] [] = array(
                                $end_com[0],
                                $end_component['keyword']
                            );
                        }
                    }
                }
            }
        }

        $fields[] = array(
            'id'     => 'format[linking_field]',
            'name'   => 'format[linking_field]',
            'label'  => fx::alang('Linking field'),
            'type'   => 'select',
            'values' => $linking_field_values,
            'value'  => $this['format']['linking_field']
        );
        foreach ($res_datatypes as $rel_field_id => $linking_datatype) {
            $field_id = 'format[linking_field_' . $rel_field_id . '_datatype]';
            $fields[] = array(
                'id'     => $field_id,
                'name'   => $field_id,
                'type'   => 'select',
                'label'  => fx::alang('Linked datatype'),
                'parent' => array('format[linking_field]' => $rel_field_id),
                'values' => $linking_datatype,
                'value'  => $this['format']['linking_datatype']
            );
        }

        foreach ($res_many_many_fields as $res_mmf_key => $mm_fields) {
            list($check_field, $check_type) = explode("_", $res_mmf_key);
            $field_id = 'format[linking_mm_field_' . $res_mmf_key . ']';
            $fields[] = array(
                'id'     => $field_id,
                'name'   => $field_id,
                'type'   => 'select',
                'label'  => 'Many-many field',
                'parent' => array(
                    'format[linking_field_' . $check_field . '_datatype]' => $check_type,
                    'format[linking_field]' => $check_field
                ),
                'values' => $mm_fields,
                'value'  => $this['format']['mm_field']
            );
        }
        foreach ($res_many_many_types as $res_mmt_key => $mmt_fields) {
            list($check_mmf, $check_field) = explode("|", $res_mmt_key);
            $field_id = 'format[linking_mm_type_' . str_replace("|", "_", $res_mmt_key) . ']';
            $fields[] = array(
                'id'     => $field_id,
                'name'   => $field_id,
                'type'   => 'select',
                'label'  => 'Many-many datatype',
                'parent' => array(
                    'format[linking_mm_field_' . $check_mmf . ']' => $check_field
                ),
                'values' => $mmt_fields,
                'value'  => $this['format']['mm_datatype']
            );
        }
        $fields[] = array(
            'id'     => 'format[render_type]',
            'name'   => 'format[render_type]',
            'label'  => fx::alang('Render type', 'system'),
            'type'   => 'select',
            'values' => array(
                'livesearch' => fx::alang('Live search', 'system'),
                'table'      => fx::alang('Fields table', 'system')
            ),
            'value'  => $this['format']['render_type']
        );
        return $fields;
         * 
         */
    }

    public function setValue($value)
    {
        parent::setValue($value);
    }

    protected function beforeSave()
    {
        if ($this->isModified('format') || !$this['id']) {
            $format = $this['format'];
            $format['linking_datatype'] = $format['linking_component_id'];
            $format['linking_field'] = $format['linking_field_id'];
            /*
            $c_lf = $this['format']['linking_field'];
            $c_ldt = $this['format']['linking_field_' . $c_lf . '_datatype'];
            if ($c_ldt) {
                $format = array(
                    'render_type'   => $this['format']['render_type'],
                    'linking_field' => $c_lf
                );
                $format['linking_datatype'] = $c_ldt;
                $mm_field = $this['format']['linking_mm_field_' . $c_lf . '_' . $c_ldt];
                if ($mm_field) {
                    $format['mm_field'] = $mm_field;
                    $format['mm_datatype'] = $this['format']['linking_mm_type_' . $c_lf . '_' . $c_ldt . '_' . $mm_field];
                }
                $this['format'] = $format;
            }
             * 
             */
            $this['format'] = $format;
        }
        parent::beforeSave();
    }


    /*
     * Converts a value from a form to the collection
     * Seems, is confined only under many_many relations
     */
    public function getSavestring($content)
    {
        $rel = $this->getRelation();
        $is_mm = $rel[0] == System\Finder::MANY_MANY;
        if ($is_mm) {
            $res = $this->appendManyMany($content);
        } else {
            $res = $this->appendHasMany($content);
        }
        return $res;
    }

    /*
     * Process value of many-many relation field
     * such as post - tag_linker - tag
     */
    protected function appendManyMany($content)
    {
        // pull the previous value
        // to fill it
        $existing_items = $content->get($this['keyword']);
        $rel = $this->getRelation();
        // end type (for fields lot)
        $linked_data_type = $this->getEndDataType();
        // binding type, which directly references
        $linker_data_type = $rel[1];
        // the name of the property, the linker where to target
        $linker_prop_name = $rel[3];
        // value to be returned
        $new_value = new System\Collection();
        $new_value->linkers = new System\Collection();
        // Find the name for the field, for example "most part"
        // something strashnenko...
        $linker_com_name = $linker_data_type;
        $end_link_field_name = 
            fx::component($linker_com_name)
                ->getAllFields()
                ->findOne(
                    function ($i) use ( $linker_prop_name ) {
                        return isset($i['format']['prop_name']) && $i['format']['prop_name'] == $linker_prop_name;
                    }
                )
                ->get('keyword');
        $linked_infoblock_id = null;
        $linked_parent_id = null;
        foreach ($this->value as $item_props) {
            $linked_props = $item_props[$end_link_field_name];
            // if the linked entity doesn't yet exist
            // we get it as an array of values including 'title'
            $linker_item = null;

            if (is_array($linked_props)) {
                if (!$linked_infoblock_id && $content instanceof \Floxim\Main\Content\Entity) {
                    $linked_infoblock_id = $content->getLinkFieldInfoblock($this['id']);
                }
                $linked_props['type'] = $linked_data_type;
                if ($linked_infoblock_id) {
                    $linked_props['infoblock_id'] = $linked_infoblock_id;
                } else {
                    if (is_null($linked_parent_id) && $content['infoblock_id']) {
                        $our_infoblock = fx::data('infoblock', $content['infoblock_id']);
                        $linked_parent_id = $our_infoblock['page_id'];
                    }
                    if ($linked_parent_id) {
                        $linked_props['parent_id'] = $linked_parent_id;
                    }
                }
            } elseif (isset($existing_items->linkers)) {
                $linker_item = $existing_items->linkers->findOne($end_link_field_name, $linked_props);
            }
            if (!$linker_item) {
                $linker_item = fx::data($linker_data_type)->create();
            }
            $linker_item->setFieldValues(array($end_link_field_name => $linked_props), array($end_link_field_name));
            $new_value[] = $linker_item[$linker_prop_name];
            $new_value->linkers [] = $linker_item;
        }
        return $new_value;
    }

    /*
     * Process value of has-many relation field
     * such as news - comment
     */
    protected function appendHasMany($content)
    {
        
        $linked_type = $this->getRelatedComponent()->get('keyword');
        
        $new_value = fx::collection();
        foreach ($this->value as $item_id => $item_props) {
            
            $linked_item = null;
            if (is_numeric($item_id)) {
                $linked_item = fx::data($linked_type, $item_id);
            } else {
                $is_empty = true;
                foreach ($item_props as $item_prop_val) {
                    if (!empty($item_prop_val)) {
                        $is_empty = false;
                        break;
                    }
                }
                // if all props are empty, skip this row and do nothing
                if ($is_empty) {
                    continue;
                }
                
                $c_linked_finder = null;
                
                if (isset($item_props['type'])) {
                    $item_com = fx::component($item_props['type']);
                    if ($item_com && $item_com->isInstanceOfComponent($linked_type)) {
                        $c_linked_finder = fx::data($item_com['keyword']);
                    }
                }
                
                if (is_null($c_linked_finder)) {
                    $c_linked_finder = fx::data($linked_type);
                }
                
                $linked_item = $c_linked_finder->create();
                
                // @todo: need more accurate check
                $content_ib = fx::data('infoblock')->where('site_id', $content['site_id'])->getContentInfoblocks($linked_item['type']);
                if (count($content_ib) > 0) {
                    $linked_item['infoblock_id'] = $content_ib->first()->get('id');
                }
            }
            if ($linked_item) {
                $linked_item->setFieldValues($item_props);
                $new_value[] = $linked_item;
            }
        }
        return $new_value;
    }

    public function getEndDataType()
    {
        // the connection generated by the field
        $relation = $this->getRelation();
        if (isset($relation[4])) {
            return $relation[4];
        }
    }
    
    public function getTargetName()
    {
        $rel = $this->getRelation();
        switch ($rel[0]) {
            case System\Finder::HAS_MANY:
                $content_type = $rel[1];
                break;
            case System\Finder::MANY_MANY:
                $content_type = $rel[4];
                break;
        }
        return $content_type;
    }
    
    public function getTargetFinder($content)
    {
        $target_com = $this->getTargetName();
        $finder = fx::data($target_com);
        $method_name = 'getRelationFinder'. fx::util()->underscoreToCamel($this['keyword']);
        if (method_exists($content, $method_name)) {
            $finder = call_user_func(array($content, $method_name), $finder);
        }
        return $finder;
    }

    /*
     * Get the referenced component field
     */
    public function getRelatedComponent()
    {
        $content_type = $this->getTargetName();
        return fx::data('component', $content_type);
    }

    public function getRelation()
    {
        if (!$this['format']['linking_field']) {
            return false;
        }
        $direct_target_field = fx::data('field', $this['format']['linking_field']);
        $direct_target_component = fx::component($this['format']['linking_datatype']);

        $first_type = $direct_target_component['keyword'];

        if (!isset($this['format']['mm_field']) || !$this['format']['mm_field']) {
            $res_rel = array(
                System\Finder::HAS_MANY,
                $first_type,
                $direct_target_field['keyword']
            );
            return $res_rel;
        }

        $end_target_field = fx::data('field', $this['format']['mm_field']);
        $end_datatype = fx::component($this['format']['mm_datatype']);
        
        
        
        if (!$end_target_field || !$end_datatype) {
            return false;
        }

        $end_type = $end_datatype['keyword'];
        
        return array(
            System\Finder::MANY_MANY,
            $first_type,
            $direct_target_field['keyword'],
            $end_target_field->getPropertyName(),
            $end_type,
            $end_target_field['keyword']
        );
    }
    
    public function getPropertyName()
    {
        return $this['keyword'];
    }
}