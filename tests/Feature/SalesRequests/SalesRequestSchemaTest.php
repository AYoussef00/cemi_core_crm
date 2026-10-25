<?php

namespace Tests\Feature\SalesRequests;

use App\Support\SchemaConstraints;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesRequestSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_origin_visit_id_uses_short_named_constraints(): void
    {
        $this->assertTrue(Schema::hasColumn('sales_requests', 'origin_visit_id'));
        $this->assertTrue(SchemaConstraints::indexExistsOnColumns('sales_requests', ['origin_visit_id']));
        $this->assertTrue(SchemaConstraints::foreignKeyExists(
            'sales_requests',
            'origin_visit_id',
            'customer_visits',
        ));
        $this->assertSame(
            'restrict',
            $this->foreignKeyOn('sales_requests', 'origin_visit_id')['on_delete'],
        );

        foreach (Schema::getIndexes('sales_requests') as $index) {
            $this->assertLessThanOrEqual(64, strlen((string) $index['name']), 'sales_requests index '.$index['name']);
        }

        foreach (Schema::getForeignKeys('sales_requests') as $foreignKey) {
            if (is_string($foreignKey['name']) && $foreignKey['name'] !== '') {
                $this->assertLessThanOrEqual(64, strlen($foreignKey['name']), 'sales_requests fk '.$foreignKey['name']);
            }
        }
    }

    public function test_origin_visit_id_migration_can_rerun_when_column_already_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('sales_requests', 'origin_visit_id'));

        $migration = require database_path('migrations/2026_10_25_211901_add_origin_visit_id_to_sales_requests_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('sales_requests', 'origin_visit_id'));
        $this->assertTrue(SchemaConstraints::indexExistsOnColumns('sales_requests', ['origin_visit_id']));
        $this->assertSame(
            'restrict',
            $this->foreignKeyOn('sales_requests', 'origin_visit_id')['on_delete'],
        );
        $this->assertCount(
            1,
            array_filter(
                Schema::getForeignKeys('sales_requests'),
                fn (array $foreignKey): bool => $foreignKey['columns'] === ['origin_visit_id'],
            ),
        );
    }

    /**
     * @return array{name: string|null, columns: list<string>, foreign_table: string, on_delete: string|null}|array{}
     */
    private function foreignKeyOn(string $table, string $column): array
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if ($foreignKey['columns'] === [$column]) {
                return $foreignKey;
            }
        }

        return [];
    }
}
