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
        
        if (!isset($params['smtp_host'])) {
            $smtp_host = fx::config('mail.smtp_host');
            if ($smtp_host) {
                $params['smtp_host'] = $smtp_host;
                $smtp_pass = fx::config('mail.smtp_password');
                if ($smtp_pass) {
                    $params['smtp_password'] = $smtp_pass;
                }
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
        
        if (isset($params['message'])) {
            $this->message($params['message']);
        }
        
        if (isset($params['from'])) {
            $this->from($params['from'], isset($params['from_name']) ? $params['from_name'] : null);
        }
        
        if (isset($params['smtp_host'])) {
            $this->smtp(
                $params['smtp_host'], 
                isset($params['smtp_password']) ? $params['smtp_password'] : null
            );
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
     * Set mail body
     * @param string $message
     * @return \fx_system_mail
     */
    public function message($message) {
        $this->mailer->Body = $message;
        return $this;
    }
    
    /**
     * Set up or disable smtp mode
     * @param mixed $host host name or false to switch off smtp mode
     * @param string $password
     * @return \fx_system_mail
     */
    public function smtp($host, $password = null) {
        if ($host === false) {
            $this->mailer->isMail();
            return $this;
        }
        $this->mailer->isSMTP();
        $this->mailer->Host = $host;
        if ($password) {
            $this->mailer->Password = $password;
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
        $from_name = null;
        if (preg_match("~<(.+?)>~", $address, $from_name)) {
            $address = trim(preg_replace("~<(.+?)>~", '', $address));
            if (!$name) {
                $name = $from_name[1];
            }
        }
        $this->mailer->From = $address;
        if ($name) {
            $this->mailer->FromName = $name;
        }
        return $this;
    }
    
    
    public function send() {
        return $this->mailer->send();
    }
}