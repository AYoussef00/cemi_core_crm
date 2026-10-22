<?php

namespace Tests\Unit\Support\Documents;

use App\Support\Documents\DocumentNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentNumberTest extends TestCase
{
    #[DataProvider('formatsProvider')]
    public function test_formats_from_id_starting_at_100(string $method, int $id, string $expected): void
    {
        $this->assertSame($expected, DocumentNumber::{$method}($id));
    }

    /**
     * @return list<array{0: string, 1: int, 2: string}>
     */
    public static function formatsProvider(): array
    {
        return [
            ['quotation', 1, 'QA-000000100'],
            ['quotation', 2, 'QA-000000101'],
            ['purchaseOrder', 1, 'PO-000000100'],
            ['invoice', 1, 'INV-000000100'],
            ['deliveryNote', 1, 'DN-000000100'],
            ['invoice', 42, 'INV-000000141'],
        ];
    }

    public function test_doc_no_helper(): void
    {
        $this->assertSame('QA-000000100', doc_no('QA', 1));
        $this->assertSame('—', doc_no('QA', null));
        $this->assertSame('—', doc_no('QA', 0));
    }
}
