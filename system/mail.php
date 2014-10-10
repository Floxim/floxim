<?php
/**
 * Floxim wrapper for PHPMailer library
 */
class fx_system_mail {
    public $mailer = null;
    protected $_data = null;
    
    public function __construct($params = null, $data = null) {
        require_once ( fx::path('floxim', 'lib/PHPMailer/PHPMailerAutoload.php') );
        $this->_data = $data;
        $this->mailer = new PHPMailer();
        
        $this->mailer->CharSet = 'utf-8';
        
        if (!is_array($params)) {
            $params = array();
        }
        
        if (!isset($params['from'])) {
            $from = fx::config('mail.from');
            if (!$from) {
                $from = 'noreply@'.fx::env('host');
            }
            $params['from'] = $from;
        }
        
        
        foreach (array('host', 'password', 'user', 'port') as $smtp_prop) {
            if (!isset($params['smtp_'.$smtp_prop]) && ($conf_prop = fx::config('mail.smtp_'.$smtp_prop))) {
                $params['smtp_'.$smtp_prop] = $conf_prop;
            }
        }
        
        $this->set_params($params);
    }
    
    /**
     * Set params from array
     * @param array $params
     * @return \fx_system_mail
     */
    public function set_params($params = array()) {
        
        if (isset($params['template'])) {
            $this->template($params['template']);
        }
        
        if (isset($params['message'])) {
            $this->message($params['message']);
        }
        
        if (isset($params['from'])) {
            $this->from($params['from'], isset($params['from_name']) ? $params['from_name'] : null);
        }
        
        if (isset($params['smtp_host'])) {
            $this->smtp(
                $params['smtp_host'], 
                isset($params['smtp_user']) ? $params['smtp_user'] : null,
                isset($params['smtp_password']) ? $params['smtp_password'] : null,
                isset($params['smtp_port']) ? $params['smtp_port'] : null
            );
        }
        
        if (isset($params['subject'])) {
            $this->subject($params['subject']);
        }
        
        if (isset($params['to'])) {
            $this->to($params['to']);
        }
        return $this;
    }
    
    /**
     * Set mail subject
     * @param string $subject
     * @return \fx_system_mail
     */
    public function subject($subject) {
        $this->mailer->Subject = $subject;
        return $this;
    }
    
    /**
     * Set or get mail body
     * @param string $message
     * @return \fx_system_mail
     */
    public function message($message = null) {
        if ($message === null) {
            return $this->mailer->Body;
        }
        $this->mailer->Body = $message;
        if (preg_match("~<[a-z].+>~", $message)) {
            $this->mailer->isHTML(true);
        }
        return $this;
    }
    
    /**
     * Set up or disable smtp mode
     * @param mixed $host host name or false to switch off smtp mode
     * @param string $password
     * @return \fx_system_mail
     */
    public function smtp($host, $user = null, $password = null, $port = null) {
        if ($host === false) {
            $this->mailer->isMail();
            return $this;
        }
        $this->mailer->isSMTP();
        if ($user && $password) {
            $this->mailer->SMTPAuth = true;
            $this->mailer->Password = $password;
            $this->mailer->Username = $user;
        }
        //$this->mailer->SMTPDebug = 1;
        $this->mailer->Host = $host;
        if ($port) {
            $this->mailer->Port = $port;
        }
        return $this;
    }
    
    /**
     * Set mail reciever address
     * @param mixed $to one address or array of addresses
     * @return \fx_system_mail
     */
    public function to($to) {
        if (!is_array($to)) {
            $to = array($to);
        }
        foreach ($to as $address) {
            $this->mailer->addAddress($address);
        }
        return $this;
    }
    
    /**
     * Set mail sender address
     * @param string $address email or email+name: "ivan@petrov.ru <Ivan Petrov>"
     * @param string $name
     * @return \fx_system_mail
     */
    public function from($address, $name = null) {
        $real_address = null;
        if (preg_match("~<(.+?)>~", $address, $real_address)) {
            $name = trim(preg_replace("~<(.+?)>~", '', $address));
            $address = $real_address[1];
        }
        $this->mailer->From = $address;
        if ($name) {
            $this->mailer->FromName = $name;
        }
        return $this;
    }
    
    /**
     * Send message
     * @return boolean
     */
    public function send() {
        return $this->mailer->send();
    }
    
    /**
     * Load message template
     * @param string $template template code
     */
    public function template($template) {
        $tpl = fx::content('mail_template')
                                ->where('keyword', $template)
                                ->one();
        if ($tpl) {
            $this->_mail_template = $tpl;
            if ($tpl['from']) {
                $this->from($tpl['from']);
            }
            if ($tpl['bcc']) {
                $this->bcc($tpl['bcc']);
            }
        }
        $this->_process_template();
        return $this;
    }
    
    /**
     * Append data for message template
     * @param mixed $key
     * @param mixed $value
     * @return \fx_system_mail
     */
    public function data($key, $value = null) {
        if (func_num_args() == 2 && is_string($key)) {
            $this->_data[$key] = $value;
            return $this;
        }
        if (func_num_args() == 1 && is_array($key)) {
            $this->_data = array_merge_recursive($this->_data, $key);
        }
        return $this;
    }
    
    protected function _process_template() {
        if (!$this->_mail_template) {
            return;
        }
        $props = array('subject', 'message');
        $res = array();
        foreach ($props as $prop) {
            $prop_tpl = $this->_mail_template[$prop];
            $tpl = fx::template()->virtual($prop_tpl);
            $tpl->is_admin(false);
            $res[$prop] = $tpl->render($this->_data);
        }
        $this->subject($res['subject']);
        $this->message($res['message']);
    }
    
    public function bcc($recievers) {
        $recievers = explode(",", $recievers);
        foreach ($recievers as $address) {
            $this->mailer->addBCC($address);
        }
        return $this;
    }
}