<?php

namespace Floxim\Floxim\Template;

interface Entity extends \ArrayAccess
{
    /**
     * Add meta attributes to entity buffered html
     */
    public function addTemplateRecordMeta($html, $collection, $index, $is_subroot);

    /**
     * Get meta info about sertain entity field
     */
    public function getFieldMeta($field_keyword);
    
    /**
     * Get all entity offset keys
     */
    public function getAvailableOffsetKeys();
}