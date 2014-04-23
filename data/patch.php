<?php
class fx_data_patch extends fx_data {
    public function check_updates() {
        $stored = $this->all();
        @ $res = file_get_contents(
            'http://floxim.org/getfloxim/check_updates.php?v='.fx::version('full')
        );
        if (!$res) {
            return false;
        }
        $res = json_decode($res);
        if (!$res->patches || count($res->patches) == 0) {
            return true;
        }
        foreach ($res->patches as $patch) {
            if ($stored->find_one('to', $patch->to)) {
                continue;
            }
            $new_patch = $this->create(get_object_vars($patch));
            if ($patch->from == fx::version()) {
                $new_patch['status'] = 'ready';
            } else {
                $new_patch['status'] = 'pending';
            }
            $new_patch->save();
        }
        return true;
    }
}