<?php
namespace Floxim\Floxim\Component\User;

use fx;

class Controller extends \Floxim\Floxim\Controller\Frontoffice {
    public function do_auth_form() {
        $user = fx::user();
        
        if (!$user->is_guest()) {
            if (!fx::is_admin()) {
                return false;
            }
            $this->_meta['hidden'] = true;
        }
        
        $form = $user->get_auth_form();
        
        if ($form->is_sent() && !$form->has_errors()) {
            $vals = $form->get_values();
            if (!$user->login($vals['email'], $vals['password'], $vals['remember'])) {
                $form->add_error('User not found or password is wrong', 'email');
            } else {
                $location = $_SERVER['REQUEST_URI'];
                if ($location  === '/floxim/') {
                    $location = '/';
                }
                // send admin to cross-auth page
                if ($user->is_admin()) {
                    fx::input()->set_cookie('fx_target_location', $location);
                    fx::http()->redirect('/~ajax/user._crossite_auth_form');
                }
                fx::http()->redirect($location);
            }
        }
        
        return array(
            'form' => $form
        );
    }
    
    /**
     * Show form to authorize user on all sites
     */
    public function do__crossite_auth_form() {
        if (!fx::user()->is_admin()) {
            fx::http()->redirect('/');
        }
        $sites = fx::data('site')->all();
        $hosts = array();
        foreach ($sites as $site) {
            foreach ($site->get_all_hosts() as $host) {
                if ($host === fx::env('host')) {
                    continue;
                }
                $hosts[]= $host;
            }
        }
        fx::env('ajax', false);
        $target_location = fx::input()->fetch_cookie('fx_target_location');
        if (!$target_location) {
            $target_location = '/';
        }
        fx::log('chosts', $hosts);
        if (count($hosts) === 0) {
            fx::http()->redirect($target_location);
        }
        return array(
            'hosts' => $hosts,
            'auth_url' => '/~ajax/user._crossite_auth',
            'target_location' => $target_location,
            'session_key' => fx::data('session')->load()->get('session_key')
        );
    }
    
    public function do__crossite_auth() {
        if (isset($_POST['email']) && isset($_POST['password'])) {
            fx::user()->login($_POST['email'], $_POST['password']);
        } elseif (isset($_POST['session_key'])) {
            $session = fx::data('session')->get_by_key($_POST['session_key']);
            if ($session) {
                $session->set_cookie();
                $user = fx::data('content_user', $session['user_id']);
                return "Hello, ".$user['name'].'!<br /> '.fx::env('host').' is glad to see you!';
            }
        }
    }
    
    public function do_greet() {
        $user = fx::user();
        if ($user->is_guest()) {
            return false;
        }
        return array(
            'user' => $user,
            'logout_url' => $user->get_logout_url()
        );
    }
    
    public function do_recover_form() {
        $form = new fx_form();
        $form->add_fields(array(
            'email' => array(
                'label' => 'E-mail',
                'validators' => 'email -l',
                'value' => $this->get_param('email')
            ),
            'submit' => array(
                'type' => 'submit',
                'label' => 'Send me new password'
            )
        ));
        if ($form->is_sent() && !$form->has_errors()) {
            $user = fx::data('content_user')->get_by_login($form->email);
            if (!$user) {
                $form->add_error(fx::lang('User not found'), 'email');
            } else {
                $password = $user->generate_password();
                $user['password'] = $password;
                $user->save();
                fx::data('session')->where('user_id', $user['id'])->delete();
                $form->add_message('New password is sent to '.$form->email);
                fx::mail()
                    ->to($form->email)
                    ->data('user', $user)
                    ->data('password', $password)
                    ->data('site', fx::env('site'))
                    ->template('user.password_recover')
                    ->send();
            }
        }
        return array('form' => $form);
    }
    
    public function do__logout() {
        $user = fx::user();
        $user->logout();
        $back_url = $this->get_param('back_url', '/');
        fx::http()->redirect($back_url);
    }
}