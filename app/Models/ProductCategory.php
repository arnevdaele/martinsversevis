<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'translations', 'sort_order'])]
class ProductCategory extends Model
{
    use HasTranslations;

    protected array $translatable = ['name'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
