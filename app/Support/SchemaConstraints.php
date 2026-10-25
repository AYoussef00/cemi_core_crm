<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Inspect existing foreign keys and indexes without Schema::hasForeignKey / Schema::hasIndex.
 *
 * Uses Schema::getForeignKeys() and Schema::getIndexes(), which Laravel 13 compiles
 * from information_schema on MySQL and from PRAGMA on SQLite.
 */
final class SchemaConstraints
{
    public static function foreignKeyExists(string $table, string $column, string $referencedTable): bool
    {
        $column = strtolower($column);
        $referencedTable = strtolower($referencedTable);

        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (self::normalizedColumns($foreignKey['columns'] ?? []) !== [$column]) {
                continue;
            }

            $foreignTable = strtolower((string) ($foreignKey['foreign_table'] ?? ''));
            $schemaParts = explode('.', $foreignTable);
            $foreignTable = (string) end($schemaParts);

            if ($foreignTable === $referencedTable) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $columns
     */
    public static function indexExistsOnColumns(string $table, array $columns): bool
    {
        $expected = self::normalizedColumns($columns);

        foreach (Schema::getIndexes($table) as $index) {
            if (self::normalizedColumns($index['columns'] ?? []) === $expected) {
                return true;
            }
        }

        return false;
    }

    public static function indexExistsByName(string $table, string $name): bool
    {
        $name = strtolower($name);

        foreach (Schema::getIndexes($table) as $index) {
            if (strtolower((string) ($index['name'] ?? '')) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>|string  $columns
     * @return list<string>
     */
    private static function normalizedColumns(array|string $columns): array
    {
        if (is_string($columns)) {
            $columns = $columns === '' ? [] : explode(',', $columns);
        }

        return array_values(array_map(
            static fn (mixed $column): string => strtolower(trim((string) $column)),
            $columns,
        ));
    }
}
