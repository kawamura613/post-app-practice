<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostEditedLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $category;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
        $this->category = Category::create(['name' => '雑記']);
        $this->post = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $this->category->id,
            'title' => '元のタイトル',
            'content' => '元の本文',
        ]);
    }

    private function update(array $overrides = [], ?Post $post = null)
    {
        return $this->actingAs($this->author)->put(route('posts.update', $post ?? $this->post), array_merge([
            'title' => '直したタイトル',
            'category_id' => $this->category->id,
            'content' => '直した本文',
        ], $overrides));
    }

    public function test_一度も編集していないポストには編集済みが出ない(): void
    {
        $this->actingAs($this->author)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('元のタイトル')
            ->assertDontSee('編集済み');
    }

    public function test_編集して保存するとタイムラインに編集済みが出る(): void
    {
        $this->travel(1)->minutes();

        $this->update()->assertRedirect(route('posts.index'));

        $this->actingAs($this->author)
            ->get(route('posts.index'))
            ->assertSee('直したタイトル')
            ->assertSee('編集済み');
    }

    public function test_編集したポストだけに出てほかのポストには出ない(): void
    {
        $untouched = Post::create([
            'user_id' => $this->author->id,
            'category_id' => $this->category->id,
            'title' => '触っていないタイトル',
            'content' => '触っていない本文',
        ]);

        $this->travel(1)->minutes();

        $this->update();

        $this->assertTrue($this->post->fresh()->isEdited());
        $this->assertFalse($untouched->fresh()->isEdited());

        $response = $this->actingAs($this->author)
            ->get(route('posts.index'))
            // 新しい順なので触っていないポストが先。「編集済み」はヘッダー（タイトルより前）に出る
            ->assertSeeInOrder(['触っていないタイトル', '編集済み', '直したタイトル']);

        $this->assertSame(1, substr_count($response->getContent(), '編集済み'));
    }

    public function test_リプライが付いただけでは編集済みにならない(): void
    {
        $this->travel(1)->minutes();

        $this->actingAs($this->author)
            ->post(route('replies.store', $this->post), ['content' => 'リプライです'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->author)
            ->get(route('posts.index'))
            ->assertDontSee('編集済み');
    }

    public function test_入力チェックで弾かれた編集は編集済みにならない(): void
    {
        $this->travel(1)->minutes();

        $this->update(['content' => ''])->assertSessionHasErrors('content');

        $this->actingAs($this->author)
            ->get(route('posts.index'))
            ->assertDontSee('編集済み');
    }
}
