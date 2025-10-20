<?php

namespace Profscode\Translate\Models;

use Illuminate\Database\Eloquent\Model;

class ProfscodeTranslate extends Model
{
    protected $table = 'profscode_translates';

    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        'key',
        'value',
    ];

    public function translatable()
    {
        return $this->morphTo();
    }
}
