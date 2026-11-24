<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MfsMerchantConfiguration extends Model
{
    protected $fillable = ['provider', 'mode', 'credentials', 'updated_by'];
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array'];
    }
}
