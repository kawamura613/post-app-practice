<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $other;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
        $this->other = User::factory()->create();
        $this->category = Category::create(['name' => '雑記']);
    }

    private function store(array $overrides = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->author)->post(route('posts.store'), array_merge([
            'title' => '新しいタイトル',
            'category_id' => $this->category->id,
            'content' => '新しい本文',
        ], $overrides));
    }

    public function test_タイムラインに書く画面へのリンクが出る(): void
    {
        $this->actingAs($this->author)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('href="'.route('posts.create').'"', false);
    }

    public function test_書く画面にトピックの選択肢が出る(): void
    {
        $this->actingAs($this->author)
            ->get(route('posts.create'))
            ->assertOk()
            ->assertSee('雑記');
    }

    public function test_送るとタイムラインの先頭にポストが増える(): void
    {
        Post::create([
            'user_id' => $this->other->id,
            'category_id' => $this->category->id,
            'title' => '既存のタイトル',
            'content' => '既存の本文',
        ]);

        $this->store()->assertRedirect(route('posts.index'));

        $this->assertDatabaseHas('posts', [
            'user_id' => $this->author->id,
            'category_id' => $this->category->id,
            'title' => '新しいタイトル',
            'content' => '新しい本文',
        ]);

        $this->actingAs($this->author)
            ->get(route('posts.index'))
            ->assertSeeInOrder(['新しいタイトル', '既存のタイトル']);
    }

    public function test_本文が空だとエラーになりポストは増えない(): void
    {
        $this->from(route('posts.create'))
            ->store(['content' => ''])
            ->assertRedirect(route('posts.create'))
            ->assertSessionHasErrors(['content' => '本文を入力してください。']);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_141字だとエラーになりポストは増えない(): void
    {
        $this->store(['content' => str_repeat('あ', Post::CONTENT_MAX_LENGTH + 1)])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_改行を含む140字はcrlfでも送れる(): void
    {
        $content = str_repeat('あ', Post::CONTENT_MAX_LENGTH - 2)."\r\n".'い';

        $this->store(['content' => $content])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_増えたポストに自分の名前と編集削除の操作が出る(): void
    {
        $this->store();
        $post = Post::firstOrFail();

        $this->actingAs($this->author)
            ->get(route('posts.index'))
            ->assertSee($this->author->name)
            ->assertSee('href="'.route('posts.edit', $post).'"', false)
            ->assertSee('action="'.route('posts.destroy', $post).'"', false);
    }

    public function test_リクエストでuser_idを指定しても本人のポストになる(): void
    {
        $this->store(['user_id' => $this->other->id]);

        $this->assertSame($this->author->id, Post::firstOrFail()->user_id);
    }

    public function test_他人のポストは編集画面も削除も403のまま(): void
    {
        $this->store();
        $post = Post::firstOrFail();

        $this->actingAs($this->other)->get(route('posts.edit', $post))->assertForbidden();
        $this->actingAs($this->other)->delete(route('posts.destroy', $post))->assertForbidden();

        $this->actingAs($this->other)
            ->get(route('posts.index'))
            ->assertDontSee('href="'.route('posts.edit', $post).'"', false)
            ->assertDontSee('action="'.route('posts.destroy', $post).'"', false);

        $this->assertDatabaseCount('posts', 1);
    }

    public function test_ログインしていないとログイン画面に飛ばされる(): void
    {
        $this->get(route('posts.create'))->assertRedirect(route('login'));

        $this->post(route('posts.store'), [
            'title' => '新しいタイトル',
            'category_id' => $this->category->id,
            'content' => '新しい本文',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('posts', 0);
    }
}
