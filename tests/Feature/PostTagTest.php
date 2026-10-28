<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTagTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $other;

    private Category $category;

    private Tag $laravel;

    private Tag $php;

    private Tag $mysql;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
        $this->other = User::factory()->create();
        $this->category = Category::create(['name' => '雑記']);
        $this->laravel = Tag::create(['name' => '#Laravel']);
        $this->php = Tag::create(['name' => '#PHP']);
        $this->mysql = Tag::create(['name' => '#MySQL']);
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => '直したタイトル',
            'category_id' => $this->category->id,
            'content' => '直した本文',
        ], $overrides);
    }

    private function timeline(User $user, array $query = [])
    {
        return $this->actingAs($user)->get(route('posts.index', $query));
    }

    private function tagNames(Post $post): array
    {
        return $post->fresh()->tags->pluck('name')->sort()->values()->all();
    }

    // 送る（作成）

    public function test_作成画面にタグの選択肢が出る(): void
    {
        $this->actingAs($this->author)
            ->get(route('posts.create'))
            ->assertOk()
            ->assertSee('name="tag_ids[]"', false)
            ->assertSee('#Laravel')
            ->assertSee('#PHP')
            ->assertSee('#MySQL');
    }

    public function test_タグを選んでポストを作れる(): void
    {
        $this->actingAs($this->author)
            ->post(route('posts.store'), $this->payload(['tag_ids' => [$this->laravel->id, $this->php->id]]))
            ->assertRedirect(route('posts.index'));

        $created = Post::where('title', '直したタイトル')->firstOrFail();
        $this->assertSame(['#Laravel', '#PHP'], $this->tagNames($created));
    }

    public function test_タグを選ばなくてもポストを作れる(): void
    {
        $this->actingAs($this->author)
            ->post(route('posts.store'), $this->payload())
            ->assertRedirect(route('posts.index'));

        $created = Post::where('title', '直したタイトル')->firstOrFail();
        $this->assertSame([], $this->tagNames($created));
    }

    public function test_存在しないタグでは作れず日本語のエラーが出る(): void
    {
        $this->actingAs($this->author)
            ->from(route('posts.create'))
            ->post(route('posts.store'), $this->payload(['tag_ids' => [9999]]))
            ->assertRedirect(route('posts.create'))
            ->assertSessionHasErrors(['tag_ids.0' => '選べないタグが含まれています。']);

        $this->assertDatabaseMissing('posts', ['title' => '直したタイトル']);
    }

    // 送る（編集）

    public function test_編集画面に今付いているタグが選ばれて出る(): void
    {
        $this->post->tags()->attach($this->php->id);

        $html = $this->actingAs($this->author)
            ->get(route('posts.edit', $this->post))
            ->assertOk()
            ->assertSee('#Laravel')
            ->getContent();

        $this->assertMatchesRegularExpression('/value="'.$this->php->id.'" checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$this->laravel->id.'" checked/', $html);
    }

    public function test_編集でタグを付け替えて保存できる(): void
    {
        $this->post->tags()->attach($this->laravel->id);

        $this->actingAs($this->author)
            ->put(route('posts.update', $this->post), $this->payload(['tag_ids' => [$this->php->id, $this->mysql->id]]))
            ->assertRedirect(route('posts.index'));

        $this->assertSame(['#MySQL', '#PHP'], $this->tagNames($this->post));
    }

    public function test_編集でタグを全部外して保存できる(): void
    {
        $this->post->tags()->attach([$this->laravel->id, $this->php->id]);

        $this->actingAs($this->author)
            ->put(route('posts.update', $this->post), $this->payload())
            ->assertRedirect(route('posts.index'));

        $this->assertSame([], $this->tagNames($this->post));
    }

    public function test_存在しないタグでは更新できずタグも変わらない(): void
    {
        $this->post->tags()->attach($this->laravel->id);

        $this->actingAs($this->author)
            ->from(route('posts.edit', $this->post))
            ->put(route('posts.update', $this->post), $this->payload(['tag_ids' => [9999]]))
            ->assertSessionHasErrors(['tag_ids.0' => '選べないタグが含まれています。']);

        $this->assertSame(['#Laravel'], $this->tagNames($this->post));
        $this->assertSame('ポストのタイトル', $this->post->fresh()->title);
    }

    // 見る

    public function test_タイムラインのポストに付いているタグが出る(): void
    {
        $this->post->tags()->attach([$this->laravel->id, $this->php->id]);

        $html = $this->timeline($this->author)->assertOk()->getContent();

        // 絞り込みの欄（上部）ではなく、ポストの中のタグの欄だけを取り出して見る
        $this->assertSame(1, preg_match('/<div class="post-tags">(.*?)<\/div>/s', $html, $matches));
        $this->assertStringContainsString('#Laravel', $matches[1]);
        $this->assertStringContainsString('#PHP', $matches[1]);
        $this->assertStringNotContainsString('#MySQL', $matches[1]);
    }

    public function test_タグが1つも付いていないポストにはタグの欄が出ない(): void
    {
        $this->timeline($this->author)
            ->assertOk()
            ->assertSee('ポストのタイトル')
            ->assertDontSee('class="post-tags"', false);
    }

    public function test_タグが付いたポストにだけタグの欄が出る(): void
    {
        $tagged = $this->makePost('タグ付きのタイトル', 'タグ付きの本文');
        $tagged->tags()->attach($this->mysql->id);

        $html = $this->timeline($this->author)->getContent();

        $this->assertSame(1, substr_count($html, 'class="post-tags"'));
    }

    // 絞る

    public function test_タグを選ぶとそのタグが付いたポストだけが並ぶ(): void
    {
        $withLaravel = $this->makePost('ララベルのタイトル', 'ララベルの本文');
        $withLaravel->tags()->attach($this->laravel->id);
        $withPhp = $this->makePost('ピーエイチピーのタイトル', 'ピーエイチピーの本文');
        $withPhp->tags()->attach($this->php->id);

        $this->timeline($this->other, ['tag' => $this->laravel->id])
            ->assertOk()
            ->assertSee('ララベルのタイトル')
            ->assertDontSee('ピーエイチピーのタイトル')
            ->assertDontSee('ポストのタイトル');
    }

    public function test_複数のタグが付いたポストはどのタグで絞っても出る(): void
    {
        $this->post->tags()->attach([$this->laravel->id, $this->php->id]);

        $this->timeline($this->other, ['tag' => $this->laravel->id])->assertSee('ポストのタイトル');
        $this->timeline($this->other, ['tag' => $this->php->id])->assertSee('ポストのタイトル');
        $this->timeline($this->other, ['tag' => $this->mysql->id])->assertDontSee('ポストのタイトル');
    }

    public function test_該当するポストが無いタグで絞ると案内が出る(): void
    {
        $this->timeline($this->author, ['tag' => $this->mysql->id])
            ->assertOk()
            ->assertSee('このタグが付いたポストはまだありません。');
    }

    public function test_存在しないタグで絞ろうとしても全件が出る(): void
    {
        $this->timeline($this->author, ['tag' => 9999])
            ->assertOk()
            ->assertSee('ポストのタイトル');
    }

    public function test_タグを絞っていないときは全部のポストが出る(): void
    {
        $tagged = $this->makePost('タグ付きのタイトル', 'タグ付きの本文');
        $tagged->tags()->attach($this->laravel->id);

        $this->timeline($this->author)
            ->assertSee('ポストのタイトル')
            ->assertSee('タグ付きのタイトル');
    }

    // 他人

    public function test_他人のポストの編集画面は403になる(): void
    {
        $this->actingAs($this->other)
            ->get(route('posts.edit', $this->post))
            ->assertForbidden();
    }

    public function test_他人のポストにタグを送っても403でタグは変わらない(): void
    {
        $this->post->tags()->attach($this->laravel->id);

        $this->actingAs($this->other)
            ->put(route('posts.update', $this->post), $this->payload(['tag_ids' => [$this->php->id]]))
            ->assertForbidden();

        $this->assertSame(['#Laravel'], $this->tagNames($this->post));
        $this->assertSame('ポストのタイトル', $this->post->fresh()->title);
    }

    // 変わっていない

    public function test_タグを付け替えてもポストの更新日時は動かない(): void
    {
        $this->travel(1)->minutes();

        $this->actingAs($this->author)
            ->put(route('posts.update', $this->post), $this->payload([
                'title' => $this->post->title,
                'content' => $this->post->content,
                'tag_ids' => [$this->laravel->id],
            ]))
            ->assertRedirect(route('posts.index'));

        $this->assertFalse($this->post->fresh()->isEdited());
        $this->timeline($this->author)->assertDontSee('編集済み');
    }

    public function test_ポストを消すとタグの付き方も消えタグ自体は残る(): void
    {
        $this->post->tags()->attach($this->laravel->id);

        $this->actingAs($this->author)
            ->delete(route('posts.destroy', $this->post))
            ->assertRedirect(route('posts.index'));

        $this->assertDatabaseMissing('post_tag', ['post_id' => $this->post->id]);
        $this->assertDatabaseHas('tags', ['id' => $this->laravel->id]);
    }

    public function test_タグがあっても編集と削除は本人にだけ出る(): void
    {
        $this->post->tags()->attach($this->laravel->id);

        // 削除の URL は「返信」リンクと同じなので、ボタンの文言で見る
        $this->timeline($this->author)
            ->assertSee(route('posts.edit', $this->post), false)
            ->assertSee('>削除</button>', false);

        $this->timeline($this->other)
            ->assertDontSee(route('posts.edit', $this->post), false)
            ->assertDontSee('>削除</button>', false);
    }

    public function test_タグがあってもいいねの数は今までどおり出る(): void
    {
        $this->post->tags()->attach($this->laravel->id);
        $this->actingAs($this->other)->post(route('likes.store', $this->post));

        $this->timeline($this->author)
            ->assertSee('<span class="like-count">1</span>', false);
    }
}
