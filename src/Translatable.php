<?php

namespace Profscode\Translatable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Profscode\Translatable\Models\ProfscodeTranslate;

trait Translatable
{
    protected $_tempFileName;

    public static function bootTranslatable()
    {
        static::saving(function (Model $model) {

            $fileName = $model->getKey() ?: Str::uuid()->toString();

            $model->_tempFileName = $fileName;

            foreach ($model->getTranslatableAttributes() as $attribute) {

                $value = $model->attributes[$attribute] ?? null;

                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }

                if (!is_array($value)) {
                    continue;
                }

                foreach ($value as $locale => $translation) {
                    self::saveToLangFile($model, $attribute, $translation, $locale, $fileName);
                    self::saveToDatabase($model, $attribute, $translation, $locale, $fileName);
                }

                $model->attributes[$attribute] = $fileName;
            }
        });

        static::saved(function (Model $model) {

            if ($model->wasRecentlyCreated) {

                $oldID = $model->_tempFileName;
                $newID = $model->getKey();

                if ($oldID && $newID && $oldID !== $newID) {
                    self::changeFileName($model, $oldID, $newID);
                }
            }

        });
    }

    public function __get($key)
    {
        if (in_array($key, $this->getTranslatableAttributes())) {

            $translated = $this->getTranslation($key);

            if ($translated !== null) {
                return $translated;
            }

            return parent::__get($key);
        }

        return parent::__get($key);
    }

    public function getTranslation(string $key, string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $class = class_basename($this);
        $fileID = $this->getKey();
        if (!$fileID) {
            return null;
        }

        $path = lang_path("$locale/$class/$fileID.php");

        if (File::exists($path)) {
            $translations = include $path;
            return $translations[$key] ?? null;
        }

        return $this->getKey() . " " . $key;
    }

    protected static function saveToDatabase(Model $model, string $key, string $value, string $locale, $id): void
    {
        ProfscodeTranslate::updateOrCreate(
            [
                'translatable_type' => get_class($model),
                'translatable_id' => $id,
                'locale' => $locale,
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }

    protected static function saveToLangFile(Model $model, string $key, string $value, string $locale, string $fileName): void
    {
        $class = class_basename($model);
        $dir = lang_path("$locale/$class");
        $path = "$dir/$fileName.php";

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $translations = File::exists($path) ? include $path : [];

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

    protected static function changeFileName(Model $model, $oldID, $newID)
    {
        $class = class_basename($model);
        $locales = scandir(lang_path());

        foreach ($locales as $loc) {

            if ($loc === '.' || $loc === '..') {
                continue;
            }

            $dir = lang_path("$loc/$class");
            $oldPath = "$dir/$oldID.php";
            $newPath = "$dir/$newID.php";

            if (file_exists($oldPath)) {
                rename($oldPath, $newPath);
            }
        }
        ProfscodeTranslate::where('translatable_id', $oldID)
            ->update(['translatable_id' => $newID]);

    }

    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable')
            ? $this->translatable
            : [];
    }
}
