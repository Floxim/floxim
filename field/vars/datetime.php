<?php

class fx_field_vars_datetime {

    protected $timestamp;
    protected $to_string = null;
    protected $not_value = false;

    public function __construct($date) {
        if ( !$date || $date == '0000-00-00 00:00:00') {
            $this->not_value = true;
        }
        else {
            $this->timestamp = strtotime($date);
        }
        
    }

    public function format($format = false) {
        if ( $this->not_value ) {
            return '';
        }
        /**
         * @todo substitute the date format in zavisimosti localization through constant
         */
        if ($format === false) {
            $format = 'd.m.Y H:i:s';
        }

        return date($format, $this->timestamp);
    }

    public function get_date() {
        return $this->format('d.m.Y');
    }

    public function get_time() {
        return $this->format('H:i:s');
    }

    public function get_day() {
        return $this->format('d');
    }

    public function get_month() {
        return $this->format('m');
    }

    public function get_year() {
        return $this->format('Y');
    }

    public function get_hour() {
        return $this->format('H');
    }

    public function get_minutes() {
        return $this->format('i');
    }

    public function get_seconds() {
        return $this->format('s');
    }

    public function __toString() {
        return $this->to_string ? $this->to_string : $this->format();
    }

    public function set_to_str_value($value) {
        $this->to_string = $value;
    }

}

