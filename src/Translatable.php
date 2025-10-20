<?php

namespace Profscode\Translate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

trait Translatable
{
    public static function bootTranslatable()
    {
        static::saving(function (Model $model) {
            foreach ($model->getTranslatableAttributes() as $attribute) {
                $value = $model->{$attribute};

                if (is_array($value)) {
                    foreach ($value as $locale => $translation) {
                        self::saveToLangFile($model, $attribute, $translation, $locale);
                    }

                    $activeLocale = app()->getLocale();
                    $fallback = config('app.fallback_locale', 'en');
                    $model->{$attribute} = $value[$activeLocale]
                        ?? $value[$fallback]
                        ?? array_values($value)[0];
                }
            }
        });
    }

    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable') ? $this->translatable : [];
    }

    protected static function saveToLangFile(Model $model, string $key, string $value, string $locale): void
    {
        $class = class_basename($model);
        $id = $model->getKey() ?? 'new';
        $dir = lang_path("$locale/$class");
        $path = "$dir/$id.php";

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $translations = [];
        if (File::exists($path)) {
            $translations = include $path;
        }

        if (isset($translations[$key]) && $translations[$key] !== $value) {
            $i = 1;
            while (isset($translations["{$key}_old{$i}"])) {
                $i++;
            }
            $translations["{$key}_old{$i}"] = $translations[$key];
        }

        $translations[$key] = $value;

        $export = "<?php\n\nreturn " . var_export($translations, true) . ";\n";
        File::put($path, $export);
    }

    /**
     * Dosyadan çeviri çek
     */
    public function getTranslation(string $key, string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $class = class_basename($this);
        $id = $this->getKey();
        $path = lang_path("$locale/$class/$id.php");

        if (!File::exists($path)) {
            return null;
        }

        $translations = include $path;

        return $translations[$key] ?? null;
    }

    /**
     * Magic getter: $blog->name direkt çevrilmiş değer döner
     */
    public function __get($key)
    {
        // Önce normal Eloquent alanlarına bak
        if (in_array($key, $this->getTranslatableAttributes())) {
            return $this->getTranslation($key, app()->getLocale());
        }

        return parent::__get($key);
    }
}
