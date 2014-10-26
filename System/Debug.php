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
        $this->start_time = isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true);
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


    protected function getDir()
    {
        if (is_null($this->dir)) {
            $this->dir = fx::path('log');
        }
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
    protected function startLog()
    {
        $this->file = fx::files()->open($this->getFileName(), 'w');
        register_shutdown_function(array($this, 'stopLog'));
    }

    public function stopLog()
    {
        if (is_null($this->file)) {
            return;
        }
        fclose($this->file);
        $this->writeIndex();
    }

    /**
     * Get short backtrace output.
     * Useful together with logger, e.g. fx::log($some_data, fx_debug::backtrace(6));
     * @param int|bool $level how many trace levels to display or FALSE to show all
     * @return string Trace, each level separated by newline
     */
    public static function backtrace($level = 3)
    {
        $trace = debug_backtrace();
        if ($level !== false) {
            $trace = array_slice($trace, 0, $level);
        }
        $res = array();
        foreach ($trace as $l) {
            $str = '';
            if ($l['file']) {
                $file = fx::path()->toHttp($l['file']);
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
        fputs($fh_index, serialize($log_header) . "\n");
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
        $own_file = fx::path()->toAbs($this->getFileName());
        foreach ($log_files as $lf) {
            if (fx::path()->toAbs($lf) != $own_file) {
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
        $res = array_reverse($res);
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

    /**
     * Put args into log
     */
    public function log()
    {
        if (defined("FX_ALLOW_DEBUG") && !FX_ALLOW_DEBUG) {
            return;
        }
        if ($this->disabled) {
            return;
        }
        if (is_null($this->file)) {
            $this->startLog();
        } else {
            fputs($this->file, $this->separator);
        }
        fputs(
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
            fx::page()->addCssFile(fx::path('floxim', 'Admin/style/debug.less'));
            fx::page()->addJsFile(FX_JQUERY_PATH);
            fx::page()->addJsFile(fx::path('floxim', 'Admin/js/fxj.js'));
            fx::page()->addJsFile(fx::path('floxim', 'Admin/js/debug.js'));
            register_shutdown_function(function () {
                if (!fx::env()->get('complete_ok')) {
                    echo fx::page()->getAssetsCode();
                }
            });
            $head_files_added = true;
        }
    }

    protected function entry()
    {
        $c_time = microtime(true);
        $memory = memory_get_usage(true);

        $backtrace = array_slice(debug_backtrace(), 4, 2);

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

        $args = func_get_args();
        $items = array();
        foreach ($args as $a) {
            $type = gettype($a);
            if ($type == 'array' || $type == 'object') {
                $a = print_r($a, 1);
            }
            $items[] = array($type, $a);
        }
        return array($meta, $items);
    }

    protected function printEntry($e)
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
                    if (strstr($item[1], "\n")) {
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