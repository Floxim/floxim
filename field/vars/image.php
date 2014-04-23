<?php

require_once fx::config()->INCLUDE_FOLDER.'phpthumb/phpThumb.config.php';

class fx_field_vars_image extends fx_field_vars_file {

    protected $width = 0;
    protected $height = 0;
    protected $wh_init = false;

    /**
     *
     * @param type $w
     * @param type $h
     * @param Set to "1" or "C" to zoom-crop towards the center, or set to "T", "B", "L", "R", "TL", "TR", "BL", "BR"
     * @return string
     */
    public function resize ( $w = 0, $h = 0, $crop = false ) {
        if ( !$this->path ) {
            return '';
        }

        $params = 'src='.$this->path;
        if ( $w ) $params .= '&w='.$w;
        if ( $h ) $params .= '&h='.$h;
        if ( $crop ) $params .= '&zc='.$crop;
        $path = str_replace('\\', '/',phpThumbURL($params));
        $path = str_replace(fx::config()->DOCUMENT_ROOT, '', $path);

        return $path;
    }

    public function get_width() {
        $this->wh_init();
        return $this->width;
    }

    public function get_height() {
        $this->wh_init();
        return $this->height;
    }

    /**
     * @see http://phpthumb.sourceforge.net/demo/docs/phpthumb.readme.txt
     * @param type $params
     */
    public function thumb ( $params = array() ) {
        if (is_string($params) ) {
            parse_str($params,$params);
        }
        if ( !$this->path ) {
            return '';
        }

        if ( !$params['src'] ) {
            $params['src'] = $this->path;
        }
        foreach ( $params as $k => $v ) {
            $s .= $k.'='.$v.'&';
        }

        $path = phpThumbURL($s);
        $path = str_replace(fx::config()->DOCUMENT_ROOT, '', $path);

        return $path;
    }

    protected function wh_init() {
        if (!$this->wh_init && $this->path) {
            $info = @getimagesize(fx::config()->DOCUMENT_ROOT . $this->path);
            if ($info) {
                $this->width = $info[0];
                $this->height = $info[1];
            }
        }
        $this->wh_init = true;
    }

}

?>