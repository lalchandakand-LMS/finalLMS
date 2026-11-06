<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';

    public $timestamps = true;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function paymentMethods(): array
    {
        $savedMethods = json_decode((string) static::value('payment_methods', ''), true);
        $defaults = [
            'bkash' => ['enabled' => false, 'label' => 'bKash', 'account' => '', 'instructions' => ''],
            'rocket' => ['enabled' => false, 'label' => 'Rocket', 'account' => '', 'instructions' => ''],
            'bank' => ['enabled' => false, 'label' => 'Bank transfer', 'account' => '', 'instructions' => ''],
            'manual' => ['enabled' => false, 'label' => 'Manual payment', 'account' => '', 'instructions' => ''],
        ];

        return is_array($savedMethods) ? array_replace_recursive($defaults, $savedMethods) : $defaults;
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value !== null ? $value : $default;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }
}
