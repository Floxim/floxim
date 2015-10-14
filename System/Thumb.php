<?php

namespace Floxim\Floxim\System;

class Thumb
{
    protected $source_path = null;
    protected $info = array();
    protected $image = null;
    protected $config = null;
    
    protected $source_http_path = null;

    public function __construct($source_http_path, $config = '')
    {
        if (empty($source_http_path)) {
            throw new \Exception('Empty path');
        }
        $this->config = $this->readConfig($config);
        
        if (!isset($this->config['async'])) {
            $this->config['async'] = true;
        }
        

        $source_path = fx::path()->abs($source_http_path);
        if (!file_exists($source_path) || !is_file($source_path)) {
            throw new \Exception('File not found: ' . $source_path);
        }
        
        $this->source_http_path = $source_http_path;
        
        $source_path = realpath($source_path);

        $this->source_path = $source_path;
        $info = getimagesize($source_path);
        $info['width'] = $info[0];
        $info['height'] = $info[1];
        $info['imagetype'] = $info[2];
        unset($info[0]);
        unset($info[1]);
        unset($info[2]);
        unset($info[3]);
        $this->info = $info;
        if (!isset(self::$_types[$info['imagetype']])) {
            // incorrect/unknown type pictures
            throw new \Exception('Wrong image type');
        }
        $max_filesize = fx::config('image.max_filesize');
        if ($max_filesize && filesize($source_path) > $max_filesize) {
            throw new \Exception('Image is too big (limit is '.$max_filesize.')');
        }
        $this->info += self::$_types[$info['imagetype']];
    }

    public function getInfo($key = null)
    {
        switch (func_num_args()) {
            case 0:
            default:
                return $this->info;
            case 1:
                return isset($this->info[$key]) ? $this->info[$key] : null;
        }
    }

    protected function calculateSize($params, $source = null)
    {
        if (!$source) {
            $source = $this->info;
        }
        $ratio = $source['width'] / $source['height'];

        $w = $params['width'];
        $h = $params['height'];
        $miw = $params['min-width'];
        $maw = $params['max-width'];
        $mih = $params['min-height'];
        $mah = $params['max-height'];

        // begin: width:200 => min-width:200, max-width:200
        if ($w) {
            if (!$miw) {
                $miw = $w;
            }
            if (!$maw) {
                $maw = $w;
            }
        }

        // similar height
        if ($h) {
            if (!$mah) {
                $mah = $h;
            }
            if (!$mih) {
                $mih = $h;
            }
        }

        // set only the width
        if ($w && !$h) {
            $h = $w * 1 / $ratio;
        } // was asked only height
        elseif ($h && !$w) {
            $w = $h * $ratio;
        } // not asked anything
        elseif (!$h && !$w) {
            $h = $source['height'];
            $w = $source['width'];
        }

        // add min/max

        if ($miw === false) {
            $miw = 0;
        }
        if ($mih === false) {
            $mih = 0;
        }
        if (!$maw && !$mah) {
            $maw = $w;
            $mah = $h;
        } elseif (!$mah && $maw) {
            $mah = $maw * 1 / $ratio;
        } elseif (!$maw && $mah) {
            $maw = $mah * $ratio;
        }
        /*
        echo '<table>';
        foreach (explode(',', 'w,h,miw,mih,maw,mah') as $vn) {
        echo "<tr><td>$".$vn."</td><td>".$$vn."</td></tr>";
        }
        echo "</table>";
        */

        // now, believe on the name plate http://www.w3.org/TR/CSS21/visudet.html#min-max-widths

        /**
         *
         * Constraint Violation     Resolved Width     Resolved Height
         * #0    none     w     h
         * #1    w > max-width     max-width     max(max-width * h/w, min-height)
         * #2    w < min-width     min-width     min(min-width * h/w, max-height)
         * #3    h > max-height     max(max-height * w/h, min-width)     max-height
         * #4    h < min-height     min(min-height * w/h, max-width)     min-height
         * #5    (w > max-width) and (h > max-height), where (max-width/w <= max-height/h)    max-width     max(min-height, max-width * h/w)
         * #6    (w > max-width) and (h > max-height), where (max-width/w > max-height/h)    max(min-width, max-height * w/h)     max-height
         * #7    (w < min-width) and (h < min-height), where (min-width/w <= min-height/h)    min(max-width, min-height * w/h)     min-height
         * #8    (w < min-width) and (h < min-height), where (min-width/w > min-height/h)    min-width     min(max-height, min-width * h/w)
         * #9    (w < min-width) and (h > max-height)     min-width     max-height
         * #10    (w > max-width) and (h < min-height)     max-width     min-height

         */


        // is wider than it should
        if ($w > $maw) {
            // and above
            if ($h > $mah) {
                if ($maw / $w <= $mah / $h) {
                    // #5
                    $h = max($mih, ($maw * $h / $w));
                    $w = $maw;
                } else {
                    // #6
                    $w = max($miw, $mah * $w / $h);
                    $h = $mah;
                }
            } // and below
            elseif ($h < $mih) {
                // #10
                $w = $maw;
                $h = $mih;
            } // and norms. width
            else {
                // #1
                $h = max($maw * $h / $w, $mih);
                $w = $maw;
            }
        } // already than I should
        elseif ($w < $miw) {
            // and below
            if ($h < $mih) {
                if ($miw / $w <= $mih / $h) {
                    // #7
                    $w = min($maw, $mih * $w / $h);
                    $h = $mih;
                } else {
                    // #8
                    $h = min($mah, $miw * $h / $w);
                    $w = $miw;
                }
            } // and above
            elseif ($h > $mah) {
                // #9
                $w = $miw;
                $h = $mah;
            } // and norms. height
            else {
                // #2
                $h = min($miw * $h / $w, $mah);
                $w = $miw;
            }
        }
        // width OK. problems with height

        // above
        elseif ($h > $mah) {
            // #3
            $w = max($mah * $w / $h, $miw);
            $h = $mah;
        } // below
        elseif ($h < $mih) {
            // #4
            $w = min($mih * $w / $h, $maw);
            $h = $mih;
        }

        $res = array(
            'width'  => round($w),
            'height' => round($h)
        );
        return $res;
    }

    public function resize($params = null)
    {
        if (isset($params)) {
            $params = $this->readConfig($params);
        } else {
            $params = $this->config;
        }
        $st = array_merge(array(
            'width'      => false,
            'height'     => false,
            'min-width'  => false,
            'min-height' => false,
            'max-width'  => false,
            'max-height' => false,
            'crop'       => true
        ), $params);

        // the calculated sizes based on min-max, the size of the picture and a set of w-h
        $st = array_merge($st, $this->calculateSize($st));

        $width = $this->info['width'];
        $height = $this->info['height'];
        $type = $this->info['imagetype'];

        // determine the price. zoom
        $scale = 1;
        // and padding for circumcision
        $crop_x = 0;
        $crop_y = 0;
        // width and the height of fragments
        $crop_width = $width;
        $crop_height = $height;


        if (isset($st['crop']) && $st['crop']) {
            $scale_x = $st['width'] / $width;
            $scale_y = $st['height'] / $height;
            $scale = max($scale_x, $scale_y);
            if (isset($st['crop_offset']) && in_array($st['crop_offset'], array(
                    'top',
                    'middle',
                    'bottom'
                ))
            ) {
                $crop_offset = $st['crop_offset'];
            } else {
                $crop_offset = 'middle';
            }
            if ($scale == $scale_x) {
                // the cropped height
                $crop_height = $st['height'] / $scale;
                switch ($crop_offset) {
                    case 'top':
                        $crop_y = 0;
                        break;
                    case 'middle':
                        $crop_y = round(($height - $crop_height) / 2);
                        break;
                    case 'bottom':
                        $crop_y = $height - $crop_height;
                        break;
                }
            } else {
                // the trimmed width
                $crop_width = $st['width'] / $scale;
                switch ($crop_offset) {
                    case 'top':
                        $crop_x = 0;
                        break;
                    case 'middle':
                        $crop_x = round(($width - $crop_width) / 2);
                        break;
                    case 'bottom':
                        $crop_x = $width - $crop_width;
                        break;
                }
            }
        }

        if (!$this->image) {
            $this->loadImage();
        }
        $source_i = $this->image;
        $target_i = imagecreatetruecolor($st['width'], $st['height']);


        if (($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF)) {
            $this->addTransparency($target_i, $source_i, $type);
        }

        $icr_args = array(
            'target_image'  => $target_i, //resource $dst_image ,
            'source_image'  => $source_i, //resource $src_image ,
            'target_x'      => 0, //int $dst_x ,
            'target_y'      => 0, //int $dst_y ,
            'crop_x'        => $crop_x, //int $src_x ,
            'crop_y'        => $crop_y, //int $src_y ,
            'target_width'  => $st['width'], //int $dst_w ,
            'target_height' => $st['height'], //int $dst_h , 
            'source_width'  => $crop_width, //int $src_w ,
            'source_height' => $crop_height //int $src_h 
        );
        call_user_func_array('imagecopyresampled', $icr_args);
        imagedestroy($this->image);
        $this->image = $target_i;
        return $this;
    }

    protected function addTransparency($dst, $src, $type)
    {
        if ($type == IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
            imagefill($dst, 0, 0, $transparent);
            return;
        }
        $t_index = imagecolortransparent($src);
        $t_color = array(
            'red'   => 255,
            'green' => 0,
            'blue'  => 0
        );

        if ($t_index >= 0) {
            $t_color = imagecolorsforindex($src, $t_index);
        }
        $t_index = imagecolorallocate($dst, $t_color['red'], $t_color['green'], $t_color['blue']);
        imagefill($dst, 0, 0, $t_index);
        imagecolortransparent($dst, $t_index);
    }

    public function save($target_path = false, $params = null)
    {
        if (isset($params)) {
            $params = $this->readConfig($params);
        } else {
            $params = $this->config;
        }
        $params = array_merge(array('quality' => 90), $params);
        
        $type_props = null;
        if (isset($params['type'])) {
            $type_props = self::imageTypeByPath('pic.' . $params['type']);
        } elseif ($target_path) {
            $type_props = self::imageTypeByPath($target_path);
        }

        if ($type_props && $type_props['type'] !== $this->info['imagetype']) {
            $image_type = $type_props['type'];
            $save_function = $type_props['save_func'];
        } else {
            $image_type = $this->info['imagetype'];
            $save_function = $this->info['save_func'];
        }

        $quality = $params['quality'];

        if ($image_type == IMAGETYPE_PNG) {
            //$quality = 10 - round($quality / 10);
            // this is not 'quality', but a compression level, the more is value the less is filesize
            // png compression is always lossles
            $quality = 9;
        }
        $output = isset($this->config['output']);
        if ($target_path === false) {
            $target_path = $this->source_path;
        } elseif ($target_path === null || $output) {
            header("Content-type: " . $this->info['mime']);
            ob_end_clean();
        } else {
            fx::files()->mkdir(dirname($target_path));
        }
        if (!$this->image) {
            $this->loadImage();
        }
        
        if ($save_function === 'imagejpeg') {
            imageinterlace($this->image, true);
        }
        
        // Save to file
        call_user_func($save_function, $this->image, $target_path, $quality);
        
        // Output to browser
        if ($output && $target_path !== null){
            call_user_func($save_function, $this->image, null, $quality);
        }
    }

    protected static $_types = array(
        IMAGETYPE_GIF  => array(
            'ext'         => 'gif',
            'create_func' => 'imagecreatefromgif',
            'save_func'   => 'imagegif'
        ),
        IMAGETYPE_JPEG => array(
            'ext'         => 'jpg',
            'create_func' => 'imagecreatefromjpeg',
            'save_func'   => 'imagejpeg'
        ),
        IMAGETYPE_PNG  => array(
            'ext'         => 'png',
            'create_func' => 'imagecreatefrompng',
            'save_func'   => 'imagepng'
        )
    );

    public static function imageTypeByPath($path)
    {
        $name = fx::path()->fileName($path);
        $ext = strtolower(preg_replace("~^.+\.~", '', $name));
        foreach (self::$_types as $type => $props) {
            if ($props['ext'] == $ext) {
                return $props + array('type' => $type);
            }
        }
    }

    protected function loadImage()
    {
        $this->image = call_user_func($this->info['create_func'], $this->source_path);
    }


    public function process($full_path = false)
    {
        // buffer errors
        ob_start();
        $this->loadImage();
        $this->resize();
        $this->save($full_path);
        $this->image = null;
        ob_end_clean();
    }

    public function getResultPath()
    {
    	$rel_path = $this->source_http_path;

        $folder_name = array();
        foreach ($this->config as $key => $value) {
            if ($value && !in_array($key, array('async', 'output'))) {
                $folder_name [] = $key . '-' . $value;
            }
        }
        $folder_name = join('.', $folder_name);
        
        $rel_path = $folder_name . '/' . $rel_path;
        $full_path = fx::path('@thumbs/' . $rel_path);
        if (!file_exists($full_path)) {
            if ($this->config['async']) {
                $target_dir = dirname($full_path);
                if (!file_exists($target_dir)) {
                    fx::files()->mkdir($target_dir);
                }
            } else {
            	$this->process($full_path);
            }
        }
        $path = fx::path()->http($full_path);
        return $path;
    }

    public static function findThumbs($source_path)
    {
        $res = array();
        $rel_path = fx::path()->http($source_path);
        $dir = glob(fx::path('@thumbs') . '/*');


        if (!$dir) {
            return $res;
        }
        foreach ($dir as $sub) {
            if (is_dir($sub)) {
                $check_path = fx::path()->abs($sub . $rel_path);
                if (fx::path()->isFile($check_path)) {
                    $res [] = $check_path;
                }
            }
        }
        return $res;
    }

    public static function readConfig($config)
    {
        if (is_array($config)) {
            return $config;
        }
        $prop_map = array(
            'w'    => 'width',
            'h'    => 'height',
            'minw' => 'min-width',
            'maxw' => 'max-width',
            'minh' => 'min-height',
            'maxh' => 'max-height'
        );
        $config = trim($config);

        $config = preg_replace_callback(
            '~(\d+)\s*[\*x]\s*(\d+)~',
            function ($matches) {
                return 'width:' . $matches[1] . ',height:' . $matches[2];
            },
            $config
        );

        $config = str_replace(";", ',', $config);
        if (empty($config)) {
            return array();
        }
        $config = explode(",", $config);
        $params = array();
        foreach ($config as $props) {
            //list($prop, $value) = explode(":", $props);
            $prop_parts = explode(":", $props);
            $prop = $prop_parts[0];
            $value = isset($prop_parts[1]) ? $prop_parts[1] : null;
            if (is_numeric($value)) {
                $value = floor($value);
            }
            $prop = trim($prop);
            if (isset($prop_map[$prop])) {
                $prop = $prop_map[$prop];
            }
            if ($prop === 'crop-offset') {
                $prop = 'crop_offset';
            }
            $params[$prop] = $value;
        }
        if (isset($params['async'])) {
            $params['async'] = $params['async'] === 'false' ? false : true;
        }
        return $params;
    }

    public function setConfig($key, $value)
    {
        if (is_array($key)) {
            foreach ($key as $rk => $rv) {
                $this->setConfig($rk, $rv);
            }
            return $this;
        }
        $this->config[$key] = $value;
        return $this;
    }
    
    public function readConfigFromPathString($config)
    {
        $config = str_replace(".", ",", $config);
        $config  = str_replace("-", ':', $config);
        $config = preg_replace("~(min|max):(width|height)~", '$1-$2', $config);
        return $config;
    }
}