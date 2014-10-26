<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class User extends Admin
{

    public function all()
    {
        $users = fx::data('user')->all();
        $result = array('type' => 'list', 'filter' => true, 'tpl' => 'imgh');
        $result['labels'] = array();
        $result['labels'] = array(
            'name'  => fx::alang('User name', 'system'),
            'email' => fx::alang('Email', 'system'),
        );
        $result['values'] = array();
        foreach ($users as $v) {
            $r = array(
                'id'     => $v->getId(),
                'entity' => '',
                'name'   => array(
                    'name' => $v->get('name'),
                    'url'  => '#admin.user.edit(' . $v->getId() . ')'
                ),
                'email'  => $v->get('email')
            );
            $result['values'][] = $r;
        }
        $result['entity'] = 'user';
        $res = array('fields' => array($result));
        $this->response->addButtons(array(
            array(
                'key'   => 'add',
                'title' => fx::alang('Add new user', 'system'),
                'url'   => '#admin.user.add()'
            ),
            "delete"
        ));
        $this->response->submenu->setMenu('user');
        $this->response->breadcrumb->addItem(fx::alang('Users', 'system'), '#admin.user.all');
        return $res;
    }

    /**
     * Register a new user IN the ADMIN panel
     *
     * @param type $input
     *
     * @return type
     */
    public function addSave($input)
    {
        return $this->save($input);
    }

    public function editSave($input)
    {
        $info = fx::data('user', $input['id']);
        return $this->save($input, $info);
    }

    protected function save($input, $info = null)
    {
        $result = array('status' => 'ok');

        $email = trim($input['f_email']);
        $name = trim($input['f_name']);
        if (!$email || !fx::util()->validateEmail($email)) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Fill in correct email', 'system');
            $result['fields'][] = 'email';
        }
        if (!$name) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang('Fill in name', 'system');
            $result['fields'][] = 'name';
        }
        if ($info && (empty($input['password']) || empty($input['password2']))) {
            unset($input['password']);
        }

        if (!$info) {
            if (!$input['password']) {
                $result['status'] = 'error';
                $result['text'][] = fx::alang('Password can\'t be empty', 'system');
                $result['fields'][] = 'password';
            }

            if ($result['status'] != 'error') {
                $info = fx::data('user')->create(array(
                    'checked' => 1,
                    'created' => date("Y-m-d H:i:s")
                ));
            }
        }
        foreach ($input as $name => $value) {
            if (preg_match('~^f_[\w]+~', $name) === 1) {
                $data[preg_replace('~^f_~', '', $name)] = $value;
            }
        }

        if (isset($input['password']) && isset($input['password2'])) {
            if (!$input['password'] || !$input['password2'] || $input['password'] != $input['password2']) {
                $result['status'] = 'error';
                $result['text'][] = fx::alang('Passwords do not match', 'system');
                $result['fields'][] = 'password';
                $result['fields'][] = 'password2';
            } else {
                $data['password'] = $input['password'];
            }
        }
        try {
            if ($result['status'] == 'ok') {
                $info->set($data);
                $info->save();
            }
        } catch (Exception $e) {
            $result['status'] = 'error';
            $result['text'][] = fx::alang($e->getMessage(), 'system');
            $result['fields'][] = 'email';
        }
        $result['reload'] = '#admin.user.all';
        return $result;
    }

    public function edit($input)
    {
        $info = fx::data('user', $input['params'][0]);
        $fields = $this->form($info);
        $fields[] = $this->ui->hidden('action', 'edit');
        $fields[] = $this->ui->hidden('id', $info['id']);

        $result['fields'] = $fields;
        $this->response->addFormButton('save');
        $this->response->submenu->setMenu('user');
        $this->response->breadcrumb->addItem(fx::alang('Users', 'system'), '#admin.user.all');
        $this->response->breadcrumb->addItem(fx::alang('Edit user', 'system'),
            '#admin.user.edit(' . $input['params'][0] . ')');
        return $result;
    }

    public function add()
    {
        $fields = $this->form(null);
        $fields[] = $this->ui->hidden('action', 'add');
        $result['fields'] = $fields;
        $this->response->addFormButton('save');
        $this->response->submenu->setMenu('user');
        $this->response->breadcrumb->addItem(fx::alang('Users', 'system'), '#admin.user.all');
        $this->response->breadcrumb->addItem(fx::alang('Add user', 'system'), '#admin.user.add()');
        return $result;
    }

    protected function allowEditAdmin($user)
    {
        if (!$user['id'] || !$user['is_admin']) {
            return true;
        }
        $another_admin = fx::data('user')->where('is_admin', 1)->where('id', $user['id'], '!=')->one();
        return $another_admin ? true : false;
    }

    protected function form($info)
    {
        $fields[] = $this->ui->input('f_email', fx::alang('Email', 'system'), $info['email']);
        $fields[] = $this->ui->input('f_name', fx::alang('User name', 'system'), $info['name']);
        $fields[] = $this->ui->password('password', fx::alang('Password', 'system'));
        $fields[] = $this->ui->password('password2', fx::alang('Confirm password', 'system'));

        if ($this->allowEditAdmin($info)) {
            $fields[] = array(
                'type'  => 'checkbox',
                'name'  => 'f_is_admin',
                'label' => fx::alang('Is admin?', 'system'),
                'value' => $info['is_admin']
            );
        }

        $fields[] = $this->ui->hidden('posting');
        $fields[] = $this->ui->hidden('entity', 'user');

        return $fields;
    }

    public function deleteSave($input)
    {
        fx::data('user', $input['id'])->delete();
        return array('status' => 'ok');
    }

}