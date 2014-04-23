<?php
class fx_content_video extends fx_content {
    protected function _before_save() {
        parent::_before_save();
        if(preg_match('/<[\w\s]+/', $this['embed_html'])==0) {
            if (preg_match('/((youtube.com\/watch\?v=(?P<url>\w+))|(youtu.be\/(?P<url1>\w+))){1}/', $this['embed_html'], $matches)!=0) {
                $url = '';
                if (!empty($matches['url'])) {
                    $url = $matches['url'];
                } elseif(!empty($matches['url1'])) { 
                    $url = $matches['url1'];
                }
                if (!empty($url)) {
                   $this['embed_html'] = '<iframe width="420" height="315" src="http://www.youtube.com/embed/'.$url.'?wmode=opaque" frameborder="0" allowfullscreen></iframe>';         
                }
            }
        } else {
            if (preg_match('~src="(?P<url>[\/\w.]+)"~', $this['embed_html'], $matches)!=0) {
                $this['embed_html'] = str_replace($matches['url'], $matches['url'].'?wmode=opaque', $this['embed_html']);
            }
        }
        
    }
}