<?php

namespace WpAiAltText\Includes\Models\Relations;

if (!defined('ABSPATH')) {
    exit;
}

class BelongsTo
{
    protected $related;
    protected $child;
    protected $foreignKey;
    protected $ownerKey;

    public function __construct($related, $child, $foreignKey, $ownerKey)
    {
        $this->related = $related;
        $this->child = $child;
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
    }

    public function get()
    {
        $result = $this->related::where($this->ownerKey, $this->child->{$this->foreignKey});
        return !empty($result) ? $result[0] : null;
    }
}