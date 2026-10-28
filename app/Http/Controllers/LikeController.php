<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function store(Request $request, Post $post)
    {
        // 連打や二重送信でも 1 人 1 件のまま。posts の行は更新しない（updated_at が動くと「編集済み」になる）
        $post->likedBy()->syncWithoutDetaching([$request->user()->id]);

        return redirect()->route('posts.index');
    }

    public function destroy(Request $request, Post $post)
    {
        $post->likedBy()->detach($request->user()->id);

        return redirect()->route('posts.index');
    }
}
