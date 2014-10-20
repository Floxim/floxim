<?php

namespace Floxim\Floxim\Component\UrlAlias;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

class Entity extends System\Entity {
	/**
	 * Reset "is_current" flag from this alias
	 */
	public function resetCurrent () {
		$this->set('is_current', 0)->save();
	}
    
    /**
	 * Reset "is_original" flag from this alias
	 */
    public function resetOriginal () {
		$this->set('is_original', 0)->save();
	}
	
	/**
	 * Check "is_current" flag for this alias
	 */
	public function isCurrent() {
        return $this['is_current'];
    }
	
	/**
	 * Check "is_current" flag for this alias
	 */
	public function isOriginal() {
        return $this['is_original'];
    }    
}