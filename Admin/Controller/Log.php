<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Log extends Admin
{

    public function process()
    {
        fx::debug()->disable();
        return parent::process();
    }

    public function all()
    {
        $field = array('type' => 'list', 'filter' => true);
        $field['labels'] = array(
            'date'    => fx::alang('Date', 'system'),
            'request' => fx::alang('Request', 'system'),
            'time'    => fx::alang('Time', 'system'),
            'entries' => fx::alang('Entries', 'system')
        );
        $field['values'] = array();

        $logger = fx::debug();
        $index = $logger->getIndex();

        foreach ($index as $item) {
            $url = preg_replace("~^http://[^/]+~", '', $item['url']);
            $r = array(
                'id'      => $item['id'],
                'date'    => array(
                    'name' => date('d.m.Y, H:i:s', round($item['start'])),
                    'url'  => '#admin.log.show(' . $item['id'] . ')'
                ),
                'request' => '[' . $item['method'] . '] ' . $item['host'] . $url,
                'time'    => sprintf('%.5f', $item['time']),
                'entries' => $item['count_entries']
            );

            $field['values'][] = $r;
        }

        $this->response->breadcrumb->addItem(fx::alang('Logs'), '#admin.log.all');
        $this->response->submenu->setMenu('log');
        $fields = array();
        if (count($field['values']) > 0) {
            $fields [] = array(
                'type'    => 'button',
                'label'   => fx::alang('Delete all', 'system'),
                'options' => array(
                    'action'   => 'drop_all',
                    'entity'   => 'log',
                    'fx_admin' => 'true'
                )
            );
        }
        $fields [] = $field;
        return array('fields' => $fields);
    }

    public function show($input)
    {
        $log_id = $input['params'][0];

        $logger = fx::debug();

        $meta = $logger->getIndex($log_id);

        $this->response->breadcrumb->addItem(fx::alang('Logs'), '#admin.log.all');
        if ($meta) {
            $name = '[' . $meta['method'] . '] ' . $meta['url'] . ', ' . date('d.m.Y, H:i:s', round($meta['start']));
            $this->response->breadcrumb->addItem($name, '#admin.log.show');
        }
        $this->response->submenu->setMenu('log');
        return array(
            'fields' => array(
                array(
                    'type'    => 'button',
                    'label'   => fx::alang('Delete', 'system'),
                    'options' => array(
                        'action'   => 'drop_log',
                        'entity'   => 'log',
                        'fx_admin' => 'true',
                        'log_id'   => $log_id
                    )
                ),
                array(
                    'type'    => 'button',
                    'label'   => fx::alang('Delete all', 'system'),
                    'options' => array(
                        'action'   => 'drop_all',
                        'entity'   => 'log',
                        'fx_admin' => 'true'
                    )
                ),
                array(
                    'type' => 'html',
                    'html' => '<div class="fx_debug_entries">' . $logger->showItem($log_id) . "</div>"
                )
            )
        );
    }

    public function dropLog($input)
    {
        fx::debug()->dropLog($input['log_id']);
        return array('reload' => '#admin.log.all');
    }

    public function dropAll()
    {
        fx::debug()->dropAll();
        return array('reload' => '#admin.log.all');
    }
}