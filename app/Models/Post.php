<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    public const CONTENT_MAX_LENGTH = 140;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'content',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    // 列は足さず、作成後に更新された（updated_at が created_at より後）ポストを編集済みとみなす
    public function isEdited(): bool
    {
        return $this->updated_at->gt($this->created_at);
    }

    // 中間テーブル post_tag。タグはあらかじめ用意されたものから選ぶ（付け外しで posts.updated_at は動かない）
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    // 中間テーブル likes。いいねの数は列に持たず、行数から数える（posts.updated_at を動かさないため）
    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'likes')->withTimestamps();
    }
}
