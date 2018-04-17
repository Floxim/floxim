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

    public static function getLightness ($path)
    {
        static $stored = [];
        if (isset($stored[$path])) {
            return $stored[$path];
        }
        $cache_file = $path.'.lightness';
        if (file_exists($cache_file)) {
            $res = file_get_contents($cache_file);
            $stored[$path] = $res;
            return $res;
        }
        $lum = self::getAvgLuminance($path);
        $res = $lum > 180 ? 'light' : 'dark';
        $stored[$path] = $res;
        file_put_contents($cache_file, $res);
        return $res;
    }

    protected static function getAvgLuminance($filename, $num_samples=10) {
        $img = imagecreatefromjpeg($filename);

        $width = imagesx($img);
        $height = imagesy($img);

        $x_step = intval($width/$num_samples);
        $y_step = intval($height/$num_samples);

        $total_lum = 0;

        $sample_no = 1;

        for ($x=0; $x<$width; $x+=$x_step) {
            for ($y=0; $y<$height; $y+=$y_step) {

                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                // choose a simple luminance formula from here
                // http://stackoverflow.com/questions/596216/formula-to-determine-brightness-of-rgb-color
                $lum = ($r+$r+$b+$g+$g+$g)/6;

                $total_lum += $lum;

                // debugging code
                //           echo "$sample_no - XY: $x,$y = $r, $g, $b = $lum<br />";
                $sample_no++;
            }
        }

        // work out the average
        $avg_lum  = $total_lum/$sample_no;

        return $avg_lum;
    }

    protected function calculateSize($params, $source = null)
    {
        
        if (!$source) {
            $source = $this->info;
        }
        
        if (isset($params['crop-width']) && isset($params['crop-height'])) {
            $source = array_merge($source, array(
                'width' => $params['crop-width'],
                'height' => $params['crop-height']
            ));
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
    
    protected $custom_meta = null;
    
    public function getCustomMeta()
    {
        if (is_null($this->custom_meta)) {
            $path = $this->getCustomMetaPath();
            $res = array();
            if (file_exists($path)) {
                $data = json_decode(file_get_contents($path),1);
                if (is_array($data)) {
                    $res = $data;
                }
            }
            $this->custom_meta = $res;
        }
        return $this->custom_meta;
    }
    
    public function getCustomMetaPath()
    {
        $path = $this->source_path.'.meta';
        return $path;
    }
    
    public function setCustomMetaForFormat($meta, $format = null)
    {
        if (is_null($format)) {
            $format = $this->getConfigHash();
        }
        $c = $this->getCustomMeta();
        if (!isset($c['formats'])) {
            $c['formats'] = array();
        }
        $meta['timestamp'] = time();
        $c['formats'][$format] = $meta;
        $this->setCustomMeta($c);
    }
    
    public function getCustomMetaForFormat($format = null)
    {
        if (is_null($format)) {
            $format = $this->getConfigHash();
        }
        $meta = $this->getCustomMeta();
        if (!$meta || !isset($meta['formats']) || !isset($meta['formats'][$format])) {
            return array();
        }
        return (array) $meta['formats'][$format];
    }
    
    public function setCustomMeta($meta)
    {
        $this->custom_meta = $meta;
    }
    
    public function saveCustomMeta()
    {
        $path = $this->getCustomMetaPath();
        $data = json_encode($this->getCustomMeta());
        file_put_contents($path, $data);
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
        
        $format_meta = $this->getCustomMetaForFormat();
        if (isset($format_meta['crop'])) {
            foreach ($format_meta['crop'] as $crop_prop => $crop_value) {
                $st['crop-'.$crop_prop] = $crop_value;
            }
        }
        
        $calculated_size = $this->calculateSize($st);
        // the calculated sizes based on min-max, the size of the picture and a set of w-h
        $st = array_merge($st, $calculated_size);
        //fx::log($st, $calculated_size);
        
        $width = $this->info['width'];
        $height = $this->info['height'];
        $type = $this->info['imagetype'];

        $st['original-width'] = $width;
        $st['original-height'] = $height;
        
        //if (isset($st['crop']) && $st['crop']) {
        $st = $this->addCountedCrop($st, $width, $height);
        //}

        if (!$this->image) {
            $this->loadImage();
        }
        $source_i = $this->image;
        $target_i = imagecreatetruecolor($st['width'], $st['height']);
        
        if (isset($st['crop-color'])) {
            $color_parts = null;
            if (preg_match("~(\d+),\s*(\d+),\s*(\d+)~", $st['crop-color'], $color_parts)) {
                $color = imagecolorallocate($target_i, $color_parts[1], $color_parts[2], $color_parts[3]);
                imagefill($target_i, 0, 0, $color);
            }
        }
        
        

        if (($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF)) {
            $this->addTransparency($target_i, $source_i, $type);
        }
        
        $st['crop-x2'] = ( $st['original-width'] - $st['crop-width']) - $st['crop-x'];
        $st['crop-y2'] = ( $st['original-height'] - $st['crop-height']) - $st['crop-y'];
        
        $st['target-x'] = 0;
        $st['target-y'] = 0;
        
        if ($st['crop-x'] < 0 || $st['crop-x2'] < 0) {
            $ratio_x = $st['width'] / $st['crop-width'];
            $cx = $st['crop-x'];
            if ($cx < 0) {

                $st['target-x'] = $cx * -1 * $ratio_x;
                $st['crop-x'] = 0;

                $st['width'] += $cx * $ratio_x;
                $st['crop-width'] += $cx;
            }
            $cx2 = $st['crop-x2'];
            if ($cx2 < 0) {
                $st['width'] += $cx2 * $ratio_x;
                $st['crop-width'] += $cx2;
            }
        }
        if ($st['crop-y'] < 0 || $st['crop-y2'] < 0) {
            $ratio_y = $st['height'] / $st['crop-height'];
            $cy = $st['crop-y'];
            if ($cy < 0) {

                $st['target-y'] = $cy * -1 * $ratio_y;
                $st['crop-y'] = 0;

                $st['height'] += $cy * $ratio_y;
                $st['crop-height'] += $cy;
            }
            $cy2 = $st['crop-y2'];
            if ($cy2 < 0) {
                $st['height'] += $cy2 * $ratio_y;
                $st['crop-height'] += $cy2;
            }
        }
        

        $a = array(
            'target_image'  => $target_i, //resource $dst_image ,
            'source_image'  => $source_i, //resource $src_image ,
            't_x'      => $st['target-x'], //int $dst_x ,
            't_y'      => $st['target-y'], //int $dst_y ,
            's_x'        => $st['crop-x'], //int $src_x ,
            's_y'        => $st['crop-y'], //int $src_y ,
            't_w'  => $st['width'], //int $dst_w ,
            't_h' => $st['height'], //int $dst_h , 
            's_w'  => $st['crop-width'], //int $src_w ,
            's_h' => $st['crop-height'] //int $src_h 
        );
        
        call_user_func_array('imagecopyresampled', $a);
        imagedestroy($this->image);
        $this->image = $target_i;
        return $this;
    }
    
    protected function addCountedCrop($st, $width, $height)
    {
        $has_all = true;
        foreach ( explode(',', 'x,y,width,height') as $prop) {
            if (!isset($st['crop-'.$prop])) {
                $has_all = false;
                break;
            }
        }
        if ($has_all) {
            return $st;
        }
        // and padding for circumcision
        $crop_x = 0;
        $crop_y = 0;
        // width and the height of fragments
        $crop_width = $width;
        $crop_height = $height;

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
        return array_merge(
            array(
                'crop-x' => $crop_x,
                'crop-y' => $crop_y,
                'crop-width' => $crop_width,
                'crop-height' => $crop_height
            ),
            $st
        );
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
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        } else {
            fx::files()->mkdir(dirname($target_path));
        }
        if (!$this->image) {
            $this->loadImage();
        }
        
        if ($save_function === 'imagejpeg') {
            imageinterlace($this->image, true);
        } elseif ($save_function === 'imagepng' && preg_match("~no\.png~", $target_path)) {
            imagetruecolortopalette($this->image, true, 2);
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
    
    /**
     * Get config as a string that can be used to create thumb folder
     */
    public function getConfigHash()
    {
        $res = array();
        foreach ($this->config as $key => $value) {
            if ($value && !in_array($key, array('async', 'output')) && !preg_match("~^crop-~", $key)) {
                $res [] = $key . '-' . $value;
            }
        }
        sort($res);
        $res = join('.', $res);
        return $res;
    }
    
    public static function getThumbDir($rel_path, $folder_name)
    {
        $trimmed = fx::path()->trimHost($rel_path);
        $rel_path = fx::path()->http($trimmed, true);
        //fx::debug($trimmed, $rel_path);
        if ( ($thumb_path_closure = fx::config('thumbs.path_closure'))) {
            $full_path = call_user_func($thumb_path_closure, $folder_name, $rel_path);
        } else {
            $full_path = fx::path('@thumbs/' . $folder_name . '/' . $rel_path);
        }
        return $full_path;
    }
            

    public function getResultPath()
    {
    	$rel_path = $this->source_http_path;
        
        
        $folder_name = $this->getConfigHash();
        
        $full_path = self::getThumbDir($rel_path, $folder_name);
        
        //fx::debug($this->source_http_path, $rel_path, $folder_name, $full_path);
        
        $is_forced = $this->config['async'] === 'force';
        $is_async = $this->config['async'] === true;
        
        if (!file_exists($full_path) || $is_forced) {
            if ($is_async) {
                $target_dir = dirname($full_path);
                if (!file_exists($target_dir)) {
                    fx::files()->mkdir($target_dir);
                }
            } else {
            	$this->process($full_path);
            }
        }
        
        $path = fx::path()->http($full_path);
        
        // !!!SLOW!!!
        $meta = $this->getCustomMetaForFormat();
        if (isset($meta['timestamp'])) {
            $path .= '?t='.$meta['timestamp'];
        }
        
        return $path;
    }

    public static function findThumbs($source_path)
    {
        $res = array();
        $rel_path = fx::path()->http($source_path);
        
        $dir = self::getThumbDir($rel_path, '*');
        
        $found = glob($dir);
        $res = [];
        
        foreach ($found as $item) {
            if (is_file($item)) {
                $res []= $item;
            }
        }
        
        return $res;
    }

    public static function readConfig($config)
    {
        if (is_array($config)) {
            if (isset($config['crop']) && is_array($config['crop'])) {
                foreach ($config['crop'] as $cp => $cv) {
                    $config['crop-'.$cp] = $cv;
                }
                $config['crop'] = true;
            }
            return $config;
        }
        $config = preg_replace_callback(
            "~(\d+)px~", 
            function($m) {
                return $m[1];
            },
            $config
        );
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
            $params['async'] = $params['async'] === 'false' ? false : ($params['async'] === 'force'? 'force' : true);
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
    
    public static function readConfigFromPathString($config)
    {
        $config = str_replace(".", ",", $config);
        $config  = str_replace("-", ':', $config);
        $config = preg_replace("~(min|max):(width|height)~", '$1-$2', $config);
        return $config;
    }
}