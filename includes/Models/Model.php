<?php

namespace WpAiAltText\Includes\Models;

use WpAiAltText\Includes\Support\Collection;

if (!defined('ABSPATH')) {
    exit;
}

abstract class Model
{
    protected $table;
    protected $primaryKey = 'id';
    protected $attributes = [];
    protected $fillable = [];
    protected $casts = [];
    protected $relations = [];
    protected $with = [];
    protected $query = [
        'select' => '*',
        'where' => [],
        'orderBy' => null,
        'limit' => null,
        'offset' => null
    ];
    protected $validOperators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN'];

    /**
     * Start a new query
     */
    public static function query()
    {
        return new static();
    }

    /**
     * Eager load related models
     *
     * @param array|string $relations
     * @return $this
     */
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $this->with = $relations;
        return $this;
    }

    /**
     * Define a one-to-many relationship
     *
     * @param string $related Related model class name
     * @param string $foreignKey Foreign key in the related table
     * @param string $localKey Local key in this table (defaults to primary key)
     * @return Collection
     */
    protected function hasMany($related, $foreignKey, $localKey = null)
    {
        $localKey = $localKey ?: $this->primaryKey;
        $localValue = $this->attributes[$localKey];

        return $related::where($foreignKey, $localValue)->get();
    }

    /**
     * Define a one-to-one relationship
     *
     * @param string $related Related model class name
     * @param string $foreignKey Foreign key in the related table
     * @param string $localKey Local key in this table (defaults to primary key)
     * @return Model|null
     */
    protected function hasOne($related, $foreignKey, $localKey = null)
    {
        $localKey = $localKey ?: $this->primaryKey;
        $localValue = $this->attributes[$localKey];

        return $related::where($foreignKey, $localValue)->first();
    }

    /**
     * Define an inverse one-to-one or many relationship
     *
     * @param string $related Related model class name
     * @param string $foreignKey Foreign key in this table
     * @param string $ownerKey Key in the related table (defaults to primary key)
     * @return Model|null
     */
    protected function belongsTo($related, $foreignKey, $ownerKey = 'id')
    {
        $foreignValue = $this->attributes[$foreignKey] ?? null;

        if ($foreignValue === null) {
            return null;
        }

        return $related::where($ownerKey, $foreignValue)->first();
    }

    /**
     * Load a relationship
     *
     * @param string $relation
     * @return mixed
     */
    public function load($relation)
    {
        if (!method_exists($this, $relation)) {
            return null;
        }

        $this->relations[$relation] = $this->$relation();
        return $this->relations[$relation];
    }

    /**
     * Load multiple relationships
     *
     * @param array $relations
     * @return $this
     */
    public function loadRelations(array $relations)
    {
        foreach ($relations as $relation) {
            $this->load($relation);
        }

        return $this;
    }

    public static function all()
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT * FROM " . static::table(),
            ARRAY_A
        );

        $models = new Collection(array_map(function ($item) {
            return static::hydrate($item);
        }, $results ?: []));

        // Handle any eager loading
        $instance = new static();
        if (!empty($instance->with)) {
            foreach ($models as $model) {
                $model->loadRelations($instance->with);
            }
        }

        return $models;
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($attributes as $key => $value) {
            if ($value instanceof \DateTime) {
                $attributes[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_object($value) && method_exists($value, 'toArray')) {
                $attributes[$key] = $value->toArray();
            }
        }

        // Include loaded relations
        foreach ($this->relations as $key => $relation) {
            if ($relation instanceof Model) {
                $attributes[$key] = $relation->toArray();
            } elseif ($relation instanceof Collection) {
                $attributes[$key] = $relation->toArray();
            } elseif (is_array($relation)) {
                $attributes[$key] = array_map(function ($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                }, $relation);
            }
        }

        return $attributes;
    }

    public static function table()
    {
        global $wpdb;
        $instance = new static();
        return $wpdb->prefix . WP_AI_ALT_TEXT_TABLE_PREFIX . $instance->table;
    }

    /**
     * Add where clause to query
     */
    public static function where($column, $operator = null, $value = null)
    {
        $instance = new static();
        return $instance->andWhere($column, $operator, $value);
    }

    /**
     * Add another where clause
     */
    public function andWhere($column, $operator = null, $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtoupper($operator);
        if (!in_array($operator, $this->validOperators)) {
            throw new \InvalidArgumentException("Invalid operator: {$operator}");
        }

        if (in_array($operator, ['LIKE', 'NOT LIKE'])) {
            if (strpos($value, '%') === false) {
                $value = '%' . $value . '%';
            }
        }

        $this->query['where'][] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];

        return $this;
    }

    /**
     * Add order by clause
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $this->query['orderBy'] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        return $this;
    }

    /**
     * Add pagination
     */
    public function paginate($perPage = 15, $page = 1)
    {
        global $wpdb;

        $this->query['limit'] = (int) $perPage;
        $this->query['offset'] = ((int) $page - 1) * $perPage;

        // Get total count
        $sql = "SELECT COUNT(*) FROM " . static::table();
        if (!empty($this->query['where'])) {
            $sql .= $this->buildWhereClause();
        }
        $total = (int) $wpdb->get_var($sql);

        // Get paginated results
        $results = $this->get();

        return [
            'data' => $results,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total
            ]
        ];
    }

    /**
     * Execute query and get results
     */
    public function get($columns = ['*'])
    {
        global $wpdb;

        $sql = "SELECT {$this->query['select']} FROM " . static::table();
        $this->query['select'] = is_array($columns) ? implode(', ', $columns) : $columns;
        if (!empty($this->query['where'])) {
            $sql .= $this->buildWhereClause();
        }

        if ($this->query['orderBy']) {
            $sql .= " ORDER BY {$this->query['orderBy']['column']} {$this->query['orderBy']['direction']}";
        }

        if ($this->query['limit']) {
            $sql .= $wpdb->prepare(" LIMIT %d", $this->query['limit']);
            if ($this->query['offset']) {
                $sql .= $wpdb->prepare(" OFFSET %d", $this->query['offset']);
            }
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        $models = array_map(function ($item) {
            return static::hydrate($item);
        }, $results ?: []);

        $collection = new Collection($models);

        // Load any eager loaded relationships
        if (!empty($this->with)) {
            foreach ($collection as $model) {
                $model->loadRelations($this->with);
            }
        }

        return $collection;
    }

    /**
     * Create a new model instance from database attributes
     *
     * @param array $attributes
     * @return static
     */
    protected static function hydrate($attributes)
    {
        $model = new static();
        $model->attributes = $attributes;

        // Handle any type casting
        if (isset($model->casts)) {
            foreach ($model->casts as $key => $type) {
                if (isset($attributes[$key])) {
                    switch ($type) {
                        case 'json':
                            if (is_string($attributes[$key])) {
                                $model->attributes[$key] = json_decode($attributes[$key], true);
                            }
                            break;
                        case 'datetime':
                            if (is_string($attributes[$key])) {
                                $model->attributes[$key] = strtotime($attributes[$key]);
                            }
                            break;
                        case 'boolean':
                            $model->attributes[$key] = (bool) $attributes[$key];
                            break;
                    }
                }
            }
        }

        return $model;
    }

    // Update find method to use hydrate
    public static function find($id)
    {
        global $wpdb;
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . static::table() . " WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!$result) {
            return null;
        }

        $model = static::hydrate($result);

        // Load any eager loaded relationships if this was called from a query builder
        $instance = new static();
        if (!empty($instance->with)) {
            $model->loadRelations($instance->with);
        }

        return $model;
    }

    public function save()
    {
        global $wpdb;
        $table = static::table();

        // Prepare attributes for saving
        $attributes = $this->attributes;

        // Handle casts before saving
        foreach ($this->casts as $key => $type) {
            if (isset($attributes[$key])) {
                switch ($type) {
                    case 'json':
                        if (is_array($attributes[$key]) || is_object($attributes[$key])) {
                            $attributes[$key] = json_encode($attributes[$key]);
                        }
                        break;
                    case 'datetime':
                        if ($attributes[$key] instanceof \DateTime) {
                            $attributes[$key] = $attributes[$key]->format('Y-m-d H:i:s');
                        }
                        break;
                    case 'boolean':
                        $attributes[$key] = (int) $attributes[$key];
                        break;
                }
            }
        }

        // Update or Insert
        if (isset($attributes['id'])) {
            $updated = $wpdb->update(
                $table,
                $attributes,
                ['id' => $attributes['id']]
            );
            return $updated !== false;
        }

        $inserted = $wpdb->insert($table, $attributes);
        if ($inserted) {
            $this->attributes['id'] = $wpdb->insert_id;
            return true;
        }

        return false;
    }

    public function __get($key)
    {
        // First check if it's a loaded relationship
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Next, check if it's an attribute
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        // Finally, check if it's a relationship method that hasn't been loaded yet
        if (method_exists($this, $key)) {
            $this->relations[$key] = $this->$key();
            return $this->relations[$key];
        }

        return null;
    }

    public function __set($key, $value)
    {
        if (in_array($key, $this->fillable)) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Create a new record
     *
     * @param array $attributes
     * @return static
     */
    public static function create(array $attributes)
    {
        $model = new static();
        foreach ($attributes as $key => $value) {
            if (in_array($key, $model->fillable)) {
                $model->attributes[$key] = $value;
            }
        }
        $model->save();
        return $model;
    }

    /**
     * Delete the current model instance from database
     *
     * @return bool
     */
    public function delete()
    {
        global $wpdb;

        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        $id = $this->attributes[$this->primaryKey];

        $result = $wpdb->delete(
            static::table(),
            [$this->primaryKey => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Find and delete a record
     *
     * @param int $id
     * @return bool
     */
    public static function findByIdAndDelete($id)
    {
        global $wpdb;
        return $wpdb->delete(
            static::table(),
            ['id' => $id],
            ['%d']
        );
    }

    /**
     * Update a record
     *
     * @param int $id
     * @param array $attributes
     * @return bool
     */
    public static function update($id, array $attributes)
    {
        $model = static::find($id);
        if (!$model) {
            return false;
        }

        foreach ($attributes as $key => $value) {
            if (in_array($key, $model->fillable)) {
                $model->attributes[$key] = $value;
            }
        }

        return $model->save();
    }

    /**
     * Get first record from query
     */
    public function first()
    {
        $this->query['limit'] = 1;
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Build WHERE clause
     */
    protected function buildWhereClause()
    {
        global $wpdb;

        $conditions = [];
        foreach ($this->query['where'] as $where) {
            $conditions[] = $wpdb->prepare(
                "`{$where['column']}` {$where['operator']} %s",
                $where['value']
            );
        }

        return " WHERE " . implode(' AND ', $conditions);
    }
}
