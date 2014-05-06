<?php
class fx_controller_component_page extends fx_controller_component {
    public function do_neighbours() {
        $item = fx::env('page');
        
        $q = $this->get_finder()->order(null)->limit(1);
        
        $q_next = clone $q;
        $q_prev = clone $q;
        
        
        
        if ($this->get_param('sorting') === 'auto') {
            $item_ib_params = fx::data('infoblock', $item['infoblock_id'])->get('params');
            $ib_sorting = $item_ib_params['sorting'];
            $this->set_param('sorting', $ib_sorting == 'manual' ? 'priority' : $ib_sorting);
            $this->set_param('sorting_dir', $item_ib_params['sorting_dir']);
        }
        
        $sort_field = $this->get_param('sorting', 'priority');
        $dir = strtolower($this->get_param('sorting_dir', 'asc'));
        
        $where_prev = array(array($sort_field, $item[$sort_field], $dir == 'asc' ? '<' : '>'));
        $where_next = array(array($sort_field, $item[$sort_field], $dir == 'asc' ? '>' : '<'));
        
        $group_by_parent = $this->get_param('group_by_parent');
        
        if ($group_by_parent) {
            $c_parent = fx::content($item['parent_id']);
            $q_prev->order('parent.priority', 'desc')->where('parent.priority', $c_parent['priority'], '<=');
            $q_next->order('parent.priority', 'asc')->where('parent.priority', $c_parent['priority'], '>=');
            $where_prev []= array('parent_id', $item['parent_id'], '!=');
            $where_next []= array('parent_id', $item['parent_id'], '!=');
        }
        
        
        $q_prev->order($sort_field, $dir == 'asc' ? 'desc' : 'asc')
                ->where($where_prev, null, 'or');
        $prev = $q_prev->all();
        
        $q_next->order($sort_field, $dir)
                ->where($where_next, null, 'or');
        
        $next = $q_next->all();
        
        
        //fx::log($item, $q_next, $q_prev);
        
        return array(
            'prev' => $prev,
            'current' => $item,
            'next' => $next
        );
    }
}
/*
1
2
+3
4
5
 * 
 */