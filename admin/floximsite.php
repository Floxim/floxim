<?php

class fx_admin_floximsite {

    protected $url = 'http://floxim.org/';

    
    protected function send($post) {
        $data = http_build_query($post, null, '&');

        $opts = array('http' =>
                array(
                        'method' => 'POST',
                        'header' => 'Content-type: application/x-www-form-urlencoded',
                        'content' => $data,
                        'timeout' => 10,
                )
        );

        $context = stream_context_create($opts);
        $response = @file_get_contents($this->url, false, $context);

        return $response;
    }

    protected function get_base_post() {
        $post = array();
        $post['userinfo'] = $this->get_userinfo();
        return $post;
    }

    protected function get_userinfo() {
        $info = array();
        $info['host'] = $_SERVER['HTTP_HOST'];
        return $info;
    }

}