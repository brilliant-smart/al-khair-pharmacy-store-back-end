<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'image_url',
        'department_id',
        'is_active',
    ];

    /**
     * Get the route key for the model.
     * Default to 'id' for route binding
     */
    public function getRouteKeyName()
    {
        return 'id';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    protected $appends = ['image_full_url'];

    public function getImageFullUrlAttribute(): ?string
    {
        return $this->image_url
            ? asset('storage/' . $this->image_url)
            : null;
    }

    protected static function booted()
    {
        static::deleting(function ($product) {
            if ($product->image_url) {
                Storage::disk('public')->delete($product->image_url);
            }
        });
    }
}
