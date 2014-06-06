<?php
class fx_controller_component_publication extends fx_controller_component_page {
    
    public function do_list () {
        $this->listen('query_ready', function (fx_data $query) {
            $query->with('tags');
        });
        return parent::do_list();
    }
    public function do_list_by_tag() {
        $this->listen('query_ready', function($query) {
            $ids = fx::data('content_classifier_linker')->
                    where('classifier_id', fx::env('page')->get('id'))->
                    select('content_id')->
                    get_data()->get_values('content_id');
            $query->where('id', $ids);
        });
        return $this->do_list();
    }

    protected function _get_publication_page() {
        $infoblock_id=$this->get_param('source_infoblock_id');
        if (!$infoblock_id) {
            $infoblock = fx::data('infoblock')->get_content_infoblocks($this->get_content_type())->first();
        } else {
            $infoblock = fx::data('infoblock', $infoblock_id);
        }
        if (!$infoblock) {
            return;
        }
        return fx::data(
            'content_page', 
            $infoblock->get('page_id')
        );
    }
    public function settings_calendar() {
        $ib_values=fx::data('infoblock')->
                    where('site_id', fx::env('site')->get('id'))->
                    get_content_infoblocks($this->get_content_type())
                    ->get_values('name', 'id');
        $fields ['source_infoblock_id']= array(
            'type' => 'select',
            'values' => $ib_values,
            'name' => 'source_infoblock_id',
            'label' => 
                fx::alang('Infoblock for the field', 'controller_component')
        );
        return $fields;
    }
    public function do_calendar() {
        $months = $this->get_finder()->
            select('DATE_FORMAT(`publish_date`, "%m") as month')->
            select('DATE_FORMAT(`publish_date`, "%Y") as year')->
            select('COUNT(DISTINCT({{content}}.id)) as `count`')->
            where('site_id', fx::env('site')->get('id'))->
            order('publish_date', 'DESC')->
            group('month')->group('year')->
            get_data();
        $base_url = '';
        $pub_page = $this->_get_publication_page();
        if ($pub_page) {
            $base_url = $pub_page->get('url');
        }
        
        $years = new fx_collection();
        $c_full_month = isset($_GET['month']) ? $_GET['month'] : null;
        $c_year = $c_full_month ? preg_replace("~\d+\.~", '', $c_full_month) : date('Y');
        foreach ($months as $m) {
            if (!isset($years[$m['year']])) {
                $years[$m['year']] = array(
                    'year' => $m['year'],
                    'months' => new fx_collection(),
                    'active' => $c_year == $m['year']
                );
            }
            
            $full_month = $m['month'].'.'.$m['year'];
            $m['active'] = $full_month == $c_full_month;
            $m['url'] = $base_url .'?month='.$full_month;
            $years[$m['year']]['months'][] = $m;
        }
        return array('items' => $years);
    }
    public function do_list_infoblock() {
        if ( isset($_GET['month']) ) {
            $this->listen('query_ready', function (fx_data $query) {
                list($month, $year) = explode(".", $_GET['month']);
                $start = $year.'-'.$month.'-01, 00:00:00';
                $end = $year.'-'.$month.'-'.date('t', strtotime($start)).', 23:59:59';
                $query->where('publish_date', $start, '>=');
                $query->where('publish_date', $end, '<=');
            });
        }
        $res = parent::do_list_infoblock();
        return $res;
    }

    /**
     * Return allow parent pages for current component
     *
     * @return fx_collection
     */
    protected function _get_allow_parent_pages() {
        // TODO: method get_content_infoblocks not use site_id filter
        $infoblocks=fx::data('infoblock')->get_content_infoblocks($this->get_content_type());

        $pages_id=array();
        foreach($infoblocks as $infoblock) {
            if (isset($infoblock['params']['parent_type']) and $infoblock['params']['parent_type']=='current_page_id') {
                // Retrieve all pages
                $pages_id=array_merge($pages_id,$infoblock->get_pages());
            } else {
                // Retrieve self page
                $pages_id[]=$infoblock['page_id'];
            }
        }
        $pages_id=array_unique($pages_id);
        if (!$pages_id) {
            return fx::collection();
        }
        /**
         * Retrieve pages object
         */
        $pages=fx::data('content_page')->where('id',$pages_id)->all();
        return $pages;
    }
}