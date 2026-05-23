<?php

declare(strict_types=1);

namespace Padosoft\PriceIntelligence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model: resolves the table name from config('price-intelligence.tables.*')
 * using the static $configKey so host apps can rename tables without subclassing.
 */
abstract class PriceIntelligenceModel extends Model
{
    /**
     * Key inside config('price-intelligence.tables').
     */
    protected static string $configKey = '';

    public function getTable(): string
    {
        if ($this->table !== null) {
            return $this->table;
        }

        $configured = config('price-intelligence.tables.' . static::$configKey);

        return is_string($configured) && $configured !== ''
            ? $configured
            : parent::getTable();
    }
}
