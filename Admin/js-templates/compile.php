<?
class JSTX {
	protected $options = array();
	public function __construct($options = array()) {
            $this->options = array_merge(
                array(
                    'left_bracket' => '<'.'?',
                    'right_bracket' => '?'.'>',
                    'context_name' => '_c',
                    'use_with' => true,
                    'js_xjst_var' => '$t',
                    'tpl_file_regexp' => "~^[^_].+?\.jst~"
                    //'tpl_file_regexp' => "~^form\.jst~"
                ), $options
            );
	}
	
	public function opt($name) {
            return isset($this->options[$name]) ? $this->options[$name] : false;
	}
	
	public function compileTemplate($tpl) {
            $res = "var p=[],print=function(){p.push.apply(p,arguments);};";

            // Make the data available locally using the with(){}
            $res .= ($this->opt('use_with') ? "with(_c){" : "")." p.push('";

            // Turn the template in pure JavaScript

            $tpl = preg_replace("~[\r\t\n]~", " ", $tpl);
            $tpl = preg_replace("~\/\*.+?\*\/~", '', $tpl);

            $tpl = explode($this->opt('left_bracket'), $tpl);
            $tpl = join("\t", $tpl);
            $tpl = preg_replace("~\t=(.*?)".preg_quote($this->opt('right_bracket'))."~", "',$1,'", $tpl);
            $tpl = explode("\t", $tpl);
            $tpl = join("');", $tpl);
            $tpl = explode($this->opt('right_bracket'), $tpl);
            $tpl = join("\np.push('", $tpl);
            $tpl = explode("\r", $tpl);
            $tpl = join("\\'", $tpl);

            $res .= $tpl;

            $res .= "');".($this->opt('use_with') ? "}" : "")."return p.join('');";
            return $res;
	}
	
	protected function _groupSplit($string, $regexp) {
            $string = trim($string);
            $parts = preg_split($regexp, $string, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            $res = array();
            $c_key = false;
            foreach ($parts as $k => $p) {
                $p = trim($p);
                if ($k % 2 == 0) {
                    $c_key = $p;
                } else {
                    if (!isset($res[$c_key])) {
                        $res[$c_key] = $p;
                    } elseif (is_string($res[$c_key])) {
                        $res[$c_key] = array($res[$c_key]);
                        $res[$c_key] []= $p;
                    } else {
                        $res[$c_key] []= $p;
                    }
                }
            }
            return $res;
	}
	
	public function parseFile($file) {
            $tpls = file_get_contents($file);
            $tpls = trim($tpls);
            // remove a single-line comments
            $tpls = preg_replace_callback(
                    "~//.*?([\n\r])~", function($matches) {
                            return $matches[1];
                    }, 
                    $tpls
            );

            $templates = array();

            $delegated_jquery = array();

            foreach ($this->_groupSplit($tpls, "~<!--\[(.+?)\]-->~") as $k => $tpl) {
                if (!is_array($tpl)) {
                    $tpl = array($tpl);
                }

                if (preg_match("~\?~", $k)) {
                    $key_parts = explode("?", $k, 2);
                    $k = trim($key_parts[0]);
                    $tpl[0].= "\n<!--test-->\n".trim($key_parts[1]);
                }
			
                foreach ($tpl as $tpl_num => $tpl_data) {
                    $tpl_parts = $this->_groupSplit(
                        "<!--template-->".$tpl_data, "~<!--(.+?)-->~"
                    );
                    foreach ($tpl_parts as $part_key => $part_data) {
                        if (preg_match("~^jquery~", $part_key)) {
                            //$part_data = preg_replace("~\s+~", ' ', preg_replace("~[\r\n]~", ' ', $part_data));
                            if (preg_match("~^jquery\:(.+)~", $part_key, $jquery_target_template)) {
                                unset($tpl_parts[$part_key]);
                                if (!isset($delegated_jquery[$jquery_target_template[1]])) {
                                    $delegated_jquery[$jquery_target_template[1]] = '';
                                }
                                $delegated_jquery[$jquery_target_template[1]] .= $part_data;
                            } else {
                                $tpl_parts[$part_key] = $part_data;
                            }
                        }
                    }
                    $tpl[$tpl_num] = $tpl_parts;
                }
                if (!isset($templates[$k])) {
                    $templates[$k] = $tpl;
                } else {
                    $templates[$k] = array_merge($templates[$k], $tpl);
                }
            }
        
            foreach ($delegated_jquery as $tpl_name => $jquery_code) {
                if (!isset($templates[$tpl_name])) {
                        continue;
                }
                foreach ($templates[$tpl_name] as $tpl_key => &$tpl_data) {
                    if (!isset($tpl_data['jquery'])) {
                            $tpl_data['jquery'] = '';
                    }
                    $tpl_data['jquery'] .= $jquery_code;
                }
            }
		
            ksort($templates);
		
            echo "(function() {\nvar f;\n";
            foreach ($templates as $tpl_name => $tpl_vars) {
                echo "// ".$tpl_name."\n";
                foreach ($tpl_vars as $tpl) {
                    echo "f = function(_c, _o) {".$this->compileTemplate($tpl['template'])."};\n";
                    if (isset($tpl['jquery'])) {
                        echo "f.jquery = function(html, _c, _o) {".$tpl['jquery']."};\n";
                    }
                    if (isset($tpl['test'])) {
                        echo "f._test = function(_c, _o) {return ".$tpl['test']."};\n";
                    }
                    if (isset($tpl['priority'])) {
                        echo "f._priority = ".$tpl['priority'].";\n";
                    }
                    echo "\$t.add('".$tpl_name."', f);\n\n";
                }
            }
            echo "})();\n";
	}
	
	public function parseDir($dir = '.', $regexp = false) {
            echo " (function(\$) {\n";
            if (!$regexp) {
                $regexp = $this->opt('tpl_file_regexp');
            }
            if ( ($handle = opendir($dir)) ) {
                $path = realpath($dir);

                while (false !== ($entry = readdir($handle))) {
                    if (preg_match($regexp, $entry)) {
                        $this->parseFile($path.DIRECTORY_SEPARATOR.$entry);
                    }
                }

                closedir($handle);
            }
            echo "\n})(\$fxj);";
	}
}

header("Content-type: text/javascript; charset=utf-8");
$JSTX = new JSTX(array('use_with' => false)); 
$JSTX->parseDir();