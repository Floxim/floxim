<?php
class fx_profiler {
    protected $level = 0;
    public $data = array();
    protected $stack = array();
    
    const T_BLOCK = 1;
    const T_TAG = 2;
    
    protected $tags = array();
    
    public function block($name) {
        $this->level++;
        $this->tags = array();
        $this->stack []= array($name, microtime(true), self::T_BLOCK);
        return $this;
    }
    
    public function tag($name) {
        $this->level++;
        $this->stack []= array($name, microtime(true), self::T_TAG);
        return $this;
    }
    
    protected $stopped_type = null;
    public function stop() {
        $meta = array_pop($this->stack);
        $c_type = $meta[2];
        $this->stopped_type = $c_type;
        $time = microtime(true) - $meta[1];
        if ($c_type == self::T_BLOCK) {
            $this->data []= array($meta[0], $this->level, $time, $this->tags);
            $this->tags = array();
        } else {
            if (!isset($this->tags[$meta[0]])) {
                $this->tags[$meta[0]] = 0;
            }
            $this->tags[$meta[0]] += $time;
        }
        
        $this->level--;
        return $this;
    }
    
    public function then($name) {
        $this->stop();
        if ($this->stopped_type == self::T_BLOCK) {
            $this->block($name);
        } else {
            $this->tag($name);
        }
        return $this;
    }
    
    public function result($plain_data) {
        $root = new fx_profiler_block(array('root',0,0,array()));
        $roots = array($root);
        while ($b = array_pop($plain_data)) {
            $b = new fx_profiler_block($b);
            do {
                $c_target = array_pop($roots);
            } while ($b->level <= $c_target->level);
            $c_target->add_child($b);
            $roots[]= $c_target;
            $roots[]= $b;
        }
        return $root;
    }
    
    public function show() {
        $res = $this->result($this->data);
        ob_start();
        $res->show();
        return ob_get_clean();
    }
}

class fx_profiler_block {
    public $level = 0;
    public $tags = array();
    public $child_tags = array();
    public $children = array();
    public $time = 0;
    public $name = '';
    public function __construct($params = null) {
        if ($params) {
            $this->name = $params[0];
            $this->level = $params[1];
            $this->time = $params[2];
            $this->tags = $params[3];
        }
    }
    
    public function __toString() {
        return $this->name.' <sup>'.$this->level.'</sup>';
    }

        public function add_child($block) {
        array_unshift($this->children, $block);
    }
    
    public function get_tags() {
        $tags = $this->tags;
        foreach ($this->children as  $ch) {
            $child_tags = $ch->get_tags();
            foreach ($child_tags as $tag => $time) {
                if (!isset($tags[$tag])) {
                    $tags[$tag] = 0;
                }
                $tags[$tag] += $time;
            }
        }
        return $tags;
    }
    
    public static function ftime($time) {
        $threshold = 0.0009;
        $time = $time < $threshold ? 0 : round($time * 1000) / 1000;
        return $time;
    }
    
    public function show() {
        $time = self::ftime($this->time);
        $child_time = 0;
        foreach ($this->children as $ch) {
            $child_time += $ch->time;
        }
        $child_time = self::ftime($child_time);
        if ($this->level > 0) {
            ?><b><?=$this->name?></b> &mdash; <?=$time?><?php
            if ($child_time && $child_time != $time) {
                ?> (<?=$child_time?>)<?php
            }
        }
        if (count($this->children) > 0) {
            ?>
            <ul class="profiler_res">
                <?php foreach ($this->children as $child) {
                    if (self::ftime($child->time)) {?>
                        <li><?php $child->show();?></li>
                    <?php }
                }?>
            </ul>
            <?php
        }
        $tags = $this->get_tags();
        if ($this->level > 0 && count($tags) > 0) {
            ?>
            <ul>
                <?php foreach ($tags as $tag => $time) {?>
                <li><?=$tag?> &ndash; <?=$time?></li>
                <?php } ?>
            </ul>
            <?php
        }
    }
}