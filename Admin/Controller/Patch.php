<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class Patch extends Admin
{

    public function all($input)
    {
        if (isset($input['params'][0])) {

        }
        $bSkipCheckUpdates = isset($input['params'][0]) ? $input['params'][0] : false;
        if (!$bSkipCheckUpdates) {
            if (!fx::data('patch')->checkUpdates()) {
                $this->response->addField(array(
                    'type'  => 'label',
                    'value' => '<p style="color:#F00;">' . fx::alang('Update check failed', 'system') . '</p>'
                ));
            }
        }

        $this->response->addField(array(
            'type'  => 'label',
            'value' => '<p>' . fx::alang('Current Floxim version:', 'system') . ' ' . fx::version() . '</p>'
        ));

        $patches = fx::data('patch')->all();

        $list = array('type' => 'list', 'filter' => true, 'sortable' => false);
        $list['labels'] = array(
            'name'        => fx::alang('Version'),
            'description' => fx::alang('Description'),
            'buttons'     => array('type' => 'buttons'),
            'from'        => fx::alang('Previous'),
            'status'      => fx::alang('Status')
        );

        $list['values'] = array();
        $have_ready = false;
        foreach ($patches as $patch) {
            $r = array(
                'row_id'      => 'patch_id_' . $patch['id'],
                'name'        => $patch['to'],
                'description' => $patch['description'],
                'from'        => $patch['from'],
                'status'      => $patch['status'],
                'buttons'     => array()
            );
            if ($patch['status'] == 'ready') {
                $have_ready = true;
                $r['buttons'] [] = array(
                    'url'   => 'patch.install(' . $patch['id'] . ')',
                    'label' => fx::alang('Install')
                );
            };
            $list['values'][] = $r;
        }
        $this->response->addField($list);
        if ($have_ready) {
            $this->response->addField(array(
                'type'  => 'button',
                'func'  => 'fx_patch.install_chain',
                'label' => fx::alang('Install all')
            ));
        }
        $this->setLayout();
    }

    public function getNextForInstall()
    {
        $result = array();
        if ($patch = fx::data('patch')->getReadyForInstall()) {
            $result = $patch->get();
        }

        return json_encode($result);
    }

    public function installSilent($input)
    {
        // TODO: duplicate logic with method "install"
        $result = array('error' => null);

        $patch_id = $input['params'][0];
        if (!$patch_id) {
            $result['error'] = 'Empty params';
            return json_encode($result);
        }
        $patch = fx::data('patch', $patch_id);
        if (!$patch) {
            $result['error'] = 'Patch not found';
            return json_encode($result);
        }

        try {
            if (!$patch->install()) {
                $result['error'] = 'Install failed!';
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return json_encode($result);
    }

    public function install($input)
    {
        $patch_id = $input['params'][0];
        if (!$patch_id) {
            return;
        }
        $patch = fx::data('patch', $patch_id);
        if (!$patch) {
            return;
        }
        $this->response->addField(array(
            'type'  => 'label',
            'value' => '<p>' . sprintf(fx::alang('Installing patch %s...', 'system'), $patch['to']) . '</p>'
        ));

        $res = false;
        try {
            $res = $patch->install();
        } catch (Exception $e) {
            $this->response->addField(array(
                'type'  => 'label',
                'value' => '<p style="color:#F00;">' . $e->getMessage() . '</p>'
            ));
        }

        if (!$res) {
            $this->response->addField(array(
                'type'  => 'label',
                'value' => '<p style="color:#F00;">Install failed!</p>'
            ));
        } else {
            // retrieve changes
            $changes = '<ul>';
            $logs = fx::changelog($patch['to']);
            foreach ($logs as $log) {
                $changes .= "<li>{$log['message']}</li>";
            }
            $changes .= '</ul>';

            $this->response->addField(array(
                'type'  => 'label',
                'value' => '<p>Patch installed sucessfully!</p>' . $changes
            ));
        }
        $this->response->addField(array(
            'type'  => 'button',
            'url'   => 'patch.all',
            'label' => 'Back'
        ));
        $this->setLayout($patch);
    }

    protected function setLayout($c_patch = null)
    {
        $this->response->breadcrumb->addItem(fx::alang('Patches', 'system'), '#admin.patch.all');
        if ($c_patch) {
            $this->response->breadcrumb->addItem($c_patch['to'], '#admin.patch.view(' . $c_patch['id'] . ')');
        }
        $this->response->submenu->setMenu('patch');
    }
}