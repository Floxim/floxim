<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class FieldMultilink extends \Floxim\Floxim\Component\Field\Entity
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
                $m2m_field = $this->getM2MField();
                $res = array_merge(
                    $res,
                    [
                        'type' => 'livesearch',
                        'allow_new' => true,
                        'is_multiple' => true,
                        'params' => [
                            'content_type' => $m2m_field->getTargetName(),
                            'relation_field_id' => $this['id']
                        ],
                        'value' => $content[$this['keyword']]->find($m2m_field['keyword'])->getValues($m2m_field['keyword'])
                    ]
                );
                break;
            case 'table':
                $res = $this->getJsFieldTable($content, $res);
                break;
        }
        return $res;
    }

    public function getM2MField()
    {
        $f = $this['format'];
        $prop = 'livesearch_m2m_field';
        if (!isset($f[$prop]) || !$f[$prop] || $f['render_type'] !== 'livesearch') {
            return;
        }
        $m2m_field = fx::data('field', $f[$prop]);
        return $m2m_field;
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
        
        if (!$com) {
            fx::log($content, $rel, $this);
            return $res;
        }
        
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
        
        
        $ckw = $this['keyword'];
        
        $all_fields = $all_fields->find(
            function($f) use ($ckw) {
                if ($f['keyword'] === $ckw) {
                    return false;
                }
                if (!$f['is_editable']) {
                    return false;
                }
                return true;
            }
        );
        
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
        
        $field_groups = $all_fields->find('type', 'group');
        
        $all_fields = $all_fields->find(function($f) use ($field_groups) {
            return true;
        });
        
        $fields_by_com = array();

        $group_field_keyword = null;
        if (($group_by = $this->getFormat('group_by'))) {
            $group_field = fx::data('field', $group_by);
            if ($group_field && $group_field instanceof FieldLink) {
                $group_field_keyword = $group_field['keyword'];
                $groups_finder = $group_field->getTargetFinder($content);
                $group_values = $groups_finder->all();
                $res['group_by'] = [
                    'field' => $group_field['keyword'],
                    'type' => $groups_finder->getType(),
                    'values' => $group_values->getValues(function($item) {
                        return [
                            $item['id'],
                            $item['name']
                        ];
                    }),
                    'allow_new' => $this->getFormat('group_by_allow_new')
                ];
            }
        }
        
        
        foreach ($all_fields as $field) {
            if ($field['keyword'] == $rel[2]) {
                continue;
            }
            if ($field['keyword'] === $group_field_keyword) {
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

                if ($group_field_keyword) {
                    $val_array['_meta']['group_by_value'] = $linker[$group_field_keyword];
                }

                $linker_fields = $linker->getFormFields();

                foreach ($all_fields as $field) {
                    $field_keyword = $field['keyword'];
                    if ($field_keyword === $group_field_keyword) {
                        continue;
                    }
                    if ($field_keyword === 'type') {
                        $val_array[$field_keyword] = $linker->getComponent()->getItemName();
                        continue;
                    }
                    $linker_field = $linker_fields->findOne('name', $field_keyword);
                    if ($linker_field) {
                        $val_array[$field_keyword] = isset($linker_field['value']) ? $linker_field['value'] : null;
                    } else {
                        $val_array[$field_keyword] = $linker[$field_keyword];
                    }
                }
                $res['values'] [] = $val_array;
            }
        }

        return $res;
    }

    public function generateValues ($items) {

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

        $m2m_fields = [];
        
        foreach ($com_values as $com_value) {
            $c_com = fx::getComponentById($com_value[0]);
            $c_com_fields = $c_com->getAllFieldsWithChildren();

            $c_com_fields->apply(function($f)  use (&$m2m_fields) {
                if ($f instanceof FieldLink && !in_array($f, $m2m_fields)) {
                    $m2m_fields []= $f;
                }
            });
            foreach ($c_com_fields as $c_com_field) {
                $c_id = $c_com_field['id'];
                if (isset($all_fields[$c_id])) {
                    continue;
                }
                $all_fields[$c_id] = array(
                    'id' => $c_id,
                    'name' => $c_com_field['name'],
                    'component_id' => $c_com_field['component_id'],
                    'components' => $c_com_field['component']->getAllVariants()->getValues('id'),
                    'type' => $c_com_field['type']
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
            'parent' => 'format[render_type] != livesearch && format[list_fields_type] != all',
            'values' => array_values($all_fields),
            'values_filter' => 'format[linking_component_id] in this.components'
        );

        $avail_link_fields = array_filter(
            $all_fields,
            function  ($f) {
                return $f['type'] === 'link';
            }
        );
        $avail_link_components = [];
        foreach ($avail_link_fields as $alf) {
            $avail_link_components = array_merge($avail_link_components, $alf['components']);
        }
        $avail_link_components = array_unique($avail_link_components);

        $fields []= [
            'name' => 'group_by',
            'type' => 'livesearch',
            'label' => 'Группировать',
            'values' => array_values(
                array_merge(
                    [
                        [
                            'id' => '',
                            'name' => '- нет -',
                            'components' => $avail_link_components
                        ]
                    ],
                    $avail_link_fields
                )
            ),
            'values_filter' => 'format[linking_component_id] in this.components'
        ];

        $fields []= [
            'name' => 'group_by_allow_new',
            'type' => 'checkbox',
            'label' => 'Можно создавать новые группы?',
            'parent' => 'format[group_by]'
        ];

        $m2m_vals = [];
        foreach ($m2m_fields as $m2m_field) {
            $m2m_field_com = $m2m_field['component'];
            $m2m_field_id = $m2m_field['id'];
            $m2m_vals [$m2m_field_id]= [
                'id' => $m2m_field_id,
                'name' => $m2m_field_com['name'] . ' - '. $m2m_field['name'],
                'component_id' => $m2m_field['component_id'],
                'components' => $m2m_field_com->getAllVariants()->getValues('id')
            ];
        }
        $fields []= [
            'name' => 'livesearch_m2m_field',
            'type' => 'livesearch',
            'label' => 'Что искать?',
            'values' => array_values($m2m_vals),
            'parent' => 'format[render_type] == livesearch',
            'values_filter' => 'format[linking_component_id] in this.components && format[linking_field_id] != this.id'
        ];
        $m2m_field = $this->getM2MField();
        if ($m2m_field) {
            $m2m_field_com_keyword = $m2m_field->getTargetName();
            $cond_field = array(
                'name' => 'livesearch_m2m_cond',
                'type' => 'condition',
                'fields' => array(
                    fx::component($m2m_field_com_keyword)->getFieldForFilter('entity'),
                ),
                'types' => fx::data('component')->getTypesHierarchy(),
                // 'value' => $value,
                'label' => 'Условия',
                'parent' => 'format[render_type] == livesearch'
                // 'pageable' => $pageable->getValues('keyword')
            );
            $fields[] = $cond_field;
        }

        return $fields;
    }

    public function setValue($value)
    {
        //fx::log('sval', $value, $this['keyword'], debug_backtrace());
        parent::setValue($value);
    }

    protected function beforeSave()
    {
        if ($this->isModified('format') || !$this['id']) {
            $format = $this['format'];
            $format['linking_datatype'] = $format['linking_component_id'];
            $format['linking_field'] = $format['linking_field_id'];
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
        $has_mm_field = $this->getM2MField();
        if ($is_mm) {
            $res = $this->appendManyMany($content);
        } elseif ($has_mm_field) {
            $res = $this->appendWithM2MField($content);
        } else {
            $res = $this->appendHasMany($content);
        }
        return $res;
    }

    protected function appendWithM2MField($content)
    {
        $m2mf = $this->getM2MField();
        $old_val = $content[$this['keyword']];
        $new_raw_vals = $this->value;
        $new_val = fx::collection();
        $target = $this->getTargetName();
        foreach ($new_raw_vals as $item_id) {
            $linker = $old_val->findOne($m2mf['keyword'], $item_id);
            if (!$linker) {
                $linker = fx::data($target)->create([
                    $m2mf['keyword'] => $item_id
                ]);
            }
            $new_val[]= $linker;
        }
        return $new_val;
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
                foreach ($item_props as $item_prop_keyword => $item_prop_val) {
                    if ($item_prop_keyword !== 'type' && !empty($item_prop_val)) {
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
                
                /*
                // @todo: need more accurate check
                $content_ib = fx::data('infoblock')->where('site_id', $content['site_id'])->getContentInfoblocks($linked_item['type']);
                if (count($content_ib) > 0) {
                    $linked_item['infoblock_id'] = $content_ib->first()->get('id');
                }
                 * 
                 */
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
    
    public function fakeValue($entity = null)
    {
        $target_finder = $this->getTargetFinder($entity);
        $fake_level = $entity->getPayload('fake_level');
        $res = fx::collection();
        foreach (range(0, rand(0, 2)) as $n) {
            $item = $target_finder->fake([], $fake_level + 1);
            $res []= $item;
        }
        return $res;
    }
}