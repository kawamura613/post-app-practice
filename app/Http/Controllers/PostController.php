<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $tags = Tag::orderBy('id')->get();
        // ?tag= が一覧にないタグ（存在しない ID など）なら絞り込まず全件を出す
        $selectedTag = $tags->firstWhere('id', $request->integer('tag'));

        $posts = Post::with(['user', 'category', 'tags'])
            ->withCount('likedBy as likes_count')
            ->withExists(['likedBy as liked_by_me' => fn ($query) => $query->where('users.id', auth()->id())])
            ->when($selectedTag, fn ($query) => $query->whereHas('tags', fn ($q) => $q->where('tags.id', $selectedTag->id)))
            ->latest()->latest('id')->get();

        return view('posts.index', compact('posts', 'tags', 'selectedTag'));
    }

    public function create()
    {
        $categories = Category::orderBy('id')->get();
        $tags = Tag::orderBy('id')->get();

        return view('posts.create', compact('categories', 'tags'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePost($request);

        $post = $request->user()->posts()->create(Arr::except($validated, 'tag_ids'));
        $post->tags()->sync($validated['tag_ids'] ?? []);

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
        $tags = Tag::orderBy('id')->get();
        $post->load('tags');

        return view('posts.edit', compact('post', 'categories', 'tags'));
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $validated = $this->validatePost($request);

        $post->update(Arr::except($validated, 'tag_ids'));
        $post->tags()->sync($validated['tag_ids'] ?? []);

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
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'integer|exists:tags,id',
        ], [
            'content.required' => '本文を入力してください。',
            'content.max' => '本文は:max字以内で入力してください。',
            'tag_ids.array' => 'タグの選び方が正しくありません。',
            'tag_ids.*.integer' => '選べないタグが含まれています。',
            'tag_ids.*.exists' => '選べないタグが含まれています。',
        ]);
    }
}
