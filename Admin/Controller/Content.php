<?php
namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\Component\Field;
use Floxim\Floxim\System\Fx as fx;

class Content extends Admin
{

    public function addEdit($input)
    {
        if (!isset($input['content_type'])) {
            return false;
        }
        $content_type = $input['content_type'];
        
        $linker = null;
        $linker_field = null;
        
        if (is_string($input['placeholder_linker'])) {
            $input['placeholder_linker'] = unserialize($input['placeholder_linker']);
        }
        
        // get the edited object
        if (isset($input['content_id']) && $input['content_id']) {
            $content = fx::data($content_type, $input['content_id']);
        } else {
            $content_type = $input['content_type'];
            $parent_page = fx::data('page', $input['parent_id']);
            $content = fx::data($content_type)->create(array(
                'parent_id'    => $input['parent_id'],
                'infoblock_id' => $input['infoblock_id'],
                'site_id'      => $parent_page['site_id']
            ));
            if (isset($input['placeholder_linker']) && is_array($input['placeholder_linker'])) {
                $linker = fx::data($input['placeholder_linker']['type'])->create($input['placeholder_linker']);
                $linker_field = $input['placeholder_linker']['_link_field'];
            }
        }

        $fields = array(
            $this->ui->hidden('content_type', $content_type),
            $this->ui->hidden('parent_id', $content['parent_id']),
            $this->ui->hidden('entity', 'content'),
            $this->ui->hidden('action', 'add_edit'),
            $this->ui->hidden('data_sent', true),
            $this->ui->hidden('fx_admin', true)
        );
        
        if ($linker) {
            $fields[]= $this->ui->hidden('placeholder_linker', serialize($input['placeholder_linker']));
        }
        
        

        $move_meta = null;
        $move_variants = array('__move_before', '__move_after');
        foreach ($move_variants as $rel_prop) {
            if (isset($input[$rel_prop]) && $input[$rel_prop]) {
                $rel_item = fx::content($input[$rel_prop]);
                if ($rel_item) {
                    $fields [] = $this->ui->hidden($rel_prop, $input[$rel_prop]);
                    $move_meta = array(
                        'item' => $rel_item,
                        'type' => preg_replace("~^__move_~", '', $rel_prop)
                    );
                }
                break;
            }
        }
        
        if (isset($input['entity_values'])) {
            $content->setFieldValues($input['entity_values'], array_keys($input['entity_values']));
        }

        if (isset($input['content_id'])) {
            $fields [] = $this->ui->hidden('content_id', $input['content_id']);
        } else {
            $fields [] = $this->ui->hidden('infoblock_id', $input['infoblock_id']);
        }

        $this->response->addFields($fields);
        if ($content->isInstanceOf('floxim.main.content')) {
            $this->response->addFields( $content->getStructureFields(), '', 'content' );
        }
        
        $content_fields = fx::collection($content->getFormFields());
        
        $content_fields->apply(function (&$f) {
            unset($f['tab']);
        });
        $this->response->addFields($content_fields, '', 'content');
        
        $is_backoffice = isset($input['mode']) && $input['mode'] == 'backoffice';

        
        if ($is_backoffice) {
            $this->response->addFields(array(
                $this->ui->hidden('mode', 'backoffice'),
                $this->ui->hidden('reload_url', $input['reload_url'])
            ));
        }

        $res = array('status' => 'ok');

        if (isset($input['data_sent']) && $input['data_sent']) {
            $res['is_new'] = !$content['id'];
            $set_res = $content->setFieldValues($input['content']);
            if (is_array($set_res) && isset($set_res['status']) && $set_res['status'] === 'error') {
                $res['status'] = 'error';
                $res['errors'] = $set_res['errors'];
            } else {
                foreach ($move_variants as $rel_prop) {
                    if (isset($input[$rel_prop])) {
                        $moved_entity = $linker ? $linker : $content;
                        $moved_entity[$rel_prop] = $input[$rel_prop];
                    }
                }
                try {
                    $content->save();
                    $res['saved_id'] = $content['id'];
                    if ($is_backoffice) {
                        $res['reload'] = str_replace("%d", $content['id'], $input['reload_url']);
                    }
                    if ($linker) {
                        $linker[$linker_field] = $content['id'];
                        $linker->save();
                    }
                }  catch (\Exception $e) {
                    $res['status'] = 'error';
                    if ($e instanceof \Floxim\Floxim\System\Exception\EntityValidation) {
                        $res['errors'] = $e->toResponse();
                    }
                }
            }
        }
        
        $com_item_name = fx::data('component', $content_type)->getItemName('add');

        if (isset($input['content_id']) && $input['content_id']) {
            $res['header'] = fx::alang('Editing ',
                    'system') . ' <span title="#' . $input['content_id'] . '">' . $com_item_name . '</span>';
        } else {
            $res['header'] = fx::alang('Adding new ', 'system') . ' ' . $com_item_name;
            if ($move_meta) {
                //$res['header'] .= ' <span class="fx_header_notice">' . fx::alang($move_meta['type']) . ' ' . $move_meta['item']['name'] . '</span>';
            }
        }
        //$res['view'] = 'cols';
        $this->response->addFormButton('save');
        return $res;
    }

    public function deleteSave($input)
    {
        if (!isset($input['content_type'])) {
            return;
        }
        $content_type = $input['content_type'];
        $id = isset($input['content_id']) ? $input['content_id'] : (isset($input['id']) ? $input['id'] : false);
        if (!$id) {
            return;
        }
        $content = fx::data($content_type, $id);
        if (!$content) {
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
            $this->ui->hidden('fx_admin', true)
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
        
        $is_linker = $content->isInstanceOf('floxim.main.linker');
        
        if ($is_linker) {
            $linked_entity = $content['content'];
            $linked_com = $linked_entity->getComponent();
            $alert = '<p>'.
                        fx::alang(
                            'Only link will be removed, not %s itself', 
                            null, 
                            $linked_com->getItemName('one')
                        );
            
            $linked_section = $linked_entity->getPath()->copy()->reverse()->findOne(function($i) {
                return $i->isInstanceOf('floxim.nav.section');
            });
            
            if ($linked_section) {
                $alert .= fx::alang(
                    ', it will be available in the %s section', 
                    null,
                    $linked_section['name']
                );
            }
            
            $alert .= '</p>';
            //fx::log($linked_entity->getPath());
        } elseif ($content->isInstanceOf('floxim.main.content')) {
            $all_descendants = fx::data('content')->descendantsOf($content)->all()->group('type');
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
        
        
        if ($alert) {
            $fields[] = array(
                'type' => 'html',
                'html' => '<div class="fx_delete_alert">'.$alert.'</div>'
            );
        }

        $this->response->addFields($fields);
        $this->response->addFormButton(array('key' => 'save', 'class' => 'delete', 'label' => fx::alang('Delete')));
        if (isset($input['delete_confirm']) && $input['delete_confirm']) {
            $response = array('status' => 'ok');
            $c_page = fx::env('page');
            if ($c_page) {
                $c_path = $c_page->getPath();
                $content_in_path = $c_path->findOne('id', $content['id']);
                if ($content_in_path) {
                    $response['reload'] = $content_in_path['parent'] ? $content_in_path['parent']['url'] : '/';
                }
            }
            $content->delete();
            return $response;
        }
        $component = fx::data('component', $content_type);

        if ($is_linker) {
            $com_name = $linked_com->getItemName();
        } else {
            $com_name = $component->getItemName();
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
        $next_id = isset($input['next_id']) ? $input['next_id'] : false;

        $neighbours = fx::data('content')
                        ->where('parent_id', $content['parent_id'])
                        ->where('infoblock_id', $content['infoblock_id'])
                        ->where('id', $content['id'], '!=')
                        ->order('priority')
                        ->all();
        $nn = $neighbours->find('id', $next_id);

        $c_priority = 1;
        $next_found = false;
        foreach ($neighbours as $n) {
            if ($n['id'] == $next_id) {
                $content['priority'] = $c_priority;
                $content->save();
                $c_priority++;
                $next_found = true;
            }
            $n['priority'] = $c_priority;
            $n->save();
            $c_priority++;
        }
        if (!$next_found) {
            $content['priority'] = $c_priority;
            $content->save();
        }
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
            'entity' => 'content'
        );
        
        if ($content_type === 'content') {
            $list['labels']['type'] = 'Type';
        }

        $com = fx::data('component', $content_type);
        
        $fields = $com->getAllFields();
        
        $ib_field = $fields->findOne('keyword', 'infoblock_id');
        if ($ib_field) {
            $list['labels']['infoblock'] = $ib_field['name'];
        }

        $fields->findRemove(function ($f) {
            return $f['type_of_edit'] == Field\Entity::EDIT_NONE;
        });

        foreach ($fields as $f) {
            $list['labels'][$f['keyword']] = $f['name'];
        }

        $finder = fx::content($content_type);
        
        
        $pager = $finder->createPager(array(
            'url_template' => $input['url_template'],
            'current_page' => $input['page']
        ));
        
        $items = $finder->all();
        
        $list['pager'] = $pager->getData();
        
        $ib_ids = $items->getValues('infoblock_id');
        $infoblocks = fx::data('infoblock', $ib_ids)->indexUnique('id');

        foreach ($items as $item) {
            $r = array('id' => $item['id']);
            $r['type'] = $item['type'];
            $c_ib = $infoblocks->findOne('id', $item['infoblock_id']);
            $r['infoblock'] = $c_ib ? $c_ib['name'] : '-';
            foreach ($fields as $f) {
                $val = $item[$f['keyword']];
                switch ($f['type']) {
                    case Field\Entity::FIELD_LINK:
                        if ($val) {
                            $linked = fx::data($f->getRelatedType(), $val);
                            $val = $linked['name'];
                        }
                        break;
                    case Field\Entity::FIELD_STRING:
                    case Field\Entity::FIELD_TEXT:
                        $val = strip_tags($val);
                        $val = mb_substr($val, 0, 150);
                        break;
                    case Field\Entity::FIELD_IMAGE:
                        $val = fx::image($val, 'max-width:100px,max-height:50px');
                        $val = '<img src="' . $val . '" alt="" />';
                        break;
                    case Field\Entity::FIELD_MULTILINK:
                        $val = fx::alang('%d items', 'system', count($val));
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