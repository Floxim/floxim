<?php
class fx_data_patch extends fx_data {
    public function check_updates() {
        $stored = $this->all();
        $url=fx::config('fx.update_url').'?action=find&from='.fx::version();
        @ $res = file_get_contents($url);
        if (!$res) {
            return false;
        }
        $res = @json_decode($res);
        if ($res) {
            foreach($res as $patch) {
                if ($stored->find_one('to', $patch->to)) {
                    continue;
                }
                $new_patch = $this->create(array(
                    'to'=>$patch->to,
                    'from'=>$patch->from,
                    'url'=>$patch->url,
                    'created'=>$patch->created,
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

    public function get_ready_for_install() {
        return $this->where('status','ready')->one();
    }
}