<?php

namespace Profscode\Translatable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Class ProfscodeTranslate
 * 
 * Represents a translation record in the database.
 * This model acts as a backup/searchable index for translations stored in files.
 * 
 * @property string $translatable_type The class name of the translatable model.
 * @property string $translatable_id The ID or UUID of the translatable model instance.
 * @property string $locale The language code (e.g., 'en', 'tr').
 * @property string $key The attribute key being translated.
 * @property string|null $value The translated content.
 * 
 * @package Profscode\Translatable\Models
 */
class ProfscodeTranslate extends Model
{
    /**
     * The table associated with the model.
     * 
     * @var string
     */
    protected $table = 'profscode_translates';

    /**
     * The attributes that are mass assignable.
     * 
     * @var array<int, string>
     */
    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        'key',
        'value',
    ];

    /**
     * Get the parent translatable model.
     * 
     * @return MorphTo
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
