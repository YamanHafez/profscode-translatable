# Profscode Translatable for Laravel

Profscode Translatable is an advanced translation system for Laravel Eloquent models.  
It stores translations in **both Lang Files and Database**, supports UUID, automatically renames translation files when IDs change, and preserves full translation history (`name_old1`, `name_old2`, …).

This package is fully compatible with Laravel’s Eloquent lifecycle — **no getAttribute override**, no model breakage, no conflicts.

---

## Features

- Translate any model attribute by adding it to `$translatable`
- Stores translations in:
  - `/lang/{locale}/{Model}/{id}.php`
  - `profscode_translates` database table
- Keeps history of old translations (`key_old1`, `key_old2`, …)
- Automatically renames translation files when model ID changes
- Works with **UUID** or **Auto-Increment ID**
- Returns translated value using `$model->attribute`
- Raw value always accessible using `getRawOriginal()`
- Fallback order:
  1. Lang file
  2. Database
  3. Null
- Zero interference with `getAttribute()` and Eloquent internals
- No need to manually define accessors

---

## Installation

Install via Composer:

```bash
composer require profscode/translatable
```

Run migrations:

```bash
php artisan migrate
```

---

## Usage

### 1. Add the trait to your model:

```php
use Profscode\Translatable\Translatable;

class Blog extends Model
{
    use Translatable;

    protected $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description'];
}
```

---

### 2. Creating a translated model:

```php
Blog::create([
    'name' => [
        'en' => 'Hello',
        'tr' => 'Merhaba',
    ],
    'description' => [
        'en' => 'Description here',
        'tr' => 'Açıklama burada',
    ]
]);
```

This generates:

```
/lang/en/Blog/{id}.php
/lang/tr/Blog/{id}.php
```

And database rows inside `profscode_translates`.

---

### 3. Reading translations:

```php
$blog = Blog::find(1);
echo $blog->name;         // returns translation based on app()->getLocale()
echo $blog->description;
```

---

### 4. Switching locale:

```php
app()->setLocale('en');

echo $blog->name; // English translation
```

---

### 5. Updating translations:

```php
$blog->update([
    'name' => [
        'en' => 'Updated title',
        'tr' => 'Güncellenmiş Başlık'
    ]
]);
```

The package automatically preserves the old value:

```
name_old1 => 'Previous value'
```

Multiple updates:

```
name_old1
name_old2
name_old3
...
```

---

### 6. Get RAW stored filename (ID/UUID):

```php
$blog->getRawOriginal('name');
```

Example output:

```
34
```

---

### 7. Fallback logic:

When reading translation:

1. If lang file exists, return `$translations[$key]`
2. Else, return DB value
3. Else, return `null`

---

## Database Table (`profscode_translates`)

| Column            | Type   | Description           |
| ----------------- | ------ | --------------------- |
| id                | INT    | Primary               |
| translatable_type | String | Model class           |
| translatable_id   | Char   | Works with ID or UUID |
| locale            | String | e.g. “tr”, “en”       |
| key               | String | Attribute name        |
| value             | Text   | Translated content    |

---

## How It Works

### On Create:

- UUID is generated
- Translations saved into lang files + database
- Row inserted
- Real ID is assigned by DB
- UUID → ID rename is triggered
- Final translations stored as:
  `/lang/{locale}/{Model}/{id}.php`

### On Update:

- Old translations are stored as `key_oldX`
- New translation replaces main key
- No rename happens (ID does not change)
- History fully preserved

### On Read:

- Trait intercepts property access using `__get`
- Returns translation based on current locale

---

## Example Lang File

`/lang/tr/Blog/12.php`:

```php
return [
    'name_old1' => 'Eski Başlık',
    'name'      => 'Yeni Başlık',
    'description' => 'Açıklama'
];
```

---

## License

MIT © Profscode

---

## Support

For issues, please open a GitHub Issue.
