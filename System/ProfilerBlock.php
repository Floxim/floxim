<?php

namespace Floxim\Floxim\System;

class ProfilerBlock
{
    public $level = 0;
    public $tags = array();
    public $child_tags = array();
    public $children = array();
    public $time = 0;
    public $name = '';

    public function __construct($params = null)
    {
        if ($params) {
            $this->name = $params[0];
            $this->level = $params[1];
            $this->time = $params[2];
            $this->tags = $params[3];
        }
    }

    public function __toString()
    {
        return $this->name . ' <sup>' . $this->level . '</sup>';
    }

    public function addChild($block)
    {
        array_unshift($this->children, $block);
    }

    public function getTags()
    {
        $tags = $this->tags;
        foreach ($this->children as $ch) {
            $child_tags = $ch->getTags();
            foreach ($child_tags as $tag => $time) {
                if (!isset($tags[$tag])) {
                    $tags[$tag] = 0;
                }
                $tags[$tag] += $time;
            }
        }
        return $tags;
    }

    public static function ftime($time)
    {
        $threshold = 0.0009;
        $time = $time < $threshold ? 0 : round($time * 1000) / 1000;
        return $time;
    }

    public function show()
    {
        $time = self::ftime($this->time);
        $child_time = 0;
        foreach ($this->children as $ch) {
            $child_time += $ch->time;
        }
        $child_time = self::ftime($child_time);
        if ($this->level === 0) {
            ?>
            <table class="fx_profiler_table">
            <?php
        } else  {
            $padding = 10 * ($this->level - 1);
            ?>
            <tr class="fx_profiler_row fx_profiler_row__level_<?=$this->level?>">
                <td style="padding-left:<?= $padding ?>px;"><?=$this->name?></td>
                <td>
                    <?= str_repeat('&nbsp;', $this->level+1)?>
                    <?= $time ?>
                    <?= ($child_time && $child_time != $time ? ' <span style="font-size:11px">('.$child_time.')</span>' : '')?>
                </td>
            </tr>
            <?php
        }
        if (count($this->children) > 0) {
            foreach ($this->children as $child) {
                if (self::ftime($child->time)) {
                    $child->show();
                }
            }
        }
        if ($this->level === 0) {
            ?></table><?php
        }
        /*
        $tags = $this->getTags();
        if ($this->level > 0 && count($tags) > 0) {
            ?>
            <ul>
                <?php foreach ($tags as $tag => $time) { ?>
                    <li><?= $tag ?> &ndash; <?= $time ?></li>
                <?php } ?>
            </ul>
        <?php
        }
         * 
         */
    }
}