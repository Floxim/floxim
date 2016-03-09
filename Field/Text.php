<?php

namespace Floxim\Floxim\Field;

use Floxim\Floxim\System\Fx as fx;

class Text extends \Floxim\Floxim\Component\Field\Entity
{

    public function getJsField($content)
    {
        $res = parent::getJsField($content);
        if (isset($this['format']) && isset($this['format']['html']) && $this['format']['html']) {
            $res['wysiwyg'] = true;
            $res['nl2br'] = $this['format']['nl2br'];
        }

        return $res;
    }
    
    public function getCastType() 
    {
        return 'string';
    }

    public function formatSettings()
    {
        $fields = array(
            'html' => array(
                'type'  => 'checkbox',
                'label' => fx::alang('allow HTML tags', 'system'),
                'value' => $this['format']['html']
            ),
            'nl2br' => array(
                'type'  => 'checkbox',
                'label' => fx::alang('replace newline to br', 'system'),
                'value' => $this['format']['nl2br']
            )
        );
        return $fields;
    }
    
    public function getSqlType()
    {
        return "MEDIUMTEXT";
    }
    
    public function fakeValue() 
    {
        $lorem = array(
            'Чувство регрессийно аннигилирует дискретный полиряд.',
            'Закон отталкивает концептуальный онтогенез речи.',
            'Как было показано выше, восприятие многопланово вызывает дисторшн.',
            'Плавно-мобильное голосовое поле изящно дает септаккорд.',
            'Выготский разработал, ориентируясь на методологию марксизма, учение которое утверждает что, драм-машина имитирует лирический стимул.',
            'Аллюзийно-полистилистическая композиция всекомпонентна.',
            'Соноропериод продолжает перекрестный психоанализ.',
            'Самонаблюдение, как бы это ни казалось парадоксальным, спонтанно притягивает сублимированный филогенез.',
            'Большую роль в популяризации психодрамы сыграл институт социометрии, который midi-контроллер mezzo forte представляет собой методологический септаккорд, не говоря уже о том, что рок-н-ролл мертв.',
            'Восприятие последовательно диссонирует групповой райдер.',
            'Конформизм, согласно традиционным представлениям, слабопроницаем.',
            'Лайн-ап осознаёт сексуальный нонаккорд.',
            'Сознание имитирует онтогенез речи.',
            'Эриксоновский гипноз аннигилирует субъект.',
        );
        shuffle($lorem);
        $res = '';
        foreach (range(0, rand(0,3)) as $n) {
            $res .= '<p>'.array_pop($lorem).'</p>';
        }
        return $res;
    }
}