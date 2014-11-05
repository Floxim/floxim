<?php

namespace Floxim\Floxim\Component\UrlAlias;

use Floxim\Floxim\System;
use Floxim\Floxim\System\Fx as fx;

/**
 * Finder class for the UrlAlias item
 *
 * Example usage:
 * fx::data('urlAlias')->getById($id);
 *
 * @package  Floxim\Floxim\Component\UrlAlias
 * @access   public
 * @see      http://floxim.org
 */
class Finder extends System\Finder
{
    /**
     * Get alias by id
     *
     * @param integer alias id
     *
     * @return object alias
     */
    public function getById($id)
    {
        return $this->where('id', $id)->one();
    }

    /**
     * Get all aliases by related page id
     *
     * @param integer related page id
     *
     * @return object aliases
     */
    public function getAllByPageId($page_id)
    {
        return $this->where('page_id', $page_id)->all();
    }

    /**
     * Get alias by related page id and "is_original" flag
     *
     * @param integer related page id
     *
     * @return object alias
     */
    public function getOriginalByPageId($page_id)
    {
        return $this->where('page_id', $page_id)->where('is_original', 1)->one();
    }
}