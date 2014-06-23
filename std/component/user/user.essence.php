<?php
class fx_content_user extends fx_content {

    static public function load() {
        $session = fx::data('session')->load();
        $user = null;
        if ($session && $session['user_id']) {
            $session->set_cookie();
            $user = fx::data('content_user', $session['user_id']);
        }
        
        if (!$user) {
            $user = fx::data('content_user')->create();
        }
        
        fx::env()->set_user($user);
        return $user;
    }

    public function login($login, $password, $remember = true) {
        $user = fx::data('content_user')->get_by_login($login); 
        if (!$user || !$user['password'] || crypt($password, $user['password'])!==$user['password']) {
            return false;
        }
        // manually replace all fields by real user's fields
        $this->data = array();
        $this->modified = array();
        foreach ($user->get() as $f => $v) {
            $this->data[$f] = $v;
        }
        $this->create_session($remember);
        return true;
    }
    
    public function get_logout_url() {
        return '/~ajax/user._logout/';
    }


    public function logout() {
        fx::data('session')->stop();
    }
    
    public function is_admin() {
        return (bool) $this['is_admin'];
    }

    public function create_session($remember = 0) {
        $session = fx::data('session')->start(array(
            'user_id' => $this['id'],
            // admins have one cross-site session
            'site_id' => $this->is_admin() ? null : fx::env('site_id'),
            'remember' => $remember
        ));
        return $session;
    }

    
    protected function _before_save () {
        if ($this->is_modified('password')) {
            $this['password'] = crypt($this['password'],  uniqid(mt_rand(), true));
        }
        if ($this->is_modified('email')) {
            $existing = fx::data('content_user')
                            ->where('email', $this['email'])
                            ->where('id', $this['id'], '!=')
                            ->one();
            if ($existing) {
                throw new Exception("Ununique email");
            }
        }
    }
    
    public function is_guest() {
        return !$this['id'];
    }
    
    public function get_auth_form() {
        $form = new fx_form();
        $form->add_fields(array(
            'email' => array(
                'label' => 'E-mail',
                'validators' => 'email -l'
            ),
            'password' => array(
                'type' => 'password',
                'label' => 'Password'
            ),
            'remember' => array(
                'type' => 'checkbox',
                'label' => 'Remember me'
            ),
            'submit' => array(
                'type' => 'submit',
                'label' => 'Log in'
            )
        ));
        return $form;
    }
    
    public function generate_password() {
        $letters = '1234567890abcdefghijklmnopqrstuvwxyz';
        $specials = '!#$%&*@~';
        $res = '';
        $length = rand(6, 9);
        $special_pos = rand(1, $length - 1);
        foreach (range(0, $length) as $n) {
            
            if ($n == $special_pos) {
                $chars = $specials;
            } else {
                $chars = $letters;
            }
            $chars = str_split($chars);
            shuffle($chars);
            
            $letter_index = array_rand($chars);
            $letter = $chars[$letter_index];
            if (preg_match("~[a-z]~", $letter) && rand(0, 3) === 3) {
                $letter = strtoupper($letter);
            }
            $res .= $letter;
        }
        return $res;
    }
}