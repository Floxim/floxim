<?php
class fx_controller_admin_log extends fx_controller_admin {
    
    public function process() {
        fx::debug()->disable();
        return parent::process();
    }
    
    public function all() {
        
        $field = array('type' => 'list', 'filter' => true);
        $field['labels'] = array(
            'date' => fx::alang('Date', 'system'),
            'request' => fx::alang('Request', 'system'),
            'time' => fx::alang('Time', 'system'),
            'entries' => fx::alang('Entries', 'system')
        );
        $field['values'] = array();
        
        $logger = fx::debug();
        $index = $logger->get_index();
        
        foreach ($index as $item) {
            $r = array(
                'id' => $item['id'],
                'date' => array(
                    'name' => date('d.m.Y, H:i:s', round($item['start'])),
                    'url' => '#admin.log.show('.$item['id'].')'
                ),
                'request' => '['.$item['method'].'] '.$item['host'].$item['url'],
                'time' => sprintf('%.5f', $item['time']),
                'entries' => $item['count_entries']
            );
            
            $field['values'][]= $r;
        }
        
        $this->response->breadcrumb->add_item(fx::alang('Logs'), '#admin.log.all');
        $this->response->submenu->set_menu('log');
        return array(
            'fields' => array($field)
        );
    }
    
    public function show($input) {
        $log_id = $input['params'][0];
        
        $logger = fx::debug();
        
        $meta = $logger->get_index($log_id);
        
        $this->response->breadcrumb->add_item(fx::alang('Logs'), '#admin.log.all');
        if ($meta) {
            $name = '['.$meta['method'].'] '.$meta['url'].', '.date('d.m.Y, H:i:s', round($meta['start']));
            $this->response->breadcrumb->add_item($name, '#admin.log.show');
        }
        $this->response->submenu->set_menu('log');
        return array(
            'fields' => array(
                array(
                    'type' => 'button',
                    'label' => fx::alang('Delete', 'system'),
                    'options' => array(
                        'action' => 'drop_log',
                        'essence' => 'log',
                        'fx_admin' => 'true',
                        'log_id' => $log_id
                    )
                ),
                array(
                    'type' => 'button',
                    'label' => fx::alang('Delete all', 'system'),
                    'options' => array(
                        'action' => 'drop_all',
                        'essence' => 'log',
                        'fx_admin' => 'true'
                    )
                ),
                array(
                    'type' => 'html',
                    'html' => '<div class="fx_debug_entries">'.$logger->show_item($log_id)."</div>"
                )
            )
        );
    }
    
    public function drop_log($input) {
        fx::debug()->drop_log($input['log_id']);
        return array('reload' => '#admin.log.all');
    }
    public function drop_all() {
        fx::debug()->drop_all();
        return array('reload' => '#admin.log.all');
    }
}