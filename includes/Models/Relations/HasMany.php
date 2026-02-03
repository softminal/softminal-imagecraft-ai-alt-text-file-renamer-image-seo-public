<?php

namespace SmartAltTextAi\Includes\Models\Relations;

if (!defined('ABSPATH')) {
    exit;
}

class HasMany
{
    private $related;
    private $parent;
    private $foreignKey;
    private $localKey;

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
