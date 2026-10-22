<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReplyTest extends TestCase
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

    private function makeReply(User $user, string $content, ?Post $post = null, ?string $createdAt = null): Reply
    {
        $reply = Reply::create([
            'user_id' => $user->id,
            'post_id' => ($post ?? $this->post)->id,
            'content' => $content,
        ]);

        if ($createdAt) {
            $reply->forceFill(['created_at' => $createdAt])->save();
        }

        return $reply;
    }

    private function reply(string $content, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->other)
            ->from(route('posts.show', $this->post))
            ->post(route('replies.store', $this->post), ['content' => $content]);
    }

    // 見る

    public function test_ログインしていないと詳細画面は開けない(): void
    {
        $this->get(route('posts.show', $this->post))->assertRedirect(route('login'));
    }

    public function test_詳細画面にポストとそのポストのリプライが出る(): void
    {
        $anotherPost = $this->makePost('別のタイトル', '別の本文');
        $this->makeReply($this->other, 'このポストへの返信');
        $this->makeReply($this->other, '別のポストへの返信', $anotherPost);

        $this->actingAs($this->author)->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('ポストのタイトル')
            ->assertSee('ポストの本文')
            ->assertSee('このポストへの返信')
            ->assertDontSee('別のポストへの返信');
    }

    public function test_別のポストのリプライは出ない(): void
    {
        $anotherPost = $this->makePost('別のタイトル', '別の本文');
        $this->makeReply($this->other, '別のポストだけへの返信', $anotherPost);

        $this->actingAs($this->author)->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertDontSee('別のポストだけへの返信');

        $this->actingAs($this->author)->get(route('posts.show', $anotherPost))
            ->assertOk()
            ->assertSee('別のポストだけへの返信');
    }

    public function test_リプライは新しい順に並ぶ(): void
    {
        $this->makeReply($this->other, '古い返信', null, '2024-01-01 10:00:00');
        $this->makeReply($this->other, '新しい返信', null, '2024-01-02 10:00:00');

        $this->actingAs($this->author)->get(route('posts.show', $this->post))
            ->assertSeeInOrder(['新しい返信', '古い返信']);
    }

    public function test_リプライが1件もなくても詳細画面は開ける(): void
    {
        $this->actingAs($this->author)->get(route('posts.show', $this->post))
            ->assertOk()
            ->assertSee('まだリプライがありません。');
    }

    public function test_存在しないポストの詳細画面は404になる(): void
    {
        $this->actingAs($this->author)->get(route('posts.show', 9999))->assertNotFound();
    }

    // 送る

    public function test_本文を入れて送るとリプライが増え詳細画面に戻る(): void
    {
        $this->reply('いい投稿ですね')->assertRedirect(route('posts.show', $this->post));

        $this->assertDatabaseCount('replies', 1);
        $this->assertDatabaseHas('replies', [
            'user_id' => $this->other->id,
            'post_id' => $this->post->id,
            'content' => 'いい投稿ですね',
        ]);

        $this->actingAs($this->other)->get(route('posts.show', $this->post))->assertSee('いい投稿ですね');
    }

    public function test_本文が空だとエラーになりリプライは増えない(): void
    {
        $this->reply('')
            ->assertRedirect(route('posts.show', $this->post))
            ->assertSessionHasErrors(['content' => '本文を入力してください。']);

        $this->assertDatabaseCount('replies', 0);
    }

    public function test_エラーのとき詳細画面にメッセージが表示される(): void
    {
        $this->reply('');

        $this->actingAs($this->other)->get(route('posts.show', $this->post))
            ->assertSee('本文を入力してください。');
    }

    public function test_140字ちょうどなら送れる(): void
    {
        $content = str_repeat('あ', 140);

        $this->reply($content)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('replies', ['content' => $content]);
    }

    public function test_141字だとエラーになりリプライは増えない(): void
    {
        $this->reply(str_repeat('あ', 141))
            ->assertSessionHasErrors(['content' => '本文は140字以内で入力してください。']);

        $this->assertDatabaseCount('replies', 0);
    }

    public function test_改行を含む140字はcrlfでも送れる(): void
    {
        // 文字 138 字 + 改行 2 つ = 140 字。CRLF のまま数えると 142 字になってしまう
        $this->reply("あ\r\n\r\n".str_repeat('い', 137))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('replies', ['content' => "あ\n\n".str_repeat('い', 137)]);
    }

    public function test_ログインしていないと送れない(): void
    {
        $this->post(route('replies.store', $this->post), ['content' => '返信'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('replies', 0);
    }

    // 消す

    public function test_自分のリプライは消せて詳細画面に戻る(): void
    {
        $reply = $this->makeReply($this->other, '消したい返信');

        $this->actingAs($this->other)->delete(route('replies.destroy', $reply))
            ->assertRedirect(route('posts.show', $this->post));

        $this->assertModelMissing($reply);
    }

    public function test_他人のリプライは消せない(): void
    {
        $reply = $this->makeReply($this->other, '他人の返信');

        $this->actingAs($this->author)->delete(route('replies.destroy', $reply))->assertForbidden();

        $this->assertModelExists($reply);
    }

    public function test_ログインしていないと消せない(): void
    {
        $reply = $this->makeReply($this->other, '返信');

        $this->delete(route('replies.destroy', $reply))->assertRedirect(route('login'));

        $this->assertModelExists($reply);
    }

    public function test_削除ボタンは自分のリプライにだけ出る(): void
    {
        $mine = $this->makeReply($this->other, '自分の返信');
        $theirs = $this->makeReply($this->author, '相手の返信');

        $response = $this->actingAs($this->other)->get(route('posts.show', $this->post));

        $response->assertSee(route('replies.destroy', $mine), false);
        $response->assertDontSee(route('replies.destroy', $theirs), false);
    }

    public function test_ポストを削除するとリプライも消える(): void
    {
        $reply = $this->makeReply($this->other, '返信');

        $this->actingAs($this->author)->delete(route('posts.destroy', $this->post));

        $this->assertModelMissing($reply);
    }

    // 変わっていない

    public function test_タイムラインに返信リンクが出る(): void
    {
        $this->actingAs($this->other)->get(route('posts.index'))
            ->assertOk()
            ->assertSee('<a href="'.route('posts.show', $this->post).'">返信</a>', false);
    }

    public function test_タイムラインの編集と削除は従来どおり本人にだけ出る(): void
    {
        $edit = route('posts.edit', $this->post);

        $this->actingAs($this->author)->get(route('posts.index'))->assertSee($edit, false);
        $this->actingAs($this->other)->get(route('posts.index'))->assertDontSee($edit, false);
    }
}
