<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $other;

    private Category $category;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
        $this->other = User::factory()->create();
        $this->category = Category::create(['name' => '雑記']);
        $this->post = $this->makePost('ポストのタイトル', 'ポストの本文');
    }

    private function makePost(string $title, string $content): Post
    {
        return Post::create([
            'user_id' => $this->author->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'content' => $content,
        ]);
    }

    private function like(User $user, ?Post $post = null)
    {
        return $this->actingAs($user)->post(route('likes.store', $post ?? $this->post));
    }

    private function unlike(User $user, ?Post $post = null)
    {
        return $this->actingAs($user)->delete(route('likes.destroy', $post ?? $this->post));
    }

    private function timeline(User $user)
    {
        return $this->actingAs($user)->get(route('posts.index'));
    }

    // 見る

    public function test_タイムラインのポストにいいねのボタンと数が出る(): void
    {
        $this->timeline($this->other)
            ->assertOk()
            ->assertSee('class="like-btn"', false)
            ->assertSee('<span class="like-count">0</span>', false);
    }

    public function test_いいねの数はそのポストへのいいねの合計が出る(): void
    {
        $anotherPost = $this->makePost('別のタイトル', '別の本文');
        $this->post->likedBy()->attach([$this->author->id, $this->other->id]);
        $anotherPost->likedBy()->attach($this->other->id);

        $this->timeline($this->author)
            ->assertSeeInOrder([
                '<span class="like-count">1</span>', // 新しい順なので別のポストが先
                '<span class="like-count">2</span>',
            ], false);
    }

    public function test_自分がいいねしたポストは押した状態で出る(): void
    {
        $this->post->likedBy()->attach($this->other->id);

        $this->timeline($this->other)
            ->assertSee('class="like-btn liked"', false)
            ->assertSee('aria-pressed="true"', false);
    }

    public function test_ログインしていないとタイムラインは開けない(): void
    {
        $this->get(route('posts.index'))->assertRedirect(route('login'));
    }

    // いいねする

    public function test_いいねすると数が1つ増えて押した状態になる(): void
    {
        $this->like($this->other)->assertRedirect(route('posts.index'));

        $this->assertDatabaseCount('likes', 1);
        $this->assertDatabaseHas('likes', [
            'user_id' => $this->other->id,
            'post_id' => $this->post->id,
        ]);

        $this->timeline($this->other)
            ->assertSee('<span class="like-count">1</span>', false)
            ->assertSee('class="like-btn liked"', false)
            ->assertSee('aria-pressed="true"', false);
    }

    public function test_二重に送っても1人1件のまま(): void
    {
        $this->like($this->other);
        $this->like($this->other)->assertRedirect(route('posts.index'));

        $this->assertDatabaseCount('likes', 1);
    }

    public function test_自分のポストにもいいねできる(): void
    {
        $this->like($this->author)->assertRedirect(route('posts.index'));

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->author->id,
            'post_id' => $this->post->id,
        ]);
    }

    public function test_ログインしていないといいねできない(): void
    {
        $this->post(route('likes.store', $this->post))->assertRedirect(route('login'));

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_存在しないポストにはいいねできない(): void
    {
        $this->actingAs($this->other)->post(route('likes.store', 9999))->assertNotFound();

        $this->assertDatabaseCount('likes', 0);
    }

    // いいねを取り消す

    public function test_もう一度押すと数が1つ減って押していない状態に戻る(): void
    {
        $this->like($this->other);
        $this->unlike($this->other)->assertRedirect(route('posts.index'));

        $this->assertDatabaseCount('likes', 0);

        $this->timeline($this->other)
            ->assertSee('<span class="like-count">0</span>', false)
            ->assertSee('class="like-btn"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertDontSee('like-btn liked', false);
    }

    public function test_いいねしていない人が取り消しても他の人のいいねは消えない(): void
    {
        $this->like($this->author);
        $this->unlike($this->other)->assertRedirect(route('posts.index'));

        $this->assertDatabaseCount('likes', 1);
        $this->assertDatabaseHas('likes', [
            'user_id' => $this->author->id,
            'post_id' => $this->post->id,
        ]);
    }

    public function test_ログインしていないといいねを取り消せない(): void
    {
        $this->post->likedBy()->attach($this->other->id);

        $this->delete(route('likes.destroy', $this->post))->assertRedirect(route('login'));

        $this->assertDatabaseCount('likes', 1);
    }

    // 別のアカウントから見る

    public function test_別のアカウントでは押していない見た目のまま数だけが増えている(): void
    {
        $this->like($this->other);

        $this->timeline($this->author)
            ->assertSee('<span class="like-count">1</span>', false)
            ->assertSee('class="like-btn"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertDontSee('like-btn liked', false);
    }

    public function test_押した人が増えても自分の押した状態は自分のぶんだけで決まる(): void
    {
        $this->like($this->author);
        $this->like($this->other);

        $this->timeline($this->author)
            ->assertSee('<span class="like-count">2</span>', false)
            ->assertSee('aria-pressed="true"', false);

        $this->unlike($this->author);

        $this->timeline($this->other)
            ->assertSee('<span class="like-count">1</span>', false)
            ->assertSee('aria-pressed="true"', false);
    }

    // 変わっていない

    public function test_いいねしてもポストの更新日時は動かない(): void
    {
        $this->travel(1)->hours();
        $before = $this->post->fresh()->updated_at;

        $this->travel(1)->hours();
        $this->like($this->other);
        $this->unlike($this->other);
        $this->like($this->author);

        $this->assertTrue($this->post->fresh()->updated_at->equalTo($before));
    }

    public function test_ポストを削除するといいねも消える(): void
    {
        $this->post->likedBy()->attach([$this->author->id, $this->other->id]);

        $this->actingAs($this->author)->delete(route('posts.destroy', $this->post));

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_いいねがあっても返信編集削除のリンクは今までどおり出る(): void
    {
        $this->post->likedBy()->attach($this->other->id);

        $this->timeline($this->author)
            ->assertSee(route('posts.show', $this->post), false)
            ->assertSee(route('posts.edit', $this->post), false)
            ->assertSee('action="'.route('posts.destroy', $this->post).'"', false);
    }

    public function test_いいねがあってもポストの編集と更新が今までどおり動く(): void
    {
        $this->post->likedBy()->attach($this->other->id);

        $this->actingAs($this->author)->put(route('posts.update', $this->post), [
            'title' => '更新後のタイトル',
            'content' => '更新後の本文',
            'category_id' => $this->category->id,
        ])->assertRedirect(route('posts.index'));

        $this->assertDatabaseHas('posts', ['id' => $this->post->id, 'title' => '更新後のタイトル']);
        $this->assertDatabaseCount('likes', 1);
    }
}
