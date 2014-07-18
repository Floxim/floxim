<?php

class fx_controller_admin_content extends fx_controller_admin {

    public function add_edit($input) {
        // get the edited object
        if ($input['content_id']) {
            $content = fx::data('content', $input['content_id']);
            $content_type = $content['type'];
        } else {
            $content_type = $input['content_type'];
            $parent_page = fx::data('content_page', $input['parent_id']);
            $content = fx::data('content_'.$content_type)->create(array(
                'parent_id' => $input['parent_id'],
                'infoblock_id' => $input['infoblock_id'],
                'checked' => 1,
                'site_id' => $parent_page['site_id']
            ));
        }
        
        $fields = array(
            $this->ui->hidden('content_type',$content_type),
            $this->ui->hidden('parent_id', $content['parent_id']),
            $this->ui->hidden('essence', 'content'),
            $this->ui->hidden('action', 'add_edit'),
            $this->ui->hidden('data_sent', true),
            $this->ui->hidden('fx_admin', true)
        );
        
        $move_meta = null;
        $move_variants = array('__move_before', '__move_after');
        foreach ($move_variants as $rel_prop) {
            if (isset($input[$rel_prop])) {
                $rel_item = fx::content($input[$rel_prop]);
                if ($rel_item) {
                    $fields []= $this->ui->hidden($rel_prop, $input[$rel_prop]);
                    $move_meta = array(
                        'item' => $rel_item,
                        'type' => preg_replace("~^__move_~", '', $rel_prop)
                    );
                }
                break;
            }
        }

        if (isset($input['content_id'])) {
            $fields []= $this->ui->hidden('content_id', $input['content_id']);
        } else {
            $fields []= $this->ui->hidden('infoblock_id', $input['infoblock_id']);
        }
        
        $this->response->add_fields($fields);
        $content_fields = fx::collection($content->get_form_fields());
        
        $is_backoffice = $input['mode'] == 'backoffice';
		
        if (!$is_backoffice){ 
            $tabbed = $content_fields->group('tab');
            foreach ($tabbed as $tab => $tab_fields) {
                $this->response->add_tab($tab, $tab);
                $this->response->add_fields($tab_fields, $tab, 'content');
            }
        } else {
            $content_fields->apply(function(&$f) {
                unset($f['tab']);
            });
            $this->response->add_fields($content_fields, '', 'content');
            $this->response->add_fields(array(
                $this->ui->hidden('mode', 'backoffice'),
                $this->ui->hidden('reload_url', $input['reload_url'])
            ));
        }
        
        $res = array('status' => 'ok');
        
        if ($input['data_sent']) {
            $res['is_new'] = !$content['id'];
            $content->set_field_values($input['content']);
            foreach ($move_variants as $rel_prop) {
                if (isset($input[$rel_prop])) {
                    $content[$rel_prop] = $input[$rel_prop];
                }
            }
            $content->save();
            $res['saved_id'] = $content['id'];
            if ($is_backoffice) {
                $res['reload'] = str_replace("%d", $content['id'], $input['reload_url']);
            }
        }
        $com_item_name = fx::data('component', $content_type)->get('item_name');
        
        if ($input['content_id']) {
            $res['header'] = fx::alang('Editing ', 'system') . 
                    ' <span title="#'.$input['content_id'].'">'.$com_item_name.'</span>';
        } else {
            $res['header'] = fx::alang('Adding new ', 'system'). ' '.$com_item_name;
            if ($move_meta) {
                $res['header'] .= ' <span class="fx_header_notice">'.
                                $move_meta['type'].' '.$move_meta['item']['name'].
                                '</span>';
            }
        }
        $res['view'] = 'cols';
        $this->response->add_form_button('save');
        return $res;
    }

    public function checked_save($input) {
        
        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            if (preg_match("/(\d+)-(\d+)/", $id, $match)) {
                $class_id = $match[1];
                $content_id = $match[2];
            }

            $content = fx::data('content')->get_by_id($class_id, $content_id);
            $content->checked();
        }

        $result['status'] = 'ok';
        return $result;
    }

    public function on_save($input) {
        return $this->checked_save($input);
    }

    public function unchecked_save($input) {
        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $id) {
            if (preg_match("/(\d+)-(\d+)/", $id, $match)) {
                $class_id = $match[1];
                $content_id = $match[2];
            }
            $content = fx::data('content')->get_by_id($class_id, $content_id);
            $content->unchecked();
        }
        $result['status'] = 'ok';
        return $result;
    }

    public function off_save($input) {
        return $this->unchecked_save($input);
    }

    public function delete_save($input) {
        if (!isset($input['content_type']) || !isset($input['content_id'])) {
            if (!isset($input['id']) || !is_numeric($input['id'])) {
                return;
            }
            $content = fx::data('content', $input['id']);
        } else {
            $content = fx::data('content_'.$input['content_type'], $input['content_id']);
        }
        if (!$content) {
            return;
        }
        $current_page_path = null;
        if ($input['page_id']) {
            $current_page_path = fx::data('content_page' , $input['page_id'])->get_path()->get_values('id');
        }
        $response = array('status'=>'ok');
        if ($content->is_instanceof('page') && is_array($current_page_path) && in_array($content['id'], $current_page_path) ) {
            if ($content['parent_id'] == 0){
                $response['reload'] = '/';
            } else {
                $parent_page = fx::data('content_page', $content['parent_id']);
                $response['reload'] = $parent_page['url'];
            }
        }
        $content->delete();
        return $response;
    }
    
    public function livesearch($input) {
        if (!isset($input['content_type'])) {
            return;
        }
        $content_type = $input['content_type'];
        $finder = fx::data($content_type);
        if (preg_match("~^content_~", $content_type) && $content_type !== 'content_user'){
            $finder->where('site_id', fx::env('site')->get('id'));
        }
        if (isset($input['skip_ids']) && is_array($input['skip_ids'])) {
            $finder->where('id', $input['skip_ids'], 'NOT IN');
        }
        if (isset($input['ids'])) {
            $finder->where('id', $input['ids']);
        }
        if (isset($input['conditions'])) {
            foreach ($input['conditions'] as $cond_field => $cond_val) {
                if (is_array($cond_val)) {
                    $finder->where($cond_field, $cond_val[0], $cond_val[1]);
                } else {
                    $finder->where($cond_field, $cond_val);
                }
            }
        }
        $res = $finder->livesearch($_POST['term'],(isset($_POST['limit']) && $_POST['limit']) ? $_POST['limit'] : 20);
        fx::env()->set('complete_ok', true);
        echo json_encode($res);
        die();
    }
    
    /*
     * Move content among neighbors inside one parent and one InfoBlock
     * Input should be content_type and content_id
     * If there next_id - sets before him
     * If there are no raises in the end
     */
    public function move($input) {
        $content_type = 'content_'.$input['content_type'];
        $content = fx::data($content_type)->where('id', $input['content_id'])->one();
        $next_id = isset($input['next_id']) ? $input['next_id'] : false;
        
        $neighbours = fx::data($content_type)->
                        where('parent_id', $content['parent_id'])->
                        where('infoblock_id', $content['infoblock_id'])->
                        where('id', $content['id'], '!=')->
                        order('priority')->all();
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
     * @param type $input
     */
    public function all($input) {
        $content_type = $input['content_type'];
        $list = array(
            'type' => 'list',
            'values' => array(),
            'labels' => array('id' => 'ID'),
            'essence' => 'content'
        );
        
        if ($content_type === 'content') {
            $list['labels']['type'] = 'Type';
        }
        
        $com = fx::data('component', $content_type);
        
        $fields = $com->all_fields();
        
        $fields->find_remove(function($f) {
            return $f['type_of_edit'] == fx_field::EDIT_NONE;
        });
        
        foreach ($fields as $f) {
            $list['labels'][$f['keyword']] = $f['name'];
        }
        
        $finder = fx::content($content_type);
        
        $items = $finder->all();
        
        foreach ($items as $item) {
            $r = array('id' => $item['id']);
            $r['type'] = $item['type'];
            foreach ($fields as $f) {
                $val = $item[$f['keyword']];
                switch ($f['type']) {
                    case fx_field::FIELD_LINK:
                        if ($val) {
                            $linked = fx::data($f->get_related_type(), $val);
                            $val = $linked['name'];
                        }
                        break;
                    case fx_field::FIELD_STRING: case fx_field::FIELD_TEXT:
                        $val = strip_tags($val);
                        $val = mb_substr($val, 0, 150);
                        break;
                    case fx_field::FIELD_IMAGE:
                        $val = fx::image($val, 'max-width:100px,max-height:50px');
                        $val = '<img src="'.$val.'" alt="" />';
                        break;
                    case fx_field::FIELD_MULTILINK:
                        $val = fx::alang('%d items', 'system', count($val));
                        break;
                }
                
                $r[$f['keyword']] = $val;
            }
            $list['values'][]= $r;
        }
        
        $this->response->add_buttons(array(
            "delete"
        ));
        
        return array('fields' => array('list' => $list));
    }
}