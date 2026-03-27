<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    public function states()
    {
        return $this->hasMany(State::class);
    }
}
