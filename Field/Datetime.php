<?php

namespace Floxim\Floxim\Field;

class Datetime extends Baze
{

    protected $day = '', $month = '', $year = '', $hours = '', $minutes = '', $seconds = '';

    public function getJsField($content)
    {
        parent::getJsField($content);

        $this->loadValuesByStr($content[$this['keyword']]);
        $this->_js_field['day'] = $this->day;
        $this->_js_field['month'] = $this->month;
        $this->_js_field['year'] = $this->year;
        $this->_js_field['hours'] = $this->hours;
        $this->_js_field['minutes'] = $this->minutes;
        $this->_js_field['seconds'] = $this->seconds;

        return $this->_js_field;
    }

    public function setValue($value)
    {
        if (is_array($value)) {
            $this->day = $value['day'];
            $this->month = $value['month'];
            $this->year = $value['year'];
            $this->hours = $value['hours'];
            $this->minutes = $value['minutes'];
            $this->seconds = $value['seconds'];

            $this->value = $this->year . '-' . $this->month . '-' . $this->day . ' ';
            $this->value .= $this->hours . ':' . $this->minutes . ':' . $this->seconds;
        } else {
            if ($value) {
                $this->value = $value;
                $this->loadValuesByStr($this->value);
            } else {
                $this->value = '';
            }
        }
    }

    public function getSqlType()
    {
        return "DATETIME";
    }

    protected function loadValuesByStr($str)
    {
        if ($str) {
            $timestamp = strtotime($str);
            $this->day = date('d', $timestamp);
            $this->month = date('m', $timestamp);
            $this->year = date('Y', $timestamp);
            $this->hours = date('H', $timestamp);
            $this->minutes = date('i', $timestamp);
            $this->seconds = date('s', $timestamp);
        }
    }

    public function getSavestring()
    {
        $v = $this->value;
        if (empty($v)) {
            return null;
        }
        $time = strtotime($v);
        if (empty($time)) {
            return null;
        }
        return $v;
    }
}