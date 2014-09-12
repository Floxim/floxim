<?php

namespace Floxim\Floxim\Helper\Form\Field;

use Floxim\Floxim\System\Fx as fx;

abstract class Options extends Field {
    public function offsetSet($offset, $value) {
        if ($offset === 'values' && (is_array($value) || $value instanceof Traversable)) {
            if (is_array($value)) {
                $value = fx::collection($value);
            }
            foreach ($value as $opt_key => $opt_val) {
                if (is_scalar($opt_val)) {
                    $value[$opt_key] = array('name' => $opt_val);
                }
            }
        }
        return parent::offsetSet($offset, $value);
    }
}