<?php

namespace Floxim\Floxim\Controller;

use Floxim\Floxim\Component\Field;
use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Component extends Frontoffice {
    
    protected function _count_parent_id() {
        if (preg_match("~^list_infoblock~", $this->action)) {
            $this->set_param('parent_id', $this->_get_parent_id());
        }
    }
    
    public function process() {
        $this->listen('before_action_run', array($this, '_count_parent_id'));
        $result = parent::process();
        return $result;
    }
    
    protected function _get_config_sources() {
        $sources = array();
        $com_dir = fx::path()->to_abs('component');
        $sources []= fx::path('floxim', '/controller/component.cfg.php');
        $com = $this->get_component();
        $chain = $com->get_chain();
        foreach ($chain as $com) {
            $com_file = fx::path('std', '/component/'.$com['keyword'].'/'.$com['keyword'].'.cfg.php');
            if (file_exists($com_file)) {
                $sources[]= $com_file;
            }
            $com_file = fx::path()->to_abs($com_dir.'/'.$com['keyword'].'/'.$com['keyword'].'.cfg.php');
            if (file_exists($com_file)) {
                $sources[]= $com_file;
            }
        }
        return $sources;
    } 

    public function get_controller_name($with_type = false){
        $name = $this->_content_type;
        if ($with_type) {
            $name = 'component_'.$name;
        }
        return $name;
    }
    
    public function save_selected_linkers($ids) {
        if (!is_array($ids)) {
            return;
        }
        $linkers = $this->_get_selected_linkers();
        $last_priority = 0;
        foreach ($linkers as $linker) {
            $linker_pos = array_search($linker['linked_id'], $ids);
            if ($linker_pos === false) {
                $linker->delete();
                $linkers->find_remove('id', $linker['id']);
            } else {
                $linker['priority'] = $linker_pos;
                $last_priority = $linker_pos;
                $linker->save();
                unset($ids[$linker_pos]);
            }
        }
        if (count($ids) > 0) {
            $ib = fx::data('infoblock', $this->get_param('infoblock_id'));
            if ($this->get_param('parent_type') == 'current_page_id') {
                $parent_id = fx::env('page_id');
            } else {
                $parent_id = $ib['page_id'];
            }
            foreach ($ids as $id) {
                $linker = fx::data('linker')->create();                $linker['parent_id'] = $parent_id;
                $linker['infoblock_id'] = $ib['id'];
                $linker['linked_id'] = $id;
                $linker['priority'] = ++$last_priority;
                $linker->save();
            }
        }
    }
    
    public function drop_selected_linkers() {    
        $linkers = fx::data('linker')                ->where('infoblock_id', $this->get_param('infoblock_id'))
                ->all();
        $linkers->apply(function($i){ 
           $i->delete(); 
        });
    }

    /*
     * @return fx_collection
     */
    protected function _get_selected_linkers () {
        $q = fx::data('linker')            ->where('infoblock_id', $this->get_param('infoblock_id'))
            ->order('priority');
        if ($this->get_param('parent_type') == 'current_page_id') {
           $q->where('parent_id', fx::env('page_id')); 
        }
        return $q->all();
    }
    
    protected function _get_selected_values() {
        $res = $this->_get_selected_linkers()->column('linked_id');
        return $res;
    }
    
    public function get_selected_field($with_values = true) {
        $field = array (
            'name' => 'selected', 
            'label' => fx::alang('Selected','controller_component'),
            'type' => 'livesearch',
            'is_multiple' => true,
            'ajax_preload' => true,
            'params' => array(
                'content_type' => $this->_content_type
            ),
            'stored' => false
        );
        if ($with_values) {
            $field['value'] =  $this->_get_selected_values()->get_data();
        }
        return $field;
    }

    public function get_conditions_field() {
        $res_field = array(
            'name' => 'conditions',
            'label' => fx::alang('Conditions','controller_component'),
            'type' => 'set', 
            'is_cond_set' => true,  
            'tpl' => array(
                array(
                    'id' => 'name',
                    'name' => 'name',
                    'type' => 'select'
                ), 
            ),
            'operators_map' => array (
                'string' => array(
                    'contains' => 'contains',
                    '=' => '=',
                    'not_contains' => 'not contains',
                    '!=' => '!='
                ),
                'int' => array(
                    '=' => '=',
                    '>' => '>',
                    '<' => '<',
                    '>=' => '>=',
                    '<=' => '<=',
                    '!=' => '!=',
                ),
                'datetime' => array(
                    '=' => '=',
                    '>' => '>',
                    '<' => '<',
                    '>=' => '>=',
                    '<=' => '<=',
                    '!=' => '!=',
                    'next' => 'next',
                    'last' => 'last',
                    'in_future' => 'in future',
                    'in_past' => 'in past'
                ),
                'multilink' => array(
                    '=' => '=',
                    '!=' => '!=',
                ),
                'link' => array(
                    '=' => '=',
                    '!=' => '!=',
                ),
            ),
            'labels' => array(
                'Field',
                'Operator',
                'Value'
            ),
        );
        $com = $this->get_component();
        $searchable_fields =  
                $com
                ->all_fields()
                ->find('type', Field\Entity::FIELD_IMAGE, '!=');
        foreach ($searchable_fields as $field) {
            $res = array(
                'description' => $field['name'],
                'type' => Field\Entity::get_type_by_id($field['type'])
            );
            if ($field['type'] == Field\Entity::FIELD_LINK) {
                $res['content_type'] = $field->get_target_name();
            }
            if ($field['type'] == Field\Entity::FIELD_MULTILINK) {
                $relation = $field->get_relation();
                $res['content_type'] = $relation[0] == System\Data::MANY_MANY ? $relation[4] : $relation[1] ;
            }
            // Add allow values for select parent page
            if ($field['keyword'] == 'parent_id') {
                $pages = $this->_get_allow_parent_pages();
                $values = $pages->get_values(array('id','name'));
                $res['values'] = $values;
            }
            $res_field['tpl'][0]['values'][$field['keyword']] = $res;
        }
        $ib_field_params = array(
            'description' => 'Infoblock',
            'type' => 'link',
            'content_type' => 'infoblock',
            'conditions' => array(
                'controller' => array(
                    $com->get_all_variants()->get_values('keyword'),
                    'IN'
                ),
                'site_id' => fx::env('site_id'),
                'action' => array( array('list_infoblock', 'list_selected'), 'IN')
            )
        );
        if ( ($cib_id = $this->get_param('infoblock_id'))) {
            $ib_field_params['conditions']['id'] = array($cib_id, '!=');
        }
        $res_field['tpl'][0]['values']['infoblock_id'] = $ib_field_params;
        return $res_field;
    }  
    
    public function get_target_config_fields() {
        
        /*
         * Below is the code that produces valid InfoBlock for fields-references
         * offers to choose, where to get/where to add value-links
         * you may elect not for incomprehensible Guia
         */
        $link_fields = $this->
                            get_component()->
                            all_fields()->
                            find('type', array(Field\Entity::FIELD_LINK, Field\Entity::FIELD_MULTILINK))->
                            find('type_of_edit', Field\Entity::EDIT_NONE, System\Collection::FILTER_NEQ);
        $fields = array();
        foreach ($link_fields as $lf) {
            if ($lf['type'] == Field\Entity::FIELD_LINK) {
                $target_com_id = $lf['format']['target'];
            } else {
                $target_com_id = isset($lf['format']['mm_datatype']) 
                                    ? $lf['format']['mm_datatype']
                                    : $lf['format']['linking_datatype'];
            }
            $target_com = fx::data('component', $target_com_id);
            if (!$target_com) {
                continue;
            }
            $com_infoblocks = fx::data('infoblock')->
                    where('site_id', fx::env('site')->get('id'))->
                    get_content_infoblocks($target_com['keyword']);
            
            $ib_values = array();
            foreach ($com_infoblocks as $ib) {
                $ib_values []= array($ib['id'], $ib['name']);
            }
            if (count($ib_values) === 0) {
                continue;
            }
            $c_ib_field = array(
                'name' => 'field_'.$lf['id'].'_infoblock'
            );
            if (count($ib_values) === 1) {
                $c_ib_field += array(
                    'type' => 'hidden',
                    'value' => $ib_values[0][0]
                );
            } else {
                $c_ib_field += array(
                    'type' => 'select',
                    'values' => $ib_values,
                    'label' => fx::alang('Infoblock for the field', 'controller_component')
                                .' "'.$lf['description'].'"'
                );
            }
            $fields[$c_ib_field['name']]= $c_ib_field;
        }
        return $fields;
    }
    
    /**
     * Get option to bind lost content (having no infoblock_id) to the newly created infoblock
     * @return array
     */
    public function get_lost_content_field() {
        // infoblock already exists
        if ($this->get_param('infoblock_id')) {
            return array();
        }
        $com = $this->get_component();
        $lost = fx::content($com['keyword'])
                    ->where('infoblock_id', 0)
                    ->where('site_id', fx::env('site_id'))
                    ->all();
        if (count($lost) == 0) {
            return array();
        }
        return array(
            'bind_lost_content' => array(
                'type' => 'checkbox',
                'label' => 'Bind lost content ('.count($lost).')'
            )
        );
    }
    
    public function bind_lost_content($ib, $params) {
        if (!isset($params['params']['bind_lost_content']) || !$params['params']['bind_lost_content']) {
            return;
        }
        $com = $this->get_component();
        $lost = fx::content($com['keyword'])
                    ->where('infoblock_id', 0)
                    ->where('site_id', fx::env('site_id'))
                    ->all();
        foreach ($lost as $lc) {
            $lc->set('infoblock_id', $ib['id']);
            if (!$lc['parent_id'] && $ib['page_id']) {
                $lc['parent_id'] = $ib['page_id'];
            }
            $lc->save();
        }
    }
    
    public function do_record() {
        $page = fx::env('page');
        return array('item' => $page);
    }
    
    public function do_list() {
        $f = $this->get_finder();
        $this->trigger('query_ready', $f);
        $items = $f->all();
        if (count($items) === 0) {
            $this->_meta['hidden'] = true;
        }
        $this->trigger('items_ready', $items);
        $res = array('items' => $items);
        if ( ($pagination = $this->_get_pagination()) ) {
            $res ['pagination'] = $pagination;
        }
        return $res;
    }
    
    protected function _get_fake_items($count = 3) {
        $finder = $this->get_finder();
        $items = fx::collection();
        foreach (range(1,$count) as $n) {
            $items []= $finder->fake();
        }
        return $items;
    }


    public function do_list_infoblock() {
        // "fake mode" - preview of newly created infoblock
        if ($this->get_param('is_fake')) {
            return array('items' => $this->_get_fake_items(3));
        }
        $this->listen('query_ready', function($q, $ctr) {
            $parent_id = $ctr->get_param('parent_id');
            if ( $parent_id && !$ctr->get_param('skip_parent_filter')) {
                $q->where('parent_id', $parent_id);
            }
            $infoblock_id = $ctr->get_param('infoblock_id');
            if ( $infoblock_id && !$ctr->get_param('skip_infoblock_filter')) {
                $q->where('infoblock_id', $infoblock_id);
            }
        });
        $res = $this->do_list();
        if (fx::is_admin()) {
            $infoblock = fx::data('infoblock', $this->get_param('infoblock_id'));
            $component = $this->get_component();
            $adder_title = fx::alang('Add').' '.$component['item_name'];//.' &rarr; '.$ib_name;
            
            $this->accept_content(array(
                'title' => $adder_title,
                'parent_id' => $this->_get_parent_id(),
                'type' => $component['keyword'],
                'infoblock_id' => $this->get_param('infoblock_id')
            ));
            
            if (count($res['items']) == 0) {
                $this->_meta['hidden_placeholder'] = 'Infoblock "'.$infoblock['name'].'" is empty. '.
                                                'You can add '.$component['item_name'].' here';
            }
        }
        return $res;
    }
    
    public function accept_content($params,$entity = null) {
        $params = array_merge(
            array(
                'infoblock_id' => $this->get_param('infoblock_id'),
                'type' => $this->get_content_type()
            ), $params
        );
        if (!is_null($entity)) {
            $meta = isset($entity['_meta'])?  $entity['_meta'] : array();
            if (!isset($meta['accept_content'])) {
                $meta['accept_content'] = array();
            }
            $meta['accept_content'][]= $params;
            $entity['_meta'] = $meta;
            return;
        }
        if (!isset($this->_meta['accept_content'])) {
            $this->_meta['accept_content'] = array();
        }
        $this->_meta['accept_content'] []= $params;
    }
    
    protected function _get_pagination_url_template() {
        $url = $_SERVER['REQUEST_URI'];
        $url = preg_replace("~[\?\&]page=\d+~", '', $url);
        return $url.'##'.(preg_match("~\?~", $url) ? '&' : '?').'page=%d##';
    }
    
    protected function _get_current_page_number() {
        return isset($_GET['page']) ? $_GET['page'] : 1;
    }

    protected function _get_pagination() {
        
        if (!$this->get_param('pagination')){
            return null;
        }
        $total_rows = $this->get_finder()->get_found_rows();
        
        if ($total_rows == 0) {
            return null;
        }
        $limit = $this->get_param('limit');
        if ($limit == 0) {
            return null;
        }
        $total_pages = ceil($total_rows / $limit);
        if ($total_pages == 1) {
            return null;
        }
        $links = array();
        $url_tpl = $this->_get_pagination_url_template();
        $base_url = preg_replace('~##.*?##~', '', $url_tpl);
        $url_tpl = str_replace("##", '', $url_tpl);
        $c_page = $this->_get_current_page_number();
        foreach (range(1, $total_pages) as $page_num) {
            $links[$page_num]= array(
                'active' => $page_num == $c_page,
                'page' => $page_num,
                'url' => 
                    $page_num == 1 ? 
                    $base_url : 
                    sprintf($url_tpl, $page_num)
            );
        }
        $res = array(
            'links' => fx::collection($links),
            'total_pages' => $total_pages,
            'total_items' => $total_rows,
            'current_page' => $c_page
        );
        if ($c_page != 1) {
            $res['prev'] = $links[$c_page-1]['url'];
        }
        if ($c_page != $total_pages) {
            $res['next'] = $links[$c_page+1]['url'];
        }
        return $res;
    }
    
    protected function _get_parent_id() {
        $ib = fx::data('infoblock', $this->get_param('infoblock_id')); 
        $parent_id = null;
        switch($this->get_param('parent_type')) {
            case 'mount_page_id':
                $parent_id = $ib['page_id'];
                if ($parent_id === 0) {
                    $parent_id = fx::env('site')->get('index_page_id');
                }
                break;
            case 'current_page_id': default:
                $parent_id = fx::env('page')->get('id');
                break;
        }
        return $parent_id;
    }
    
    public function do_list_selected() {
        $is_overriden = $this->get_param('is_overriden');
        $linkers = null;
        // preview
        if ( $is_overriden) {
            $content_ids = array();
            $selected_val  = $this->get_param('selected');
            if (is_array($selected_val)) {
                $content_ids = $selected_val;
            }
        } else {
            // normal
            $linkers = $this->_get_selected_linkers();
            $content_ids = $linkers->get_values('linked_id');
        }
        
        $this->listen('query_ready', function($q) use ($content_ids) {
            $q->where('id', $content_ids);
        });
        if ($linkers) {
            $this->listen('items_ready', function($c, $ctr) use ($linkers) {
                if ($ctr->get_param('sorting') === 'manual') {
                    $c->sort(function($a, $b) use ($linkers) {
                        $a_l = $linkers->find_one('linked_id', $a['id']);
                        $b_l = $linkers->find_one('linked_id', $b['id']);
                        if (!$a_l || !$b_l) {
                            return 0;
                        }
                        $a_priority = $linkers->find_one('linked_id', $a['id'])->get('priority');
                        $b_priority = $linkers->find_one('linked_id', $b['id'])->get('priority');
                        return $a_priority - $b_priority;
                    });
                    $c->is_sortable = true;
                }
                $c->linker_map = array();
                foreach ($c as $cc) {
                    $c->linker_map []= $linkers->find_one('linked_id', $cc['id']);
                }
            });
        } else {
            $this->listen('items_ready', function($c, $ctr) use ($content_ids) {
                if ($ctr->get_param('sorting') === 'manual') {
                    $c->sort(function($a, $b) use ($content_ids) {
                        $a_priority = array_search($a['id'], $content_ids);
                        $b_priority = array_search($b['id'], $content_ids);
                        return $a_priority - $b_priority;
                    });
                }
            });
        }
        if (!isset($this->_meta['fields'])) {
            $this->_meta['fields'] = array();
        }
        
        $res = $this->do_list();
        
        // if we are admin and not viewing the block in preview mode,
        // let's add livesearch field loaded with the selected values
        if (!$is_overriden && fx::is_admin()) {
            $selected_field = $this->get_selected_field(false);
            $selected_field['value'] = array();
            // filter result by selected content ids, 
            // because some items can be added from inherited controllers (e.g. menu with subsections)
            $selected_items = $res['items']->find('id', $content_ids);
            foreach ($selected_items as $selected_item) {
                $selected_field['value'][]= array(
                    'name' => $selected_item['name'], 
                    'id' => $selected_item['id']
                );
            }
            $selected_field['var_type'] = 'ib_param';
            unset($selected_field['ajax_preload']);
            $this->_meta['fields'][]= $selected_field;
        }
        if (count($res['items']) === 0 && fx::is_admin()) {
            $component = $this->get_component();
            $ib = fx::data('infoblock', $this->get_param('infoblock_id'));
            $this->_meta['hidden_placeholder'] = 'Infoblock "'.$ib['name'].'" is empty. '.
                                                'Select '.$component['item_name'].' to show here';
        }
        return $res;
    }
    
    public function do_list_filtered() {
        $this->listen('query_ready', function($q, $ctr) {
            $component = $ctr->get_component();
            $fields = $component->all_fields();
            $conditions = fx::collection($ctr->get_param('conditions'));
            if (!$conditions->find_one('name', 'site_id')) {
                $conditions[]= array(
                    'name' => 'site_id',
                    'operator' => '=',
                    'value' => array(fx::env('site')->get('id'))
                );
            }
            $target_parent_id = null;
            $target_infoblock_id = null;
            foreach ($conditions as $condition) {
                if (
                    $condition['name'] === 'parent_id' 
                    && is_array($condition['value']) 
                    && count($condition['value']) === 1
                ) {
                    $target_parent_id = current($condition['value']);
                } elseif (
                    $condition['name'] === 'infoblock_id' 
                    && is_array($condition['value']) 
                    && count($condition['value']) === 1    
                ) {
                    $target_infoblock_id = current($condition['value']);
                }
                
                $field = $fields->find_one('keyword', $condition['name']);
                $error = false;
                switch ($condition['operator']) {
                    case 'contains': case 'not_contains':
                        $condition['value'] = '%'.$condition['value'].'%'; 
                        $condition['operator'] = ($condition['operator']== 'not_contains' ? 'NOT ' : '').'LIKE';
                        break;
                    case 'next': 
                        if (isset($condition['value']) && !empty($condition['value'])) {
                            $q->where(
                                $condition['name'], 
                                '> NOW()', 
                                'RAW'
                            );
                            $condition['value'] = '< NOW() + INTERVAL '.$condition['value'].' '.$condition['interval']; 
                            $condition['operator'] = 'RAW';
                        } else {
                            $error = true;
                        }
                        break;
                    case 'last': 
                        if (isset($condition['value']) && !empty($condition['value'])) {
                            $q->where(
                                $condition['name'], 
                                '< NOW()', 
                                'RAW'
                            );
                            $condition['value'] = '> NOW() - INTERVAL '.$condition['value'].' '.$condition['interval']; 
                            $condition['operator'] = 'RAW';
                        } else {
                            $error = true;
                        }
                        break;
                    case 'in_future': 
                        $condition['value'] = '> NOW()'; 
                        $condition['operator'] = 'RAW';
                        break;
                    case 'in_past': 
                        $condition['value'] = '< NOW()'; 
                        $condition['operator'] = 'RAW';
                        break;
                }
                if ($field['type'] == Field\Entity::FIELD_LINK){
                    if (!isset($condition['value'])) {
                        $error = true;
                    } else {
                        $ids = array();
                        foreach ($condition['value'] as $v) {
                            $ids[]= $v;
                        }
                        $condition['value'] = $ids;
                        if ($condition['operator'] === '!=') {
                            $condition['operator'] = 'NOT IN';
                        } elseif ($condition['operator'] === '=') {
                            $condition['operator'] = 'IN';
                        }
                    }
                }

                if ($field['type'] == Field\Entity::FIELD_MULTILINK) {

                    if (!isset($condition['value']) || !is_array($condition['value'])) {
                        $error = true;
                    } else {
                        foreach ($condition['value'] as $v) {
                            $ids[]= $v;
                        }
                        $relation = $field->get_relation();
                        if ($relation[0] === System\Data::MANY_MANY){
                            $content_ids = fx::data($relation[1])->
                                where($relation[5], $ids)->
                                select('content_id')->
                                get_data()->get_values('content_id');
                        } else {
                            $content_ids = fx::data($relation[1])->
                                where('id', $ids)->
                                select($relation[2])->get_data()->get_values($relation[2]);
                        }
                        $condition['name'] = 'id';    
                        $condition['value'] = $content_ids;
                        if ($condition['operator'] === '!=') {
                            $condition['operator'] = 'NOT IN';
                        } elseif ($condition['operator'] === '=') {
                            $condition['operator'] = 'IN';
                        }
                    }
                }
                
                if ($condition['name'] == 'infoblock_id') {
                    if (empty($condition['value'])) {
                        continue;
                    }
                    $target_ib = fx::data('infoblock', $condition['value']);//->first();
                    if (!$target_ib) {
                        continue;
                    }
                    $target_ib = $target_ib->first();
                    if ($target_ib['action'] == 'list_selected') {
                        $linkers = fx::data('linker')                                    ->where('infoblock_id', $target_ib['id'])
                                    ->all();
                        $content_ids = $linkers->get_values('linked_id');
                        $condition['name'] = 'id';
                        $condition['value'] = $content_ids;
                        $condition['operator'] = 'IN';
                    }
                }
                if (!$error) {
                    $q->where(
                        $condition['name'], 
                        $condition['value'], 
                        $condition['operator']
                    );
                }
            }
            if ($target_parent_id && $target_infoblock_id) {
                $adder_title = fx::alang('Add').' '.$component['item_name'];
                $ctr->accept_content(array(
                    'title' => $adder_title,
                    'parent_id' => $target_parent_id,
                    'type' => $component['keyword'],
                    'infoblock_id' => $target_infoblock_id
                ));
            }
        });
        
        $res = $this->do_list();
        return $res;
    }
    

     /**
     * $_content_type may be one of the values
     * the table fx_component in the keyword field
     * @var string
     */
    protected $_content_type = null;
    
    /**
     * @return string
     */
    public function get_content_type() {
        if (!$this->_content_type) {
            $path = array_reverse(explode("\\", get_class($this)));
            $this->_content_type = fx::util()->camelToUnderscore($path[1]);
        }
        return $this->_content_type;
    }
    
    /**
     * @param string $content_type
     */
    public function set_content_type($content_type) {
        $this->_content_type = $content_type;
    }
    
    /**
     * Returns the component at the value of the property _content_type
     * @return fx_data_component
     */
    public function get_component() {
        return fx::data('component', $this->get_content_type());
    }
    
    
    protected $_finder = null;
    /**
     * @return fx_data_content data finder
     */
    public function get_finder() {
        if (!is_null($this->_finder)) {
            return $this->_finder;
        }
        $finder = fx::data($this->get_content_type());
        $show_pagination = $this->get_param('pagination');
        $c_page = $this->_get_current_page_number();
        $limit = $this->get_param('limit');
        if ( $show_pagination && $limit) {
            $finder->calc_found_rows();
        }
        if ( $limit ) {
            if ($show_pagination && $c_page != 1) {
                $finder->limit(
                        $limit*($c_page-1),
                        $limit
                );
            } else {
                $finder->limit($limit);
            }
        }
        if ( ($sorting = $this->get_param('sorting'))) {
            $dir = $this->get_param('sorting_dir');
            if ($sorting === 'manual') {
                $sorting = 'priority';
                $dir = 'ASC';
            }
            if (!$dir) {
                $dir = 'ASC';
            }
            $finder->order($sorting, $dir);
        }
        $this->_finder = $finder;
        return $finder;
    }
    
    public function get_signature() {
        return 'component_'.$this->get_content_type().".".$this->action;;
    }
    
    protected function _get_controller_variants() {
        //$vars = parent::_get_controller_variants();
        $vars = array();
        $com = $this->get_component();
        $chain = $com->get_chain();
        $chain = array_reverse($chain);
        foreach ($chain as $chain_item) {
            $vars []= $chain_item['keyword'];
        }
        $vars = array_unique($vars);
        return $vars;
    }
    
    public function get_actions() {
        $actions = parent::get_actions();
        $com = $this->get_component();
        foreach ($actions as $action => &$info) {
            if (!isset($info['name'])) {
                $info['name'] = $com['name'].' / '.$action;
            }
        }
        return $actions;
    }

    /**
     * Return allow parent pages for current component
     * This method need override for controller specific component
     *
     * @return fx_collection
     */
    protected function _get_allow_parent_pages() {
        return fx::collection();
    }
}
