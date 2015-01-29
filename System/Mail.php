<?php

namespace Floxim\Floxim\System;

/**
 * Floxim wrapper for PHPMailer library
 */
class Mail
{
    public $mailer = null;
    protected $data = array();
    protected $mail_template = null;

    public function __construct($params = null, $data = array())
    {
        $this->data = $data;
        $this->mailer = new \PHPMailer();

        $this->mailer->CharSet = 'utf-8';

        if (!is_array($params)) {
            $params = array();
        }

        if (!isset($params['from'])) {
            $from = fx::config('mail.from');
            if (!$from) {
                $from = 'noreply@' . fx::env('host');
            }
            $params['from'] = $from;
        }

        foreach (array('host', 'password', 'user', 'port') as $smtp_prop) {
            if (!isset($params['smtp_' . $smtp_prop]) && ($conf_prop = fx::config('mail.smtp_' . $smtp_prop))) {
                $params['smtp_' . $smtp_prop] = $conf_prop;
            }
        }

        $this->setParams($params);
    }

    /**
     * Set params from array
     * @param array $params
     * @return \Floxim\Floxim\System\Mail
     */
    public function setParams($params = array())
    {

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
        
        if (isset($params['bcc'])) {
            $this->bcc($params['bcc']);
        }
        
        return $this;
    }

    /**
     * Set mail subject
     * @param string $subject
     * @return \Floxim\Floxim\System\Mail
     */
    public function subject($subject)
    {
        $this->mailer->Subject = $subject;
        return $this;
    }

    /**
     * Set or get mail body
     * @param string $message
     * @return \Floxim\Floxim\System\Mail
     */
    public function message($message = null)
    {
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
     * @return \Floxim\Floxim\System\Mail
     */
    public function smtp($host, $user = null, $password = null, $port = null)
    {
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

        $this->mailer->Host = $host;
        if ($port) {
            $this->mailer->Port = $port;
        }
        return $this;
    }

    /**
     * Set mail reciever address
     * @param mixed $to one address or array of addresses
     * @return \Floxim\Floxim\System\Mail
     */
    public function to($to)
    {
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
     * @return \Floxim\Floxim\System\Mail
     */
    public function from($address, $name = null)
    {
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
    public function send()
    {
        $this->processTemplate();
        return $this->mailer->send();
    }

    /**
     * Load message template
     * @param string $template template code
     */
    public function template($template, $data = null)
    {
        $tpl = fx::content('mail_template')
            ->where('keyword', $template)
            ->one();
        if ($tpl) {
            $this->mail_template = $tpl;
        }
        if (!is_null($data)) {
            $this->data($data);
        }
        return $this;
    }

    /**
     * Append data for message template
     * @param mixed $key
     * @param mixed $value
     * @return \Floxim\Floxim\System\Mail
     */
    public function data($key, $value = null)
    {
        if (func_num_args() == 2 && is_string($key)) {
            $this->data[$key] = $value;
            return $this;
        }
        if (func_num_args() == 1 && is_array($key)) {
            $this->data = array_merge_recursive($this->data, $key);
        }
        return $this;
    }

    protected function processTemplate()
    {
        if (!$this->mail_template) {
            return;
        }
        $tpl = $this->mail_template;
        $props = array('subject', 'message');
        $res = array();
        foreach ($props as $prop) {
            $prop_tpl = $tpl[$prop];
            $tpl = fx::template()->virtual($prop_tpl);
            $tpl->isAdmin(false);
            $res[$prop] = $tpl->render($this->data);
        }
        $this->subject($res['subject']);
        $this->message($res['message']);
        if ($tpl['from']) {
            $this->from($tpl['from']);
        }
        if ($tpl['bcc']) {
            $this->bcc($tpl['bcc']);
        }
    }

    public function bcc($recievers)
    {
        $recievers = explode(",", $recievers);
        foreach ($recievers as $address) {
            $this->mailer->addBCC($address);
        }
        return $this;
    }
}