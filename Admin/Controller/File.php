<?php

namespace Floxim\Floxim\Admin\Controller;

use Floxim\Floxim\System\Fx as fx;

class File extends Admin
{

    public function uploadSave($input)
    {
        $path = 'upload';
        $result = fx::files()->saveFile($input['file'], $path);
        if (!$result) {
            $result = array(
               'error_message' => 'Can not load this file' 
            );
            fx::http()->status(500);
            fx::log('failed loading file', $input);
        }
        if (isset($input['format']) && !empty($input['format']) && isset($result['path'])) {
            $format = trim($input['format'], "'");
            $result['formatted_value'] = fx::image($result['path'], $format);
        }
        return $result;
    }
    
    public function getImageMeta($input)
    {
        $file = $input['file'];
        $format = $input['format'];
        $res = array();
        try {
            $thumb = new \Floxim\Floxim\System\Thumb($file, $format);
            $res['current'] = $thumb->getCustomMetaForFormat();
        } catch (Exception $ex) {
            // fx::log('failed saving meta', $input);
        }
        $res['format'] = \Floxim\Floxim\System\Thumb::readConfig($format);
        $res['formatted_value'] = fx::image($file, $format);
        return $res;
    }
    
    public function saveImageMeta($input)
    {
        $file = $input['file'];
        if ( !$file || !fx::files()->isImage(fx::path($file)) ) {
            return;
        }
        $format = $input['format'];
        $crop = json_decode($input['crop'], true);
        try {
            // save crop info for the certain format
            if ($format) {
                $thumb = new \Floxim\Floxim\System\Thumb($file, $format);
                $thumb->setCustomMetaForFormat( array('crop' => $crop) );
                $thumb->saveCustomMeta();
                $result_path = $thumb->getResultPath();
                $full_path = fx::path($file);

                fx::files()->rm( fx::path($result_path) );

                $res = fx::files()->getInfo($full_path);

                $res['formatted_value'] = $result_path;
            } 
            // duplicate and crop the original image
            else {
                $file_copy = fx::files()->duplicate($file);
                $resize_config = array();
                foreach ($crop as $crop_prop => $crop_value) {
                    $resize_config['crop-'.$crop_prop] = $crop_value;
                }
                $thumb = new \Floxim\Floxim\System\Thumb($file_copy, $resize_config);
                $thumb->process($file_copy);
                $res = fx::files()->getInfo($file_copy);
            }
            $res['action'] = 'save_image_meta';
            return $res;
        } catch (Exception $ex) {
            fx::log('failed saving meta', $input);
        }
    }
}