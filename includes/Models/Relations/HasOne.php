<?php

namespace WpAiAltText\Includes\Models\Relations;

if (!defined('ABSPATH')) {
    exit;
}

class HasOne
{
    protected $related;
    protected $parent;
    protected $foreignKey;
    protected $localKey;

    public function __construct($related, $parent, $foreignKey, $localKey)
    {
        $this->related = $related;
        $this->parent = $parent;
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    public function get()
    {
        $result = $this->related::where($this->foreignKey, $this->parent->{$this->localKey});
        return !empty($result) ? $result[0] : null;
    }
}