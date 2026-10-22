<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Support\Settings\EnsuresSingleton;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use EnsuresSingleton;
    use HasAttachments;

    protected $fillable = [
        'singleton_key',
        'name_ar',
        'address_ar',
        'contact_phone',
        'contact_email',
    ];

    protected static function singletonAlreadyExistsMessage(): string
    {
        return 'يُسمح بشركة واحدة فقط في الإصدار الحالي.';
    }

    public function isComplete(): bool
    {
        return filled($this->name_ar);
    }
}
