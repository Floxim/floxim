<?php

namespace Floxim\Floxim\Component\Patch;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Finder extends System\Data
{
    public function checkUpdates()
    {
        $stored = $this->all();
        $url = fx::config('fx.update_url') . '?action=find&from=' . fx::version();
        @ $res = file_get_contents($url);
        if (!$res) {
            return false;
        }
        $res = @json_decode($res);
        if ($res) {
            foreach ($res as $patch) {
                if ($stored->findOne('to', $patch->to)) {
                    continue;
                }
                $new_patch = $this->create(array(
                    'to'      => $patch->to,
                    'from'    => $patch->from,
                    'url'     => $patch->url,
                    'created' => $patch->created,
                ));
                if ($patch->from == fx::version()) {
                    $new_patch['status'] = 'ready';
                } else {
                    $new_patch['status'] = 'pending';
                }
                $new_patch->save();
            }
        }
        return true;
    }

    public function getReadyForInstall()
    {
        return $this->where('status', 'ready')->one();
    }
}