<?php
namespace Floxim\Floxim\Component\Address;

use Floxim\Floxim\System\Fx as fx;

class Entity extends \Floxim\Floxim\Component\Basic\Entity
{
    public function getFormFields() {
        $fields = parent::getFormFields();
        $fields->apply(function(&$f) {
            if ($f['id'] === 'text') {
                $f['type'] = 'map';
                $f['lon_field'] = 'lon';
                $f['lat_field'] = 'lat';
            }
        });
        //fx::log($fields);
        return $fields;
    }
}