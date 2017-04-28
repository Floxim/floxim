<?php

namespace Floxim\Floxim\Asset;

use Floxim\Floxim\System\Fx as fx;

class JsBundle extends Bundle {
    protected $type = 'js';
    protected $extension = 'js';
    
    public function getBundleContent() {
        $files = $this->getUniqueFiles();
        $res = '';
        $jst = [];
        foreach ($files as $f) {
            if (preg_match("~\.jst$~", $f)) {
                $jst []= $f;
                continue;
            }
            if (file_exists($f)) {
                $res .= "// ".$f."\n\n;";
                $res .= file_get_contents($f).";\n\n";
            }
        }
        if (count($jst) > 0) {
            $jstc = new \Floxim\Floxim\Asset\Jstx();
            $res .= $jstc->compile($jst);
        }
        
        if (fx::config('dev.uglifyjs')) {
            $tmp_file = $this->getFilePath().'.tmp';

            file_put_contents($tmp_file, $res);

            $cmd = 'node /usr/local/bin/uglifyjs '.$tmp_file.' --mangle --compress';

            $res = shell_exec($cmd);

            unlink($tmp_file);
        }
        
        return $res;
    }
}