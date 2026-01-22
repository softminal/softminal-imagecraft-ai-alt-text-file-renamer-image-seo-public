<?php

namespace WpAiAltText\Includes\Models\Relations;

if (!defined('ABSPATH')) {
    exit;
}

class HasMany
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
        return $this->related::where($this->foreignKey, $this->parent->{$this->localKey});
    }
}