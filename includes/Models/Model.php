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
        'select'  => '*',
        'where'   => [],
        'orderBy' => null,
        'limit'   => null,
        'offset'  => null
    ];

    protected $validOperators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN'];

    /**
     * Object cache settings (tune as needed)
     */
    protected $cacheGroup = 'wp_ai_alt_text';
    protected $cacheTtl   = 60; // seconds

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
     */
    protected function hasMany($related, $foreignKey, $localKey = null)
    {
        $localKey   = $localKey ?: $this->primaryKey;
        $localValue = $this->attributes[$localKey] ?? null;

        if ($localValue === null) {
            return new Collection([]);
        }

        return $related::where($foreignKey, $localValue)->get();
    }

    /**
     * Define a one-to-one relationship
     */
    protected function hasOne($related, $foreignKey, $localKey = null)
    {
        $localKey   = $localKey ?: $this->primaryKey;
        $localValue = $this->attributes[$localKey] ?? null;

        if ($localValue === null) {
            return null;
        }

        return $related::where($foreignKey, $localValue)->first();
    }

    /**
     * Define an inverse one-to-one or many relationship
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
     */
    public function loadRelations(array $relations)
    {
        foreach ($relations as $relation) {
            $this->load($relation);
        }

        return $this;
    }

    /**
     * Cache helpers (version-busting keys so we can invalidate all model caches reliably)
     */
    protected function getCacheVersion(): int
    {
        $key = 'model_cache_ver:' . static::table();
        $ver = wp_cache_get($key, $this->cacheGroup);
        return $ver ? (int) $ver : 1;
    }

    protected static function bumpCacheVersion(): void
    {
        $instance = new static();
        $key = 'model_cache_ver:' . static::table();
        $ver = (int) wp_cache_get($key, $instance->cacheGroup);
        wp_cache_set($key, $ver + 1, $instance->cacheGroup, DAY_IN_SECONDS);
    }

    protected function cacheKey(string $suffix): string
    {
        return $suffix . ':v' . $this->getCacheVersion();
    }

    protected function cacheKeyForSql(string $sql, array $params = []): string
    {
        return $this->cacheKey('sql:' . md5(static::table() . '|' . $sql . '|' . wp_json_encode($params)));
    }

    /**
     * Get all records
     */
    public static function all()
    {
        global $wpdb;

        $instance = new static();
        $table = static::table();

        $cacheKey = $instance->cacheKey('all:' . md5($table));
        $cached = wp_cache_get($cacheKey, $instance->cacheGroup);

        if ($cached !== false) {
            return new Collection(array_map(function ($item) {
                return static::hydrate($item);
            }, $cached ?: []));
        }

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // Reason: Table is internal; query is safe. We cache results immediately after.
        $results = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        wp_cache_set($cacheKey, $results ?: [], $instance->cacheGroup, $instance->cacheTtl);

        return new Collection(array_map(function ($item) {
            return static::hydrate($item);
        }, $results ?: []));
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
            $value    = $operator;
            $operator = '=';
        }

        $operator = strtoupper((string) $operator);
        if (!in_array($operator, $this->validOperators, true)) {
            throw new \InvalidArgumentException('Invalid operator: ' . esc_html($operator));
        }

        if (in_array($operator, ['LIKE', 'NOT LIKE'], true)) {
            $value = (string) $value;
            if (strpos($value, '%') === false) {
                $value = '%' . $value . '%';
            }
        }

        $this->query['where'][] = [
            'column'   => (string) $column,
            'operator' => $operator,
            'value'    => $value
        ];

        return $this;
    }

    /**
     * Add order by clause
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $this->query['orderBy'] = [
            'column'    => (string) $column,
            'direction' => strtoupper((string) $direction)
        ];
        return $this;
    }

    /**
     * Add pagination
     */
    public function paginate($perPage = 15, $page = 1)
    {
        global $wpdb;

        $this->query['limit']  = (int) $perPage;
        $this->query['offset'] = ((int) $page - 1) * (int) $perPage;

        $table  = static::table();
        $sql    = "SELECT COUNT(*) FROM {$table}";
        $params = [];

        if (!empty($this->query['where'])) {
            [$whereSql, $whereParams] = $this->buildWhereClause();
            $sql .= $whereSql;
            $params = $whereParams;
        }

        $cacheKey = $this->cacheKeyForSql($sql, $params);
        $cachedTotal = wp_cache_get($cacheKey, $this->cacheGroup);

        if ($cachedTotal === false) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // Reason: Table is internal; values are prepared. We cache result.
            $countSql = !empty($params) ? $wpdb->prepare($sql, $params) : $sql;
            $total = (int) $wpdb->get_var($countSql);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

            wp_cache_set($cacheKey, $total, $this->cacheGroup, $this->cacheTtl);
        } else {
            $total = (int) $cachedTotal;
        }

        $results = $this->get();

        return [
            'data' => $results,
            'meta' => [
                'current_page' => (int) $page,
                'per_page'     => (int) $perPage,
                'total'        => $total,
                'last_page'    => (int) ceil($total / max(1, (int) $perPage)),
                'has_more'     => ((int) $page * (int) $perPage) < $total
            ]
        ];
    }

    /**
     * Execute query and get results
     */
    public function get($columns = ['*'])
    {
        global $wpdb;

        $this->query['select'] = $this->normalizeSelect($columns);

        $table  = static::table();
        $sql    = "SELECT {$this->query['select']} FROM {$table}";
        $params = [];

        if (!empty($this->query['where'])) {
            [$whereSql, $whereParams] = $this->buildWhereClause();
            $sql .= $whereSql;
            $params = array_merge($params, $whereParams);
        }

        if ($this->query['orderBy']) {
            $orderCol = sanitize_key($this->query['orderBy']['column']);
            $dir      = strtoupper($this->query['orderBy']['direction']) === 'DESC' ? 'DESC' : 'ASC';
            $sql     .= " ORDER BY `{$orderCol}` {$dir}";
        }

        if ($this->query['limit']) {
            $sql     .= " LIMIT %d";
            $params[] = (int) $this->query['limit'];

            if ($this->query['offset']) {
                $sql     .= " OFFSET %d";
                $params[] = (int) $this->query['offset'];
            }
        }

        $cacheKey = $this->cacheKeyForSql($sql, $params);
        $cached = wp_cache_get($cacheKey, $this->cacheGroup);

        if ($cached !== false) {
            $models = array_map(function ($item) {
                return static::hydrate($item);
            }, $cached ?: []);

            $collection = new Collection($models);

            if (!empty($this->with)) {
                foreach ($collection as $model) {
                    $model->loadRelations($this->with);
                }
            }

            return $collection;
        }

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // Reason: Table is internal; all dynamic values are prepared via placeholders. We cache immediately.
        $prepared = !empty($params) ? $wpdb->prepare($sql, $params) : $sql;
        $results  = $wpdb->get_results($prepared, ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        wp_cache_set($cacheKey, $results ?: [], $this->cacheGroup, $this->cacheTtl);

        $models = array_map(function ($item) {
            return static::hydrate($item);
        }, $results ?: []);

        $collection = new Collection($models);

        if (!empty($this->with)) {
            foreach ($collection as $model) {
                $model->loadRelations($this->with);
            }
        }

        return $collection;
    }

    /**
     * Create a new model instance from database attributes
     */
    protected static function hydrate($attributes)
    {
        $model = new static();
        $model->attributes = $attributes;

        if (isset($model->casts)) {
            foreach ($model->casts as $key => $type) {
                if (array_key_exists($key, $attributes)) {
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

    /**
     * Find by primary key
     */
    public static function find($id)
    {
        global $wpdb;

        $instance = new static();
        $table = static::table();

        $cacheKey = $instance->cacheKey('find:' . md5($table . '|' . (int) $id));
        $cachedRow = wp_cache_get($cacheKey, $instance->cacheGroup);

        if ($cachedRow !== false) {
            return !empty($cachedRow) ? static::hydrate($cachedRow) : null;
        }

        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // Reason: Table is internal; id is prepared. We cache immediately.
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id);
        $result = $wpdb->get_row($sql, ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

        wp_cache_set($cacheKey, $result ?: [], $instance->cacheGroup, $instance->cacheTtl);

        if (!$result) {
            return null;
        }

        return static::hydrate($result);
    }

    public function save()
    {
        global $wpdb;
        $table = static::table();

        $attributes = [];
        foreach ($this->fillable as $field) {
            if (isset($this->attributes[$field]) && $this->attributes[$field] !== null && $this->attributes[$field] !== '') {
                $attributes[$field] = $this->attributes[$field];
            }
        }

        if (isset($this->attributes['id'])) {
            $attributes['id'] = $this->attributes['id'];
        }

        foreach ($this->casts as $key => $type) {
            if (isset($attributes[$key])) {
                switch ($type) {
                    case 'json':
                        if (is_array($attributes[$key]) || is_object($attributes[$key])) {
                            $attributes[$key] = wp_json_encode($attributes[$key]);
                        }
                        break;
                    case 'datetime':
                        if ($attributes[$key] instanceof \DateTime) {
                            $attributes[$key] = $attributes[$key]->format('Y-m-d H:i:s');
                        }
                        break;
                    case 'boolean':
                        $attributes[$key] = (int) (bool) $attributes[$key];
                        break;
                }
            }
        }

        if (isset($attributes['id'])) {
            $id = (int) $attributes['id'];
            unset($attributes['id']);

            $updated = $wpdb->update(
                $table,
                $attributes,
                ['id' => $id],
                null,
                ['%d']
            );

            if ($updated !== false) {
                static::bumpCacheVersion();
                return true;
            }

            return false;
        }

        $inserted = $wpdb->insert($table, $attributes);
        if ($inserted) {
            $this->attributes['id'] = (int) $wpdb->insert_id;
            static::bumpCacheVersion();
            return true;
        }

        return false;
    }

    public function __get($key)
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (method_exists($this, $key)) {
            $this->relations[$key] = $this->$key();
            return $this->relations[$key];
        }

        return null;
    }

    public function __set($key, $value)
    {
        if (in_array($key, $this->fillable, true)) {
            $this->attributes[$key] = $value;
        }
    }

    /**
     * Create a new record
     */
    public static function create(array $attributes)
    {
        $model = new static();

        foreach ($attributes as $key => $value) {
            if (in_array($key, $model->fillable, true)) {
                $model->attributes[$key] = $value;
            }
        }

        $model->save();
        return $model;
    }

    /**
     * Delete the current model instance from database
     */
    public function delete()
    {
        global $wpdb;

        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        $id = (int) $this->attributes[$this->primaryKey];

        $result = $wpdb->delete(
            static::table(),
            [$this->primaryKey => $id],
            ['%d']
        );

        if ($result !== false) {
            static::bumpCacheVersion();
        }

        return $result !== false;
    }

    /**
     * Find and delete a record
     */
    public static function findByIdAndDelete($id)
    {
        global $wpdb;

        $result = $wpdb->delete(
            static::table(),
            ['id' => (int) $id],
            ['%d']
        );

        if ($result !== false) {
            static::bumpCacheVersion();
        }

        return $result !== false;
    }

    /**
     * Update a record
     */
    public static function update($id, array $attributes)
    {
        $model = static::find($id);
        if (!$model) {
            return false;
        }

        foreach ($attributes as $key => $value) {
            if (in_array($key, $model->fillable, true)) {
                $model->attributes[$key] = $value;
            }
        }

        $saved = $model->save();
        if ($saved) {
            static::bumpCacheVersion();
        }

        return $saved;
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
     * Build WHERE clause (returns [sql, params]) and prepares once in caller.
     *
     * @return array{0:string,1:array}
     */
    protected function buildWhereClause(): array
    {
        $sqlParts = [];
        $params   = [];

        foreach ($this->query['where'] as $where) {
            $column   = sanitize_key((string) ($where['column'] ?? ''));
            $operator = strtoupper((string) ($where['operator'] ?? '='));
            $value    = $where['value'] ?? null;

            if ($column === '') {
                continue;
            }

            if ($operator === 'IN') {
                $values = is_array($value) ? $value : [$value];
                $values = array_values(array_filter($values, function ($v) {
                    return $v !== null && $v !== '';
                }));

                if (empty($values)) {
                    $sqlParts[] = "1=0";
                    continue;
                }

                $placeholders = implode(', ', array_fill(0, count($values), '%s'));
                $sqlParts[] = "`{$column}` IN ({$placeholders})";

                foreach ($values as $v) {
                    $params[] = (string) $v;
                }

                continue;
            }

            $sqlParts[] = "`{$column}` {$operator} %s";
            $params[]   = (string) $value;
        }

        if (empty($sqlParts)) {
            return ['', []];
        }

        return [" WHERE " . implode(' AND ', $sqlParts), $params];
    }

    /**
     * Normalize select columns safely.
     */
    protected function normalizeSelect($columns): string
    {
        if (is_string($columns)) {
            $columns = trim($columns);
            return ($columns === '*') ? '*' : '`' . sanitize_key($columns) . '`';
        }

        if (!is_array($columns) || empty($columns)) {
            return '*';
        }

        if (count($columns) === 1 && $columns[0] === '*') {
            return '*';
        }

        $safe = [];
        foreach ($columns as $col) {
            $col = (string) $col;
            $key = sanitize_key($col);
            if ($key !== '') {
                $safe[] = "`{$key}`";
            }
        }

        return !empty($safe) ? implode(', ', $safe) : '*';
    }
}
