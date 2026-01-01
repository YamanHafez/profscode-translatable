<?php

namespace Profscode\Translatable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Profscode\Translatable\Models\ProfscodeTranslate;

/**
 * Trait Translatable
 * 
 * Provides a file-based and database-backed translation system for Eloquent models.
 * It stores translations in PHP files within the lang/ directory and also keeps a copy in the database.
 * 
 * @package Profscode\Translatable
 */
trait Translatable
{
    /**
     * Temporary holder for the file name (UUID or ID) during the saving process.
     * 
     * @var string|null
     */
    protected $_tempFileName;

    /**
     * Boot the trait and register Eloquent event listeners.
     * 
     * @return void
     */
    public static function bootTranslatable()
    {
        /**
         * Listening to the 'saving' event to handle translatable attributes.
         */
        static::saving(function (Model $model) {
            // Generate a filename based on the model key or a UUID if it's new
            $fileName = $model->getKey() ?: (string) Str::uuid();
            $model->_tempFileName = $fileName;

            // Process each translatable attribute defined in the model
            foreach ($model->getTranslatableAttributes() as $attribute) {
                $value = $model->attributes[$attribute] ?? null;

                // Attempt to decode JSON if the value is a string (possibly coming from a form)
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }

                // If the value is not an array, we skip it (translations should be provided as array: locale => value)
                if (!is_array($value)) {
                    continue;
                }

                // Save each translation to file and database
                foreach ($value as $locale => $translation) {
                    if (!is_null($translation)) {
                        self::saveToLangFile($model, $attribute, $translation, $locale, $fileName);
                        self::saveToDatabase($model, $attribute, $translation, $locale, $fileName);
                    }
                }

                // Replace the actual attribute value with the fileName (which serves as the reference)
                $model->attributes[$attribute] = $fileName;
            }
        });

        /**
         * Listening to the 'saved' event to handle file renaming for newly created models.
         */
        static::saved(function (Model $model) {
            if ($model->wasRecentlyCreated) {
                $oldID = $model->_tempFileName;
                $newID = $model->getKey();

                // If the temporary name differs from the actual ID, rename files/database entries
                if ($oldID && $newID && $oldID !== $newID) {
                    self::changeFileName($model, $oldID, $newID);
                }
            }
        });
    }

    /**
     * Intercept attribute access to provide the translated value.
     * 
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        // If the attribute is translatable, attempt to get its translation
        if (in_array($key, $this->getTranslatableAttributes())) {
            $translated = $this->getTranslation($key);

            if ($translated !== null) {
                return $translated;
            }
        }

        return parent::__get($key);
    }

    /**
     * Retrieve the translation for a given attribute and locale.
     * 
     * @param string $key
     * @param string|null $locale
     * @return string|null
     */
    public function getTranslation(string $key, string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        $class = class_basename($this);
        $fileID = $this->getKey();

        if (!$fileID) {
            return null;
        }

        // 1. Try to get translation from the PHP lang file
        $path = lang_path("$locale/$class/$fileID.php");

        if (File::exists($path)) {
            $translations = include $path;
            if (isset($translations[$key])) {
                return $translations[$key];
            }
        }

        // 2. Fallback: Try to get translation from the database
        $dbTranslation = ProfscodeTranslate::where([
            'translatable_type' => get_class($this),
            'translatable_id' => $fileID,
            'locale' => $locale,
            'key' => $key,
        ])->first();

        if ($dbTranslation) {
            return $dbTranslation->value;
        }

        // 3. Last Fallback: Return null (or a debug string if preferred)
        return null;
    }

    /**
     * Save the translation record to the database.
     * 
     * @param Model $model
     * @param string $key
     * @param string $value
     * @param string $locale
     * @param mixed $id
     * @return void
     */
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

    /**
     * Save the translation to a PHP file in the lang directory.
     * 
     * @param Model $model
     * @param string $key
     * @param string $value
     * @param string $locale
     * @param string $fileName
     * @return void
     */
    protected static function saveToLangFile(Model $model, string $key, string $value, string $locale, string $fileName): void
    {
        $class = class_basename($model);
        $dir = lang_path("$locale/$class");
        $path = "$dir/$fileName.php";

        // Create directory if it doesn't exist
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        // Load existing translations if they exist
        $translations = File::exists($path) ? include $path : [];

        // Versioning logic: if the key exists and the value is different, keep the old version
        if (isset($translations[$key]) && $translations[$key] !== $value) {
            $i = 1;
            while (isset($translations["{$key}_old{$i}"])) {
                $i++;
            }
            $translations["{$key}_old{$i}"] = $translations[$key];
        }

        $translations[$key] = $value;

        // Export as PHP array
        $export = "<?php\n\nreturn " . var_export($translations, true) . ";\n";

        File::put($path, $export);
    }

    /**
     * Rename translation files and update database entries when a model ID changes (e.g., after creation).
     * 
     * @param Model $model
     * @param mixed $oldID
     * @param mixed $newID
     * @return void
     */
    protected static function changeFileName(Model $model, $oldID, $newID)
    {
        $class = class_basename($model);
        $localesDir = lang_path();

        if (!File::isDirectory($localesDir)) {
            return;
        }

        $locales = scandir($localesDir);

        foreach ($locales as $loc) {
            if ($loc === '.' || $loc === '..') {
                continue;
            }

            $dir = lang_path("$loc/$class");
            $oldPath = "$dir/$oldID.php";
            $newPath = "$dir/$newID.php";

            // Rename the physical file
            if (file_exists($oldPath)) {
                rename($oldPath, $newPath);
            }
        }

        // Update references in the database
        ProfscodeTranslate::where('translatable_id', $oldID)
            ->update(['translatable_id' => $newID]);
    }

    /**
     * Search models by translation key and value.
     * 
     * @param string $key The translation key (attribute name)
     * @param string $word The value to search for
     * @param array|null $langs Optional array of locales to restrict the search
     * @return string|null Returns the translatable_id (model ID) of the first match
     */
    public static function whereTranslation($key, $word, $langs = null)
    {
        $query = ProfscodeTranslate::where('translatable_type', static::class)
            ->where('key', $key)
            ->where('value', $word);

        if (!empty($langs)) {
            $query->whereIn('locale', $langs);
        }

        $translation = $query->first();

        return $translation ? $translation->translatable_id : null;
    }

    /**
     * Search models by translation key and multiple values.
     * 
     * @param string $key The translation key (attribute name)
     * @param array $words The values to search for
     * @param array|null $langs Optional array of locales to restrict the search
     * @return array Returns an array of translatable_ids (model IDs)
     */
    public static function whereInTranslation($key, array $words, $langs = null): array
    {
        $query = ProfscodeTranslate::where('translatable_type', static::class)
            ->where('key', $key)
            ->whereIn('value', $words);

        if (!empty($langs)) {
            $query->whereIn('locale', $langs);
        }

        return $query->pluck('translatable_id')->unique()->toArray();
    }

    /**
     * Search models by translation key using partial match (LIKE).
     * 
     * @param string $key The translation key (attribute name)
     * @param string $word The partial value to search for
     * @param array|null $langs Optional array of locales to restrict the search
     * @return array Returns an array of translatable_ids (model IDs)
     */
    public static function whereLikeTranslation($key, $word, $langs = null): array
    {
        $query = ProfscodeTranslate::where('translatable_type', static::class)
            ->where('key', $key)
            ->where('value', 'LIKE', '%' . $word . '%');

        if (!empty($langs)) {
            $query->whereIn('locale', $langs);
        }

        return $query->pluck('translatable_id')->unique()->toArray();
    }

    /**
     * Get the list of translatable attributes for the model.
     * 
     * @return array
     */
    public function getTranslatableAttributes(): array
    {
        return property_exists($this, 'translatable')
            ? $this->translatable
            : [];
    }
}
