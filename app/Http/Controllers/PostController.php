<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with(['user', 'category'])->latest()->latest('id')->get();

        return view('posts.index', compact('posts'));
    }

    public function create()
    {
        $categories = Category::orderBy('id')->get();

        return view('posts.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePost($request);

        $request->user()->posts()->create($validated);

        return redirect()->route('posts.index');
    }

    public function show(Post $post)
    {
        $post->load(['user', 'category']);
        $replies = $post->replies()->with('user')->latest()->latest('id')->get();

        return view('posts.show', compact('post', 'replies'));
    }

    public function edit(Post $post)
    {
        $this->authorize('update', $post);

        $categories = Category::orderBy('id')->get();

        return view('posts.edit', compact('post', 'categories'));
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $post->update($this->validatePost($request));

        return redirect()->route('posts.index');
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()->route('posts.index');
    }

    private function validatePost(Request $request): array
    {
        // ブラウザは改行を CRLF で送るため、1 文字として数えられるよう LF にそろえる
        if (is_string($request->input('content'))) {
            $request->merge([
                'content' => str_replace("\r\n", "\n", $request->input('content')),
            ]);
        }

        return $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:'.Post::CONTENT_MAX_LENGTH,
            'category_id' => 'required|exists:categories,id',
        ], [
            'content.required' => '本文を入力してください。',
            'content.max' => '本文は:max字以内で入力してください。',
        ]);
    }
}
