<?php

namespace Floxim\Floxim\System;

class Debug
{
    protected $dir = null;
    protected $id = null;
    protected $file = null;
    protected $start_time = null;
    protected $last_time = null;
    protected $count_entries = 0;
    protected $separator = "\n=============\n";
    protected $max_log_files = 30;
    protected $disabled = false;

    public function __construct()
    {
        $this->id = md5(microtime() . rand(0, 10000));
        $this->start_time = defined('FX_START_MICROTIME') ? FX_START_MICROTIME : microtime(true);
        $this->last_time = $this->start_time;
    }

    public function disable()
    {
        $this->disabled = true;
        if (!is_null($this->file)) {
            fclose($this->file);
            fx::files()->rm($this->getFileName());
            $this->file = null;
        }
    }
    
    public function setDir($dir)
    {
        $this->dir = $dir;
    }

    protected function getDir()
    {
        return $this->dir;
    }

    protected function getFileName($log_id = null)
    {
        if (is_null($log_id)) {
            $log_id = $this->id;
        }
        return $this->getDir() . '/log_' . $log_id . ".html";
    }

    protected function getIndexFileName()
    {
        return $this->getDir() . '/index.txt';
    }

    /**
     * Open log file and init index
     */
    public function start()
    {
        $file_name = $this->getFileName();
        $this->file = fx::files()->open($file_name, 'w');
        register_shutdown_function(array($this, 'stopLog'));
    }
    
    protected $metalog_handler = null;
    protected function metaLog($msg) {
        if (is_null($this->metalog_handler)) {
            $this->metalog_handler = fopen(DOCUMENT_ROOT.'/floxim_files/log/metalog.txt', 'w');
        }
        fputs($this->metalog_handler, date('d.m.Y, H:i:s')."\n".$msg."\n\n---\n\n");
    }
    
    protected $stop_handlers = array();
    
    public function onStop($callback) 
    {
        $this->stop_handlers[]= $callback;
    }

    public function stopLog()
    {
        if (is_null($this->file)) {
            return;
        }
        foreach ($this->stop_handlers as $callback) {
            call_user_func($callback);
        }
        fclose($this->file);
        //$this->file = null;
        $this->writeIndex();
    }

    /**
     * Get short backtrace output.
     * Useful together with logger, e.g. fx::log($some_data, fx_debug::backtrace(6));
     * @param int|bool $level how many trace levels to display or FALSE to show all
     * @return string Trace, each level separated by newline
     */
    public static function backtrace($level = false)
    {
        $trace = debug_backtrace();
        if ($level !== false) {
            $trace = array_slice($trace, 0, $level);
        }
        $res = array();
        foreach ($trace as $l) {
            $str = '';
            if ($l['file']) {
                $file = fx::path()->http($l['file']);
                $str .= $file . '@' . $l['line'];
            }
            if ($l['class']) {
                $str .= ' ' . $l['class'] . $l['type'];
            }
            if ($l['function']) {
                $str .= $l['function'];
            }
            $res [] = $str;
        }
        return join("\n", $res);
    }

    /**
     * Drop the first (oldest) log file
     * Used when there are too much files (more than $this->max_log_files)
     */
    protected function dropFirst()
    {
        $index_file = $this->getIndexFileName();
        $ifh = fopen($index_file, "c+");
        $is_first = true;
        while (($line = fgets($ifh)) !== false) {
            if ($is_first) {
                $item = unserialize(trim($line));
                $first_file = $this->getFileName($item['id']);
                fx::files()->rm($first_file);
                $write_offset = ftell($ifh);
                $is_first = false;
            }
            if (isset($write_position)) {
                $read_position = ftell($ifh);
                fseek($ifh, $write_position);
                fputs($ifh, $line);
                fseek($ifh, $read_position);
            }
            $write_position = ftell($ifh) - $write_offset;
        }
        ftruncate($ifh, $write_position);
        fclose($ifh);
    }

    /**
     * Put log data into index
     */
    protected function writeIndex()
    {
        if ($this->disabled) {
            return;
        }
        $c_count = $this->getCount();
        if ($c_count >= $this->max_log_files) {
            $this->dropFirst();
        } else {
            $c_count++;
        }
        $fh_index = fx::files()->open($this->getIndexFileName(), 'a');
        $log_header = array(
            'id'            => $this->id,
            'start'         => $this->start_time,
            'host'          => $_SERVER['HTTP_HOST'],
            'url'           => $_SERVER['REQUEST_URI'],
            'method'        => $_SERVER['REQUEST_METHOD'],
            'time'          => microtime(true) - $this->start_time,
            'count_entries' => $this->count_entries
        );
        if ($log_header['method'] === 'POST') {
            $post_params = array();
            foreach ($_POST as $post_key => $post_value) {
                $post_params []= $post_key.':'. (is_scalar($post_value) ? mb_substr($post_value, 0, 20) : '[...]');
            }
            $log_header['method'] .= ' '.join(", ", $post_params);
            $log_header['method'] = preg_replace("~[\n\r]~", ' ', $log_header['method']);
        }
        $serialized_header = serialize($log_header);
        fputs($fh_index,  $serialized_header. "\n");
        fclose($fh_index);
        $this->setCount($c_count);
    }

    protected function getCounterFileName()
    {
        return $this->getDir() . '/counter.txt';
    }

    protected function getCount()
    {
        $counter_file = $this->getCounterFileName();
        if (!file_exists($counter_file)) {
            $c_count = 0;
        } else {
            $c_count = (int)file_get_contents($counter_file);
        }
        return $c_count;
    }

    protected function setCount($count)
    {
        $count = (int)$count;
        if ($count < 0) {
            $count = 0;
        }
        file_put_contents($this->getCounterFileName(), $count);
    }

    public function dropLog($log_id)
    {
        $f = $this->getFileName($log_id);
        $index = $this->getIndex();
        if (file_exists($f)) {
            fx::files()->rm($f);
            $ifh = fx::files()->open($this->getIndexFileName(), 'w');
            foreach ($index as $item) {
                if ($item['id'] != $log_id) {
                    fputs($ifh, serialize($item) . "\n");
                }
            }
            fclose($ifh);
            $this->setCount($this->getCount() - 1);
        }
    }

    public function dropAll()
    {
        $log_files = glob($this->getDir() . '/log*');
        $ifh = fx::files()->open($this->getIndexFileName(), 'w');
        fputs($ifh, '');
        fclose($ifh);
        $this->setCount(0);
        if (!$log_files) {
            return;
        }
        $own_file = fx::path()->abs($this->getFileName());
        foreach ($log_files as $lf) {
            if (fx::path()->abs($lf) != $own_file) {
                fx::files()->rm($lf);
            }
        }
    }

    public function getIndex($id = null)
    {
        $file = $this->getIndexFileName();
        if (!file_exists($file)) {
            return array();
        }
        $index = trim(file_get_contents($file));
        
        if (strlen($index) == 0) {
            return array();
        }
        $items = explode("\n", $index);
        $res = array();
        foreach ($items as $item) {
            $item = unserialize($item);
            if (!is_null($id)) {
                if ($item['id'] == $id) {
                    return $item;
                }
                continue;
            }
            if (is_array($item)) {
                $res[] = $item;
            }
        }
        if (!is_null($id)) {
            return false;
        }
        usort($res, function($a, $b) {
            $diff = $a['start'] - $b['start'];
            return $diff > 0 ? 1 : -1;
        });
            
            
        $res = array_reverse($res);
        return $res;
    }
    
    /**
     * Get list of log files that aren't listed in the index
     * @param array $found
     */
    public function getLost($found = array()) 
    {
        $found = fx::collection($found)->getValues('id', 'id');
        $files = glob($this->getDir()."/log_*");
        if (!$files) {
            return array();
        }
        $res = array();
        foreach ($files as $file) {
            preg_match("~log_([^\.]+)~", $file, $id);
            $id = $id[1];
            if (!isset($found[$id])) {
                $res []= array(
                    'id' => $id,
                    'start' => filemtime($file),
                    'host' => '???',
                    'count_entries' => '???',
                    'method' => '???'
                );
            }
        }
        return $res;
    }

    public function showItem($item_id)
    {
        if (!$item_id) {
            return '';
        }
        $file = $this->getDir() . '/log_' . $item_id . '.html';
        if (!file_exists($file)) {
            return '';
        }
        $fh = fopen($file, 'r');
        if ($fh === false) {
            return '';
        }
        ob_start();

        $entry = '';
        while (!feof($fh)) {
            $s = fgets($fh);
            if (trim($s) == trim($this->separator)) {
                $this->printEntry(unserialize($entry));
                $entry = '';
            } else {
                $entry .= $s;
            }
        }
        $this->printEntry(unserialize($entry));
        fclose($fh);
        $res = ob_get_clean();
        return $res;
    }
    
    protected function fputs($h, $data)
    {
        if (is_resource($h)) {
            fputs($h, $data);
            return false;
        }
        return false;
    }

    /**
     * Put args into log
     */
    public function log()
    {
        if (!fx::config('dev.on') && (!defined("FX_ALLOW_DEBUG") || !FX_ALLOW_DEBUG)) {
            return;
        }
        if ($this->disabled) {
            return;
        }
        if (is_null($this->file)) {
            $this->start();
        } else {
            $this->fputs($this->file, $this->separator);
        }
        $this->fputs(
            $this->file,
            serialize(
                call_user_func_array(
                    array($this, 'entry'),
                    func_get_args()
                )
            )
        );
        $this->count_entries++;
    }

    /**
     * Print args to the output
     */
    public function debug()
    {
        $e = call_user_func_array(array($this, 'entry'), func_get_args());
        $this->printEntry($e);
        static $head_files_added = false;
        if (!$head_files_added) {
            fx::page()->addCssFile(fx::path('@floxim/Admin/style/debug.less'));
            fx::page()->addJsFile(FX_JQUERY_PATH);
            fx::page()->addJsFile(fx::path('@floxim/Admin/js/fxj.js'));
            fx::page()->addJsFile(fx::path('@floxim/Admin/js/debug.js'));
            register_shutdown_function(function () {
                if (!fx::env()->get('complete_ok') && !fx::env('ajax')) {
                    echo fx::page()->getAssetsCode();
                }
            });
            $head_files_added = true;
        }
    }

    protected function entry()
    {
        $args = func_get_args();
        
        $c_time = microtime(true);
        $memory = memory_get_usage(true);

        $backtrace = debug_backtrace();
        
        //$args []= $backtrace;
        
        $is_cdebug = isset($backtrace[6]) && isset($backtrace[6]['function']) && $backtrace[6]['function'] === 'cdebug';
        $backtrace = array_slice($backtrace, $is_cdebug ? 6 : 4, 2);
        
        $meta = array(
            'time'   => $c_time - $this->start_time,
            'passed' => $c_time - $this->last_time,
            'memory' => $memory
        );
        $this->last_time = $c_time;
        
        
        
        if (isset($backtrace[0]['file'])) {
            $meta['file'] = $backtrace[0]['file'];
            $meta['line'] = $backtrace[0]['line'];
        }
        
        $caller = '';
        if (isset($backtrace[1])) {
            if (isset($backtrace[1]['class'])) {
                $caller = $backtrace[1]['class'];
                $caller .= $backtrace[1]['type'];
            }
            if (isset($backtrace[1]['function'])) {
                $caller .= $backtrace[1]['function'];
            }
        }
        $meta['caller'] = $caller;

        
        $items = array();
        foreach ($args as $a) {
            //$items[]= $a;
            $items []= $this->toPlain($a);
        }
        return array($meta, $items);
    }
    
    const KEY_ARRAY_KEY = 0;
    const KEY_PUBLIC = 1;
    const KEY_PROTECTED = 2;
    const KEY_PRIVATE = 3;
    
    protected $primitives = array(
        'integer' => 0,
        'double' => 1,
        'string' => 2,
        'array' => 3,
        'boolean' => 4,
        'null' => 5,
        'resource' => 6,
        '_link' => 7
    );
    
    public function toJson($what) {
        $plain = $this->toPlain($what);
        return $this->jsonEncode($plain, JSON_UNESCAPED_UNICODE);
    }
    
    protected function jsonEncode($what)
    {
        static $is_53 = null;
        if (is_null($is_53)) {
            $is_53 = version_compare(phpversion(), '5.4') < 0;
        }
        if ($is_53) {
            return json_encode($what);
        }
        return json_encode($what, JSON_UNESCAPED_UNICODE);
    }
    
    public function toPlain($what, &$index = array()) 
    {
        if (is_string($what)) {
            return $what;
        }
        if (is_null($what)) {
            return array($this->primitives['null']);
        }
        $res = array();
        if (is_array($what)) {
            $res[0] = $this->primitives['array'];
            $res[1] = array();
            foreach ($what as $key => $value) {
                $res[1] []= array(
                    $key,
                    $this->toPlain($value, $index)
                );
            }
        } elseif (is_object($what)) {
            $found_keys = array_keys($index, $what, true);
            if (count($found_keys) > 0) {
                $res[0] = $this->primitives['_link'];
                $res[1] = $found_keys[0];
            } else {
                $index []= $what;
                $res[0] = get_class($what);
                $res[1] = array();
                $res[2] = count($index) - 1;
                $atts = array();
                if ($what instanceof \Traversable) {
                    foreach ($what as $key => $value) {
                        $atts[$key] = $value;
                    }
                } elseif ($what instanceof \Floxim\Floxim\System\Entity) {
                    $atts = $what->get();
                }
                foreach ($atts as $key => $value) {
                    $res[1] []= array(
                        array(self::KEY_ARRAY_KEY, $key),
                        $this->toPlain($value, $index)
                    );
                }
                $r_object = new \ReflectionObject($what);
                $props = $r_object->getProperties();
                $res_props = array();
                foreach ($props as $prop) {
                    if ($prop->isStatic()) {
                        continue;
                    }
                    if ($prop->isPrivate()) {
                        $key_type = self::KEY_PRIVATE;
                        $prop->setAccessible(true);
                    } elseif ($prop->isProtected()) {
                        $key_type = self::KEY_PROTECTED;
                        $prop->setAccessible(true);
                    } else {
                        $key_type = self::KEY_PUBLIC;
                    }
                    
                    $res_props[] = array(
                        array($key_type, $prop->getName()),
                        $this->toPlain($prop->getValue($what), $index)
                    );
                }
                /*
                usort($res_props, function($a, $b) {
                    
                });
                 * 
                 */
                foreach ($res_props as $rp) {
                    $res[1] []= $rp;
                }
            }
        } else {
            $res[0] = $this->primitives[strtolower(gettype($what))];
            $res[1] = $what;
        }
        return $res;
    }
    
    protected function printEntry($e)
    {
        if (!is_array($e) || count($e) < 2) {
            return;
        }
        $meta = $e[0];
        $file = isset($meta['file']) ? $meta['file'] : false;
        $line = isset($meta['line']) ? $meta['line'] : false;
        static $is_first_entry = true;
        ?>
        <div class='fx_debug_entry'>
            <div class='fx_debug_title'>
                <?php echo $file; ?>
                <?php if ($line !== false) {
                    ?> at line <b><?php echo $line ?></b><?php
                }
                echo sprintf(
                    ' (+%.5f, %.5f s, %s)',
                    $meta['passed'],
                    $meta['time'],
                    self::convertMemory($meta['memory'])
                );
                ?>
            </div>
            <?php 
            foreach ($e[1] as $item) {
                
                $json = $this->jsonEncode($item);
                $id = md5($json);
                ?>
                <div class="fx-debug__data-entry" data-hash="<?=$id?>" style="display:none;"></div>
                <script type="text/javascript">
                    <?php
                    if ($is_first_entry) {
                        $is_first_entry = false;
                        ?>
                        if (!window.fx_debug_data) {
                            window.fx_debug_data = {};
                        }
                        <?php
                    }
                    ?>
                    window.fx_debug_data['<?=$id?>'] = <?= $json ?>;
                </script>
                <?php
            }
            ?>
        </div>
        <?php
    }

    protected function _printEntry($e)
    {
        $meta = $e[0];
        $file = isset($meta['file']) ? $meta['file'] : false;
        $line = isset($meta['line']) ? $meta['line'] : false;
        ?>
        <div class='fx_debug_entry'>
            <div class='fx_debug_title'>
                <?php echo $file; ?>
                <?php if ($line !== false) {
                    ?> at line <b><?php echo $line ?></b><?php
                }
                echo sprintf(
                    ' (+%.5f, %.5f s, %s)',
                    $meta['passed'],
                    $meta['time'],
                    self::convertMemory($meta['memory'])
                );
                ?>
            </div>
            <?php foreach ($e[1] as $n => $item) {
                ob_start();
                if (in_array($item[0], array('array', 'object'))) {
                    echo $this->printFormat($item[1]);
                } else {
                    if (substr($item[1], 0, 5) === '%raw%') {
                        echo substr($item[1], 5);
                    } elseif (strstr($item[1], "\n")) {
                        echo '<pre>' . htmlspecialchars($item[1]) . '</pre>';
                    } else {
                        echo '<pre class="fx_debug_one_line">' . htmlspecialchars($item[1]) . '</pre>';
                    }
                }
                $printed = ob_get_clean();
                echo preg_replace_callback(
                    '~\[\[(good|bad)\s(.+?)\]\]~',
                    function ($matches) {
                        return '<span class="fx_debug_' . $matches[1] . '">' . $matches[2] . '</span>';
                    },
                    $printed
                );
                if ($n < count($e[1]) - 1) {
                    ?>
                    <div class="fx_debug_separator"></div>
                <?php } ?>
            <?php } ?>
        </div>
    <?php
    }

    public static function convertMemory($size, $round = 3)
    {
        $sizes = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
        $total = count($sizes);
        for ($i = 0; $size > 1024 && $i < $total; $i++) {
            $size = $size / 1024;
        }
        $result = $size . " " . $sizes[$i];
        return $result;
    }

    protected function printFormat($html)
    {
        $strings = explode("\n", htmlspecialchars($html));
        unset($html);
        $result = array();
        $collapsers = array();
        $level = 0;
        foreach ($strings as $string_num => $s) {
            if (strlen($s) > 0) {
                $init_line = $s;
                $s = trim($s);
                $is_index = preg_match("~^\s*\[.+\]~", $s);

                $s = preg_replace("~\sObject$~", '', $s);
                $s = preg_replace("~^\[(.+?)\]\s=&gt;\s?~", '<b class="pn">$1</b><span class="vs">&nbsp;:&nbsp;</span>',
                    $s);
                $s = preg_replace('~>(.+?):(protected|private)</b>~', '><span class="$2">*</span> $1</b>', $s);
                if ($s == '(') {
                    $level++;
                    $c_string = '<div class="fx_debug_collapse">';
                    $collapser =& $result[count($result) - 1];
                    $collapsers[$level] = array(
                        'collapser' => &$collapser,
                        'length'    => 0
                    );
                } elseif ($s == ')') {
                    $c_string = '</div>';
                    if (isset($collapsers[$level]) && $collapsers[$level]['length'] > 0) {
                        $c_collapser = $collapsers[$level]['collapser'];
                        $c_collapser = preg_replace('~^<div class="~', '<div class="fx_debug_collapser ', $c_collapser);
                        $c_collapser = preg_replace("~</div>$~",
                            " <i class='ln'>" . $collapsers[$level]['length'] . "</i></div>", $c_collapser);
                        $collapsers[$level]['collapser'] = $c_collapser;
                    }
                    $level--;
                } else {
                    if (preg_match("~\*RECURSION~", $s)) {
                        $last_string =& $result[count($result) - 1];
                        $last_string = preg_replace('~^<div class="~', '<div class="fx_debug_recursion ', $last_string);
                        $last_string = preg_replace('~</span></div>$~', ' [RECURSION]</span></div>', $last_string);
                        $c_string = '';
                    } else {
                        $c_string = '<div class="fx_debug_line"><span>'
                            . ($is_index || $string_num == 0 ? $s : $init_line)
                            . '</span></div>';
                        if (isset($collapsers[$level]) && $is_index) {
                            $collapsers[$level]['length']++;
                        }
                    }
                }
                $result[] = $c_string;
            }
        }
        return join("", $result);
    }
}