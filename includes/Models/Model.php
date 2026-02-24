<?php

namespace Softminal\ImageCraft\Models;

use Softminal\ImageCraft\Support\Collection;

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

    private static array $validOperators = ['=', '!=', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE', 'IN'];

    /**
     * Object cache settings (tune as needed).
     * Group is namespaced to the plugin to avoid collisions in shared caches.
     */
    private string $cacheGroup = 'icais_models';
    private int $cacheTtl = 60; // seconds

    /**
     * Get the full table name with prefix
     *
     * @return string
     */
    public static function table(): string
    {
        global $wpdb;
        $instance = new static();
        return $wpdb->prefix . ICAIS_TABLE_PREFIX . $instance->table;
    }

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
    private function getCacheVersion(): int
    {
        $key = 'model_cache_ver:' . static::table();
        $ver = wp_cache_get($key, $this->cacheGroup);
        return $ver ? (int) $ver : 1;
    }

    private static function bumpCacheVersion(): void
    {
        $instance = new static();
        $key = 'model_cache_ver:' . static::table();
        $ver = (int) wp_cache_get($key, $instance->cacheGroup);
        wp_cache_set($key, $ver + 1, $instance->cacheGroup, DAY_IN_SECONDS);
    }

    private function cacheKey(string $suffix): string
    {
        return $suffix . ':v' . $this->getCacheVersion();
    }

    private function cacheKeyForSql(string $sql, array $params = []): string
    {
        return $this->cacheKey('sql:' . md5(static::table() . '|' . $sql . '|' . wp_json_encode($params)));
    }

    /**
     * Get all records
     */
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    public static function all()
    {
        global $wpdb;

        $instance = new static();
        // Table name escaped via esc_sql()
        $safeTable = esc_sql(static::table());

        $cacheKey = $instance->cacheKey('all:' . md5($safeTable));
        $cached = wp_cache_get($cacheKey, $instance->cacheGroup);

        if ($cached !== false) {
            return new Collection(array_map(function ($item) {
                return static::hydrate($item);
            }, $cached ?: []));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Results cached; table escaped via esc_sql()
        $results = $wpdb->get_results( "SELECT * FROM `{$safeTable}`", ARRAY_A );

        wp_cache_set($cacheKey, $results ?: [], $instance->cacheGroup, $instance->cacheTtl);

        return new Collection(array_map(function ($item) {
            return static::hydrate($item);
        }, $results ?: []));
    }
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

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
        if (!in_array($operator, self::$validOperators, true)) {
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
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    public function paginate($perPage = 15, $page = 1)
    {
        global $wpdb;

        $this->query['limit']  = (int) $perPage;
        $this->query['offset'] = ((int) $page - 1) * (int) $perPage;

        // Table name escaped via esc_sql(), $whereSql uses placeholders
        $safeTable = esc_sql(static::table());
        $params = [];

        // Build WHERE clause
        $whereSql = '';
        if (!empty($this->query['where'])) {
            [$whereSql, $whereParams] = $this->buildWhereClause();
            $params = $whereParams;
        }

        $cacheKey = $this->cacheKeyForSql("COUNT:{$safeTable}{$whereSql}", $params);
        $cachedTotal = wp_cache_get($cacheKey, $this->cacheGroup);

        if ($cachedTotal === false) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Results are cached below
            if (!empty($params)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery
                $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$safeTable}`" . $whereSql, $params ) );
            } else {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
                $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$safeTable}`" . $whereSql );
            }
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
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

    /**
     * Execute query and get results
     */
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    public function get($columns = ['*'])
    {
        global $wpdb;

        // All dynamic SQL components are properly escaped:
        // - $selectClause: via normalizeSelect() which uses esc_sql(sanitize_key())
        // - $safeTable: via esc_sql()
        // - $orderCol: via esc_sql(sanitize_key())
        // - $dir: validated to be exactly 'DESC' or 'ASC'
        // - $whereSql: built with placeholders, values passed to prepare()
        // - $limitSql: uses %d placeholders only

        $selectClause = $this->normalizeSelect($columns);
        $safeTable = esc_sql(static::table());
        $params = [];

        // Build WHERE clause
        $whereSql = '';
        if (!empty($this->query['where'])) {
            [$whereSql, $whereParams] = $this->buildWhereClause();
            $params = array_merge($params, $whereParams);
        }

        // Build ORDER BY clause
        $orderSql = '';
        if ($this->query['orderBy']) {
            $orderCol = esc_sql(sanitize_key($this->query['orderBy']['column']));
            $dir = strtoupper($this->query['orderBy']['direction']) === 'DESC' ? 'DESC' : 'ASC';
            $orderSql = " ORDER BY `{$orderCol}` {$dir}";
        }

        // Build LIMIT/OFFSET clause
        $limitSql = '';
        if ($this->query['limit']) {
            $limitSql .= ' LIMIT %d';
            $params[] = (int) $this->query['limit'];

            if ($this->query['offset']) {
                $limitSql .= ' OFFSET %d';
                $params[] = (int) $this->query['offset'];
            }
        }

        $baseSql = "SELECT {$selectClause} FROM `{$safeTable}`{$whereSql}{$orderSql}{$limitSql}";

        $cacheKey = $this->cacheKeyForSql($baseSql, $params);
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Results are cached below
        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- $baseSql built from escaped components, placeholders filled by $params, WordPress.DB.DirectDatabaseQuery.DirectQuery
            $results = $wpdb->get_results( $wpdb->prepare( $baseSql, $params ), ARRAY_A );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery -- $baseSql built from escaped components, no user values, WordPress.DB.DirectDatabaseQuery.DirectQuery
            $results = $wpdb->get_results( $baseSql, ARRAY_A );
        }

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
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

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
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    public static function find($id)
    {
        global $wpdb;

        $instance = new static();
        // Table name escaped via esc_sql()
        $safeTable = esc_sql(static::table());

        $cacheKey = $instance->cacheKey('find:' . md5($safeTable . '|' . (int) $id));
        $cachedRow = wp_cache_get($cacheKey, $instance->cacheGroup);

        if ($cachedRow !== false) {
            return !empty($cachedRow) ? static::hydrate($cachedRow) : null;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Results cached; table escaped via esc_sql()
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$safeTable}` WHERE id = %d", (int) $id ), ARRAY_A );

        wp_cache_set($cacheKey, $result ?: [], $instance->cacheGroup, $instance->cacheTtl);

        if (!$result) {
            return null;
        }

        return static::hydrate($result);
    }
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    public function save()
    {
        global $wpdb;
        // Table name escaped via esc_sql()
        $safeTable = esc_sql(static::table());

        $attributes = [];
        foreach ($this->fillable as $field) {
            if (array_key_exists($field, $this->attributes) && $this->attributes[$field] !== null) {
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

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache invalidated via bumpCacheVersion
            $updated = $wpdb->update(
                $safeTable,
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache invalidated via bumpCacheVersion
        $inserted = $wpdb->insert($safeTable, $attributes);
        if ($inserted) {
            $this->attributes['id'] = (int) $wpdb->insert_id;
            static::bumpCacheVersion();
            return true;
        }

        return false;
    }
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

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
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    public function delete()
    {
        global $wpdb;

        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        $id = (int) $this->attributes[$this->primaryKey];
        // Table name escaped via esc_sql()
        $safeTable = esc_sql(static::table());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache invalidated via bumpCacheVersion
        $result = $wpdb->delete(
            $safeTable,
            [$this->primaryKey => $id],
            ['%d']
        );

        if ($result !== false) {
            static::bumpCacheVersion();
        }

        return $result !== false;
    }
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

    /**
     * Find and delete a record
     */
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    public static function findByIdAndDelete($id)
    {
        global $wpdb;
        // Table name escaped via esc_sql()
        $safeTable = esc_sql(static::table());

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache invalidated via bumpCacheVersion
        $result = $wpdb->delete(
            $safeTable,
            ['id' => (int) $id],
            ['%d']
        );

        if ($result !== false) {
            static::bumpCacheVersion();
        }

        return $result !== false;
    }
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

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
     * Column names are escaped via esc_sql(sanitize_key()), values use placeholders.
     *
     * @return array{0:string,1:array}
     */
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    private function buildWhereClause(): array
    {
        $sqlParts = [];
        $params   = [];

        foreach ($this->query['where'] as $where) {
            // Column escaped via esc_sql(sanitize_key())
            $column   = esc_sql(sanitize_key((string) ($where['column'] ?? '')));
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
                    $sqlParts[] = '1=0';
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

        return [' WHERE ' . implode(' AND ', $sqlParts), $params];
    }
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

    /**
     * Normalize select columns safely.
     * All column names are escaped via esc_sql(sanitize_key()).
     */
    // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
    private function normalizeSelect($columns): string
    {
        if (is_string($columns)) {
            $columns = trim($columns);
            return ($columns === '*') ? '*' : '`' . esc_sql(sanitize_key($columns)) . '`';
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
            // Column escaped via esc_sql(sanitize_key())
            $key = esc_sql(sanitize_key($col));
            if ($key !== '') {
                $safe[] = "`{$key}`";
            }
        }

        return !empty($safe) ? implode(', ', $safe) : '*';
    }
    // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
}
