<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;

class PluginSetting extends Model
{
    protected $table = 'plugin_settings';

    protected $fillable = ['plugin_key', 'setting_key', 'encrypted_value'];

    protected $hidden = ['encrypted_value'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'encrypted_value' => 'encrypted',
            'updated_at' => 'datetime',
        ];
    }
}
