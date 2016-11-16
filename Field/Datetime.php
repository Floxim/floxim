<?php

namespace Floxim\Floxim\Field;

class Datetime extends \Floxim\Floxim\Component\Field\Entity
{

    protected $day = '', $month = '', $year = '', $hours = '', $minutes = '', $seconds = '';

    public function getJsField($content)
    {
        $res = parent::getJsField($content);

        $this->loadValuesByStr($content[$this['keyword']]);
        
        $res = array_merge(
            $res,
            array(
               'day' => $this->day,
               'month' => $this->month,
               'year' => $this->year,
               'hours' => $this->hours,
               'minutes' => $this->minutes,
               'seconds' => $this->seconds
            )
        );
        return $res;
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
    
    public function getCastType() 
    {
        return 'date';
    }
}