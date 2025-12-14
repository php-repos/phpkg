<?php

namespace Phpkg\Infra;

/**
 * A simple in-memory cache implementation using the Singleton pattern.
 *
 * This class provides a basic caching mechanism for storing key-value pairs in memory.
 * It uses the Singleton pattern to ensure only one instance exists throughout the application.
 * The cache is useful for storing frequently accessed data, computed results, or temporary values.
 */
class Cache
{
    /** @var self|null The singleton instance of the cache */
    private static ?self $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     *
     * Initializes an empty Map to store cache items.
     *
     * @param array<string, mixed> $items The initial items in the cache
     */
    private function __construct(private array $items)
    {
    }

    /**
     * Gets the singleton instance of the cache.
     *
     * Creates a new instance if one doesn't exist, otherwise returns the existing instance.
     * This ensures only one cache instance exists throughout the application lifecycle.
     *
     * @return self The cache instance
     *
     * @example
     * ```php
     * $cache1 = Cache::load();
     * $cache2 = Cache::load();
     * // $cache1 and $cache2 are the same instance
     * var_dump($cache1 === $cache2); // true
     * ```
     */
    public static function load(): self
    {
        if (self::$instance === null) {
            self::$instance = new self([]);
        }
        return self::$instance;
    }

    /**
     * Stores a value in the cache with the specified key.
     *
     * Adds a new key-value pair to the cache. If the key already exists,
     * the value will be overwritten. The value is stored with additional metadata
     * including the key itself for consistency.
     *
     * @param string $key The unique identifier for the cached value
     * @param mixed $value The value to cache
     * @return static The cache instance for method chaining
     *
     * @example
     * ```php
     * $cache = Cache::load();
     * $cache->set('config:database', [
     *     'host' => 'localhost',
     *     'port' => 3306,
     *     'name' => 'myapp'
     * ]);
     * ```
     */
    public function set(string $key, mixed $value): static
    {
        if (isset($this->items[$key])) {
            throw new \RuntimeException("Key '{$key}' already exists in the cache.");
        }
        
        $this->items[$key] = $value;

        return $this;
    }

    /**
     * Updates an existing value in the cache.
     *
     * Updates the value associated with the specified key. This method assumes
     * the key already exists in the cache. If the key doesn't exist, the behavior
     * depends on the underlying Map implementation.
     *
     * @param string $key The key of the value to update
     * @param mixed $value The new value to store
     * @return static The cache instance for method chaining
     *
     * @example
     * ```php
     * $cache = Cache::load();
     * $cache->set('user:123', ['name' => 'John', 'age' => 30]);
     * $cache->update('user:123', ['name' => 'John', 'age' => 31]);
     * ```
     */
    public function update(string $key, mixed $value): static
    {
        $this->items[$key] = $value;

        return $this;
    }

    /**
     * Retrieves all cached items as an array.
     *
     * Returns a copy of all cached items in array format. The returned array
     * contains the internal structure used by the cache, including both keys
     * and values with metadata.
     *
     * @return array All cached items as an associative array
     *
     * @example
     * ```php
     * $cache = Cache::load();
     * $cache->set('key1', 'value1');
     * $cache->set('key2', 'value2');
     * 
     * $items = $cache->items();
     * // Returns: [
     * //   'key1' => ['key' => 'key1', 'value' => 'value1'],
     * //   'key2' => ['key' => 'key2', 'value' => 'value2']
     * // ]
     * ```
     */
    public function items(): array
    {
        return $this->items;
    }
}
