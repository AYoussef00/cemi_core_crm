<?php

namespace App\Models\Concerns;

use App\Support\Documents\DocumentNumber;

trait HasDocumentNumber
{
    abstract public static function documentNumberPrefix(): string;

    public function documentNumber(): string
    {
        return DocumentNumber::format(static::documentNumberPrefix(), (int) $this->getKey());
    }

    public function getDocumentNumberAttribute(): string
    {
        return $this->documentNumber();
    }
}
