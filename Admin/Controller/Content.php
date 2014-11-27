<?php
namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\Component\Field;
use Floxim\Floxim\System\Fx as fx;

class Content extends Admin
{

    public function addEdit($input)
    {
        // get the edited object
        if (isset($input['content_id']) && $input['content_id']) {
            $content = fx::data('content', $input['content_id']);
            $content_type = $content['type'];
        } else {
            $content_type = $input['content_type'];
            $parent_page = fx::data('page', $input['parent_id']);
            $content = fx::data($content_type)->create(array(
                'parent_id'    => $input['parent_id'],
                'infoblock_id' => $input['infoblock_id'],
                'checked'      => 1,
                'site_id'      => $parent_page['site_id']
            ));
        }

        $fields = array(
            $this->ui->hidden('content_type', $content_type),
            $this->ui->hidden('parent_id', $content['parent_id']),
            $this->ui->hidden('entity', 'content'),
            $this->ui->hidden('action', 'add_edit'),
            $this->ui->hidden('data_sent', true),
            $this->ui->hidden('fx_admin', true)
        );

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

        if (isset($input['content_id'])) {
            $fields [] = $this->ui->hidden('content_id', $input['content_id']);
        } else {
            $fields [] = $this->ui->hidden('infoblock_id', $input['infoblock_id']);
        }

        $this->response->addFields($fields);
        $content_fields = fx::collection($content->getFormFields());

        $is_backoffice = isset($input['mode']) && $input['mode'] == 'backoffice';

        if (!$is_backoffice) {
            $tabbed = $content_fields->group('tab');
            foreach ($tabbed as $tab => $tab_fields) {
                $this->response->addTab($tab, $tab);
                $this->response->addFields($tab_fields, $tab, 'content');
            }
        } else {
            $content_fields->apply(function (&$f) {
                unset($f['tab']);
            });
            $this->response->addFields($content_fields, '', 'content');
            $this->response->addFields(array(
                $this->ui->hidden('mode', 'backoffice'),
                $this->ui->hidden('reload_url', $input['reload_url'])
            ));
        }

        $res = array('status' => 'ok');

        if (isset($input['data_sent']) && $input['data_sent']) {
            $res['is_new'] = !$content['id'];
            $content->setFieldValues($input['content']);
            foreach ($move_variants as $rel_prop) {
                if (isset($input[$rel_prop])) {
                    $content[$rel_prop] = $input[$rel_prop];
                }
            }
            try {
                $content->save();
                $res['saved_id'] = $content['id'];
                if ($is_backoffice) {
                    $res['reload'] = str_replace("%d", $content['id'], $input['reload_url']);
                }
            }  catch (\Exception $e) {
                fx::log('Exceptn', $e);
                $res['status'] = 'error';
            }
        }
        $com_item_name = fx::data('component', $content_type)->getItemName();

        if (isset($input['content_id']) && $input['content_id']) {
            $res['header'] = fx::alang('Editing ',
                    'system') . ' <span title="#' . $input['content_id'] . '">' . $com_item_name . '</span>';
        } else {
            $res['header'] = fx::alang('Adding new ', 'system') . ' ' . $com_item_name;
            if ($move_meta) {
                $res['header'] .= ' <span class="fx_header_notice">' . fx::alang($move_meta['type']) . ' ' . $move_meta['item']['name'] . '</span>';
            }
        }
        $res['view'] = 'cols';
        $this->response->addFormButton('save');
        return $res;
    }

    public function checkedSave($input)
    {

        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }

        foreach ($ids as $id) {
            if (preg_match("/(\d+)-(\d+)/", $id, $match)) {
                $class_id = $match[1];
                $content_id = $match[2];
            }

            $content = fx::data('content')->getById($class_id, $content_id);
            $content->checked();
        }

        $result['status'] = 'ok';
        return $result;
    }

    public function onSave($input)
    {
        return $this->checkedSave($input);
    }

    public function uncheckedSave($input)
    {
        $ids = $input['id'];
        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $id) {
            if (preg_match("/(\d+)-(\d+)/", $id, $match)) {
                $class_id = $match[1];
                $content_id = $match[2];
            }
            $content = fx::data('content')->getById($class_id, $content_id);
            $content->unchecked();
        }
        $result['status'] = 'ok';
        return $result;
    }

    public function offSave($input)
    {
        return $this->uncheckedSave($input);
    }

    public function deleteSave($input)
    {
        $id = isset($input['content_id']) ? $input['content_id'] : (isset($input['id']) ? $input['id'] : false);
        if (!$id) {
            return;
        }
        $content = fx::data('content', $id);
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
        $descendants = fx::data('content')->descendantsOf($content)->all();
        if (($count_descendants = $descendants->count())) {
            $fields[] = array(
                'type' => 'html',
                'html' => fx::alang('The content contains some descendants',
                        'system') . ', <b>' . $count_descendants . '</b> ' . fx::alang('items. These items will be removed.',
                        'system')
            );
        }

        $this->response->addFields($fields);
        $this->response->addFormButton(array('key' => 'save', 'label' => fx::alang('Delete')));
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
        $component = fx::data('component', $content['type']);

        $header = fx::alang("Delete") . ' ' . $component['item_name'];
        if (($content_name = $content['name'])) {
            $header .= ' "' . $content_name . '"';
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

        $neighbours = fx::data('content')->where('parent_id', $content['parent_id'])->where('infoblock_id',
            $content['infoblock_id'])->where('id', $content['id'], '!=')->order('priority')->all();
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

        $fields = $com->allFields();

        $fields->findRemove(function ($f) {
            return $f['type_of_edit'] == Field\Entity::EDIT_NONE;
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
            "delete"
        ));

        return array('fields' => array('list' => $list));
    }
}