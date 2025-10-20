# Profscode Translatable

File-based translatable package for Laravel with versioning.

## Installation

```bash
composer require profscode/translatable
```

## USAGE

```bash
use Profscode\Translatable\Translatable;

class Blog extends Model
{
use Translatable;

    protected $fillable = ['name', 'description'];
    protected $translatable = ['name', 'description'];

}
```

```bash
$blog = new Blog();
$blog->name = ['en' => 'Title', 'ar' => 'العنوان'];
$blog->save();

echo $blog->name; // Active locale
echo $blog->getTranslation('name', 'en'); // Manual locale
```
