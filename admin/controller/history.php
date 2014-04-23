<?php

class fx_controller_admin_history extends fx_controller {

    static public function get_undo_obj() {
        $history = fx::data('history')->get_last();
        $history = $history[0];

        if ($history['id']) {
            return $history;
        }

        return false;
    }

    static public function get_redo_obj() {
        $history = fx::data('history')->get_next();
        $history = $history[0];

        if ($history['id']) {
            return $history;
        }

        return false;
    }

    public function __construct() {
        $this->save_history = false;
    }

    public function undo() {
        $history = self::get_undo_obj();
        if ($history) {
            $history->undo()->set('marker', 1)->save(true);
        }
        return array('status' => 'ok', 'text' => fx::alang('Cancelled','system'));
    }

    public function redo() {
        $history = self::get_redo_obj();

        if ($history) {
            $history->redo()->set('marker', 0)->save(true);
        }
        return array('status' => 'ok', 'text' => fx::alang('Repeated','system'));
    }

}

?>