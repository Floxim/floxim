<?php
namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\Component\Field;
use Floxim\Floxim\System\Fx as fx;

class Content extends Admin
{

    public function addEdit($input)
    {
        if (!isset($input['content_type'])) {
            $currentPage = fx::env('page');
            if (!$currentPage) {
                return false;
            }
            $input['content_type'] = $currentPage->getType();
            $input['content_id'] = $currentPage['id'];
        }
        $content_type = $input['content_type'];
        
        $linker = null;
        $linker_field = null;


        foreach (['placeholder_linker', 'placeholder_target'] as $complexProp) {
            if (isset($input[$complexProp]) && is_string($input[$complexProp])) {
                $input[$complexProp] = json_decode($input[$complexProp], true);
            }
        }

        $placeholderTarget = isset($input['placeholder_target']) ? $input['placeholder_target'] : null;

        // get the edited object
        if (isset($input['content_id']) && $input['content_id']) {
            $content = fx::data($content_type, $input['content_id']);
        } else {
            $content_type = $input['content_type'];
            if (isset($input['parent_id'])) {
                $parent_page = fx::data('floxim.main.content', $input['parent_id']);
                $site_id = $parent_page['site_id'];
            } else {
                $site_id = fx::env('site_id');
            }
            $infoblock_id = null;
            if (isset($input['infoblock_id'])) {
                $infoblock = fx::data('infoblock', $input['infoblock_id']);
                if ($infoblock) {
                    $infoblock_id = $infoblock['id'];
                }
            }
            $content = fx::data($content_type)->create(array(
                'parent_id'    => isset($input['parent_id']) ? $input['parent_id'] : null,
                'infoblock_id' => $infoblock_id,
                'site_id'      => $site_id
            ));
            if (isset($input['placeholder_linker']) && is_array($input['placeholder_linker'])) {
                $linker = fx::data($input['placeholder_linker']['type'])->create($input['placeholder_linker']);
                $linker_field = $input['placeholder_linker']['_link_field'];
            }
        }
        
        $res = array();
        $com_item_name = fx::getComponentByKeyword($content_type)->getItemName('add');
        if (isset($input['content_id']) && $input['content_id']) {
            $res['header'] = fx::alang('Editing ','system') 
                            . ' <span title="#' . $input['content_id'] . '">' . $com_item_name . '</span>';
        } else {
            $res['header'] = fx::alang('Adding new ', 'system') . ' ' . $com_item_name;
        }

        $fields = array(
            $this->ui->hidden('content_type', $content_type),
            $this->ui->hidden('parent_id', $content['parent_id']),
            $this->ui->hidden('entity', 'content'),
            $this->ui->hidden('action', 'add_edit'),
            $this->ui->hidden('fx_admin', true)
        );
        
        if ($linker) {
            $fields[]= $this->ui->hidden('placeholder_linker', $input['placeholder_linker']);
        }

        if ($placeholderTarget) {
            $fields []= $this->ui->hidden('placeholder_target', $placeholderTarget);
        }
        
        if (isset($input['preset_params'])) {
            $fields []= $this->ui->hidden('preset_params', $input['preset_params']);
        }

        $relation_field = null;

        if (isset($input['entity_values'])) {
            $entity_values = $input['entity_values'];
            if (is_string($entity_values)) {
                $entity_values = json_decode($entity_values, true);
            }
            $content->setFieldValues($entity_values, array_keys($entity_values));
            $fields []= $this->ui->hidden('entity_values', json_encode($entity_values));
            foreach ($entity_values as $entity_field_name => $entity_field_value) {
                $c_entity_field = $content->getField($entity_field_name);
                if ($c_entity_field instanceof \Floxim\Floxim\Field\FieldLink && $entity_field_value) {
                    $relation_field = $c_entity_field;
                }
            }
        }
        
        if (
            !isset($input['infoblock_id']) 
            && !$content['id']  
            && $content instanceof \Floxim\Main\Content\Entity
            && !$content['infoblock_id']
        ) {
            $avail_infoblocks = $content->getRelationFinderInfoblockId()->all();
            
            $avail_infoblocks = $content->filterAvailableInfoblocksByParent($avail_infoblocks, $content['parent_id']);
            
            if (count($avail_infoblocks) > 1) {
                $ib_field = $content->getFormField('infoblock_id');
                $ib_field['values'] = $avail_infoblocks->getValues(
                    function($ib) {
                        return array($ib['id'], $ib['name']);
                    },
                    false
                );
                $ib_field['value'] = $avail_infoblocks->first()->get('id');
                $fields []= $ib_field;
                $res['fields'] = $fields;
                $this->response->addFormButton(array('key' => 'save', 'label' => fx::alang('Continue')));
                return $res;
            } 
            if (count($avail_infoblocks) === 1) {
                $avail_infoblock_id = $avail_infoblocks->first()->get('id');
                $fields []= array(
                    'type' => 'hidden',
                    'name' => 'infoblock_id',
                    'value' => $avail_infoblock_id
                );
                $content['infoblock_id'] = $avail_infoblock_id;
            }
        }
        
        $fields []= $this->ui->hidden('data_sent', true);
        
        

        //$move_meta = null;
        if (isset($input['__move_field'])) {
            $move_variants = array('__move_before', '__move_after');
            $fields [] = $this->ui->hidden('__move_field', $input['__move_field']);
            foreach ($move_variants as $rel_prop) {
                if (isset($input[$rel_prop]) && $input[$rel_prop]) {
                    $fields [] = $this->ui->hidden($rel_prop, $input[$rel_prop]);
                    break;
                }
            }
        }

        if (isset($input['parent_form_data']) && isset($input['relation'])) {
            $parent_form_data = json_decode($input['parent_form_data'], true);
            $relation = json_decode($input['relation'], true);
            
            $related_entity_type = $parent_form_data['content_type'];
            $related_entity_id = isset($parent_form_data['content_id']) ? $parent_form_data['content_id'] : false;
            if ($related_entity_id) {
                $related_entity = fx::data($related_entity_type, $related_entity_id);
            } else {
                $related_entity = fx::data($related_entity_type)->create();
            }
            $related_entity->setFieldValues($parent_form_data['content'], array_keys($parent_form_data['content']));
            
            $relation_field = $content->getField($relation[2]);
            
            if ($relation_field && ($relation_prop_name = $relation_field->getFormat('prop_name'))) {
                $content[$relation_prop_name] = $related_entity;
            }
        }

        if (isset($input['content_id'])) {
            $fields [] = $this->ui->hidden('content_id', $input['content_id']);
        } else {
            $fields [] = $this->ui->hidden('infoblock_id', isset($input['infoblock_id']) ? $input['infoblock_id'] : null);
        }

        $this->response->addFields($fields);
        
        if ($content->isInstanceOf('floxim.main.content')) {
            $this->response->addFields( $content->getStructureFields(), '', 'content' );
        }
        
        $content_fields = fx::collection($content->getFormFields());

        $res['tabs'] = [];

        $content_fields->findRemove(function($f) use (&$res) {
            if (!isset($f['render_type']) || $f['render_type'] !== 'tab') {
                return false;
            }
            $res['tabs'][$f['keyword']] = [
                'label' => $f['label'],
                'key' => $f['keyword']
            ];
            return true;
        });

        if ($relation_field !== null) {
            $content_fields->apply(function(&$f) use ($relation_field) {
                if (isset($f['id']) && $f['id'] === $relation_field['keyword'] && isset($f['value']) && $f['value']) {
                    $f['type'] = 'hidden';
                    if (is_array($f['value']) && isset($f['value']['id'])) {
                        $f['value'] = $f['value']['id'];
                    }
                }
            });
        }

        if (count($res['tabs']) > 0) {
            $res['tabs'] = array_merge(
              [
                  'default_tab' => [
                      'label' => 'Основное',
                      'key' => 'default_tab'
                  ],
              ],
              $res['tabs']
            );
            $content_fields->apply(function (&$f) use ($res) {
                if (isset($f['group']) && isset($res['tabs'][$f['group']])) {
                    $f['tab'] = $f['group'];
                    unset($f['group']);
                } elseif (!isset($f['tab'])) {
                    $f['tab'] = 'default_tab';
                }
            });
        }

        $this->response->addFields($content_fields, '', 'content');
        
        $is_backoffice = isset($input['mode']) && $input['mode'] == 'backoffice';

        
        if ($is_backoffice) {
            $this->response->addFields(array(
                $this->ui->hidden('mode', 'backoffice'),
                $this->ui->hidden('reload_url', $input['reload_url'])
            ));
        }
        
        if (isset($input['data_sent']) && $input['data_sent']) {
            $res['is_new'] = !$content['id'];
            $set_res = $content->setFieldValues($input['content']);
            if (is_array($set_res) && isset($set_res['status']) && $set_res['status'] === 'error') {
                $res['status'] = 'error';
                $res['errors'] = $set_res['errors'];
            } else {
                if (isset($input['__move_field'])) {
                    $moved_entity = $linker ? $linker : $content;
                    foreach ($move_variants as $rel_prop) {
                        if (isset($input[$rel_prop])) {
                            $moved_entity[$rel_prop] = $input[$rel_prop];
                        }
                    }
                    $moved_entity['__move_field'] = $input['__move_field'];
                }
                try {
                    if (isset($input['mode']) && $input['mode'] === 'sync_fields') {
                        $synced = $content->syncFields();
                        fx::complete($synced);
                        return;
                    }
                    if (isset($content['infoblock_id']) && $content['infoblock_id']) {
                        $infoblock = fx::data('infoblock', $content['infoblock_id']);
                        if ($infoblock['is_preset']) {
                            if (isset($input['preset_params'])) {
                                $preset_params = json_decode($input['preset_params'], true);
                                $ib_visual = $infoblock->getVisual();
                                $ib_visual['area'] = $preset_params['area'];
                                if (isset($preset_params['next_visual_id'])) {
                                    $ib_visual->moveBefore($preset_params['next_visual_id']);
                                } else {
                                    $ib_visual->moveFirst();
                                }
                            }
                            $infoblock = $infoblock->createFromPreset();
                            $infoblock->save();
                            $res['real_infoblock_id'] = $infoblock['id'];
                            $content['infoblock_id'] = $infoblock['id'];
                        }
                    }
                    
                    $content->save();
                    
                    $res['saved_id'] = $content['id'];
                    if ($is_backoffice) {
                        $res['reload'] = str_replace("%d", $content['id'], $input['reload_url']);
                    }
                    if ($linker) {
                        $linker[$linker_field] = $content['id'];
                        $linker->save();
                    }
                    if ($placeholderTarget) {
                        $targetEntity = fx::data($placeholderTarget['type'], $placeholderTarget['id']);
                        if ($targetEntity) {
                            $targetEntity[$placeholderTarget['field']] = $content;
                            $targetEntity->save();
                        }
                    }
                    $res['saved_entity'] = $content->get();
                    $res['status'] = 'ok';
                }  catch (\Exception $e) {
                    $res['status'] = 'error';
                    if ($e instanceof \Floxim\Floxim\System\Exception\EntityValidation) {
                        $res['errors'] = $e->toResponse();
                    }
                }
            }
        } else {
            $res['resume'] = true;
        }
        $res['content_type_id'] = $content->getComponent()->get('id');
        $res['content_id'] = $content['id'];
        $this->response->addFormButton('save');
        fx::trigger(
            'content_form_ready', 
            [
                'response' => $res,
                'entity' => $content
            ]
        );
        return $res;
    }

    public function deleteManySave ($input) {
        fx::log('deltng', $input);
    }

    public function deleteSave($input)
    {
        if (!isset($input['content_type'])) {
            return;
        }
        $content_type = $input['content_type'];
        if (isset($input['ids'])) {
            $ids = is_string($input['ids']) ? explode(',', $input['ids']) : $input['ids'];
        } else {
            $id = isset($input['content_id']) ? $input['content_id'] : (isset($input['id']) ? $input['id'] : false);
            if (!$id) {
                return;
            }
            $ids = [$id];
        }
        if (count($ids) === 0) {
            return;
        }

        $items = fx::data($content_type)->where('id', $ids)->all();
        $content = count($items) === 1 ? $items[0] : null;
        if (count($items) < 1) {
            return;
        }
        $fields = array(
            array(
                'name'  => 'delete_confirm',
                'type'  => 'hidden',
                'value' => 1
            ),
            $this->ui->hidden('entity', 'content'),
            $this->ui->hidden('action', 'delete_save'),
            $this->ui->hidden('content_id', $content['id']),
            $this->ui->hidden('fx_admin', true),
            $this->ui->hidden('ids', implode(',', $ids))
        );
        if (isset($input['content_type'])) {
            $fields[] = $this->ui->hidden('content_type', $input['content_type']);
        }
        if (isset($input['page_id'])) {
            $fields[] = $this->ui->hidden('page_id', $input['page_id']);
        }
        /**
         * check children
         */
        $alert = '';
        
        $is_linker = $content &&  $content->isInstanceOf('floxim.main.linker');
        
        $delete_impossible = false;
        
        if ($is_linker) {
            $linked_entity = $content['content'];
            $linked_com = $linked_entity->getComponent();
            $alert = '<p>'.
                        fx::alang(
                            'Only link will be removed, not %s itself', 
                            null, 
                            $linked_com->getItemName('one')
                        );
            
            
            $linked_section = $linked_entity->getPath()->copy()->reverse()->findOne(function($i) use ($linked_entity) {
                return $i->isInstanceOf('floxim.nav.section') && $i['id'] !== $linked_entity['id'];
            });
            
            if ($linked_section) {
                $alert .= fx::alang(
                    ', it will be available in the %s section', 
                    null,
                    $linked_section['name']
                );
            }
            
            $alert .= '</p>';
        } elseif ($content && $content->isInstanceOf('floxim.main.content')) {
            $site = fx::env('site');
            if ($site && $site['index_page_id'] === $content['id']) {
                $delete_impossible = true;
                $alert = '<p>Нельзя удалить главную страницу!</p>';
            } else {
                $all_descendants = fx::data('floxim.main.content')->descendantsOf($content)->all()->group('type');
                $type_parts = array();
                foreach ($all_descendants as $descendants_type => $descendants) {
                    if ($descendants_type === 'floxim.main.linker') {
                        continue;
                    }
                    $descendants_com = fx::component($descendants_type);
                    $type_parts []= count($descendants).' '.
                                    fx::util()->getDeclensionByNumber($descendants_com['declension'], count($descendants));
                }
                if (count($type_parts) > 0) {
                    $com_name = fx::util()->ucfirst($content->getComponent()->getItemName('one'));
                    $alert = '<p>'.$com_name.' содержит данные, они также будут удалены:</p>';
                    $alert .= '<ul><li>'.join('</li><li>', $type_parts).'</li></ul>';
                }
            }
        }
        
        
        if ($alert) {
            $fields[] = array(
                'type' => 'html',
                'html' => '<div class="fx_delete_alert">'.$alert.'</div>'
            );
        }
        
        $this->response->addFields($fields);
        
        if ($delete_impossible) {
            $this->response->addFormButton('cancel');
            return;
        }
        $this->response->addFormButton(['key' => 'cancel', 'class' => 'cancel']);
        $this->response->addFormButton(array('key' => 'save', 'class' => 'delete', 'label' => fx::alang('Delete')));
        if (isset($input['delete_confirm']) && $input['delete_confirm']) {
            $response = array('status' => 'ok');
            $c_path = fx::env('path');
            if ($c_path && $content) {
                $content_in_path = $c_path->findOne('id', $content['id']);
                if ($content_in_path) {
                    $response['reload'] = $content_in_path['parent'] ? $content_in_path['parent']['url'] : '/';
                }
            }
            foreach ($items as $item) {
                $item->delete();
            }
            return $response;
        }
        $component = fx::data('component', $content_type);

        if ($is_linker) {
            $com_name = $linked_com->getItemName('add');
        } else {
            $com_name = $component->getItemName('add');
        }
        
        $header = $is_linker ? fx::alang('delete_from_list') : fx::alang("Delete");
        
        $header .= ' ' . mb_strtolower($com_name);
        
        if (($content_name = $content['name'])) {
            $content_name = strip_tags($content_name);
            $content_name = trim($content_name);
            $header .= ' &laquo;' . $content_name . '&raquo;';
        } elseif ($is_linker) {
            $header .= ' '.fx::alang('from this list');
        }
        if (count($ids) > 1) {
            $header .= ' ('.count($ids).' шт.)';
        }
        $header .= "?";
        $res = array('header' => $header);
        return $res;
    }


    
    /*
     * Move content among neighbors inside one parent and one InfoBlock
     * Input should be content_type and content_id
     * If there next_id - sets before him
     * If there are no raises in the end
     */
    public function move($input)
    {
        $content_type = $input['content_type'];
        $content = fx::data($content_type)->where('id', $input['content_id'])->one();
        
        if (!$content) {
            return;
        }

        $move_field = isset($input['field']) ? $input['field'] : 'priority';
        $content['__move_field'] = $move_field;
        
        if (isset($input['next_id']) && $input['next_id']) {
            $content['__move_before'] = $input['next_id'];
        } elseif (isset($input['prev_id']) && $input['prev_id']) {
            $content['__move_after'] = $input['prev_id'];
        } {
            $last_item = $content
                ->getFinder()
                ->whereSamePriorityGroup($content)
                ->order($move_field, 'desc')
                ->one();
            if (!$last_item) {
                return;
            }
            $content['__move_after'] = $last_item['id'];
        }
        
        $content->save();
    }

    /**
     * List all content of specified type
     *
     * @param type $input
     */
    public function all($input)
    {
        $content_type = $input['content_type'];
        $list = array(
            'type'   => 'list',
            'values' => array(),
            'labels' => array('id' => 'ID'),
            'entity' => 'content',
            'confirm_delete' => false,
            'multiselect' => true
        );
        
        if ($content_type === 'content') {
            $list['labels']['type'] = 'Type';
        }

        $com = fx::getComponentByKeyword($content_type);
        
        $fields = $com->getAllFields();
        
        $ib_field = $fields->findOne('keyword', 'infoblock_id');
        if ($ib_field) {
            $list['labels']['infoblock'] = $ib_field['name'];
        }

        $fields->findRemove(function ($f) {
            if ($f['keyword'] === 'parent_id' || $f['keyword'] === 'type' || $f['keyword'] === 'site_id') {
                return false;
            }
            if ($f['type'] === 'group') {
                return true;
            }
            return $f['is_editable'] == 0;
        });

        foreach ($fields as $f) {
            $list['labels'][$f['keyword']] = $f['name'];
        }

        $finder = fx::content($content_type);
        
        
        $pager = $finder->createPager(array(
            'url_template' => $input['url_template'],
            'current_page' => $input['page'],
            'items_per_page' => 100
        ));
        
        $items = $finder->all();
        
        $list['pager'] = $pager->getData();
        
        $ib_ids = $items->getValues('infoblock_id');
        $infoblocks = fx::data('infoblock', $ib_ids)->indexUnique('id');

        foreach ($items as $item) {
            $r = array('id' => $item['id']);
            $r['type'] = $item['type'];
            $r['infoblock'] = '-';
            if ($item['infoblock_id']) {
                $c_ib = $infoblocks->findOne('id', $item['infoblock_id']);
                if ($c_ib) {
                    $ib_url = $c_ib->getExampleUrl();
                    $r['infoblock'] = $ib_url ? 
                                        '<a href="'.$ib_url.'" target="_blank">'.$c_ib['name'].'</a>' :
                                        $c_ib['name'];
                }
            }
            foreach ($fields as $f) {
                $val = $item[$f['keyword']];
                switch ($f['type']) {
                    case 'link':
                        if ($val) {
                            $linked = fx::data($f->getRelatedType(), $val);
                            $val = $linked['name'];
                            if ($linked['url']) {
                                $val .= ' <a target="_blank" style="text-decoration:none;" href="'.$linked['url'].'">&rarr;</a>';
                            }
                        }
                        break;
                    case 'string':
                    case 'text':
                        $val = strip_tags($val);
                        $val = mb_substr($val, 0, 150);
                        break;
                    case 'image':
                        if ($val) {
                            $val = fx::image($val, 'max-width:100px,max-height:50px');
                            $val = '<img src="' . $val . '" alt="" />';
                        }
                        break;
                    case 'multilink':
                        //$val = fx::alang('%d items', 'system', count($val));
                        $val = count($val) ? count($val) . '&nbsp;шт.' : '&mdash;';
                        break;
                }

                $r[$f['keyword']] = $val;
            }
            $list['values'][] = $r;
        }

        $this->response->addButtons(array(
            array(
                'key' => "delete",
                'content_type' => $content_type
            )
        ));
        return array('fields' => array('list' => $list));
    }
}