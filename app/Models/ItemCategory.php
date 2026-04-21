<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    protected $fillable = ['keyword', 'category', 'source', 'confidence'];

    protected $casts = [
        'confidence' => 'integer',
    ];

    /**
     * Find a category by normalized keyword.
     * Returns 'house', 'personal', or null.
     */
    public static function findCategory(string $normalizedKeyword): ?string
    {
        // Exact match first
        $match = static::where('keyword', $normalizedKeyword)->first();
        if ($match) {
            return $match->category;
        }

        // Keyword is a substring of the item name
        $match = static::whereRaw('? LIKE CONCAT(\'%\', keyword, \'%\')', [$normalizedKeyword])->first();
        if ($match) {
            return $match->category;
        }

        // Item name is a substring of a longer keyword
        $match = static::whereRaw('keyword LIKE ?', ["%{$normalizedKeyword}%"])->first();

        return $match?->category;
    }
}
