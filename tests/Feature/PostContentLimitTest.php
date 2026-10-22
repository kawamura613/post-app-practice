<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostContentLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
        $category = Category::create(['name' => '雑記']);
        $this->post = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $category->id,
            'title' => '元のタイトル',
            'content' => '元の本文',
        ]);
    }

    private function update(string $content, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->author)->put(route('posts.update', $this->post), [
            'title' => '更新後のタイトル',
            'category_id' => $this->post->category_id,
            'content' => $content,
        ]);
    }

    public function test_140字ちょうどの本文は更新できる(): void
    {
        $content = str_repeat('あ', 140);

        $this->update($content)->assertRedirect(route('posts.index'));

        $this->assertSame($content, $this->post->fresh()->content);
    }

    public function test_141字の本文は日本語のエラーになり保存されない(): void
    {
        $this->update(str_repeat('あ', 141))
            ->assertSessionHasErrors(['content' => '本文は140字以内で入力してください。']);

        $this->assertSame('元の本文', $this->post->fresh()->content);
        $this->assertSame('元のタイトル', $this->post->fresh()->title);
    }

    public function test_空の本文は日本語のエラーになる(): void
    {
        $this->update('')
            ->assertSessionHasErrors(['content' => '本文を入力してください。']);
    }

    public function test_改行を含む140字は改行コードがcrlfでも更新できる(): void
    {
        // 文字 138 字 + 改行 2 つ = 140 字。CRLF のまま数えると 142 字になってしまう
        $this->update("あ\r\n\r\n".str_repeat('い', 137))
            ->assertRedirect(route('posts.index'));

        $this->assertSame("あ\n\n".str_repeat('い', 137), $this->post->fresh()->content);
    }

    public function test_編集画面に残り字数が表示される(): void
    {
        // 元の本文「元の本文」は 4 字
        $this->actingAs($this->author)->get(route('posts.edit', $this->post))
            ->assertOk()
            ->assertSee('残り 136 字');
    }

    public function test_他人の投稿は従来どおり更新できない(): void
    {
        $this->update('短い本文', User::factory()->create())->assertForbidden();

        $this->assertSame('元の本文', $this->post->fresh()->content);
    }
}
