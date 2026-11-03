# テストの決まり

- 機能のテストは `tests/Feature/` に置く。
- 実装を変えたら、テストも書いて `./vendor/bin/sail artisan test` で回し、通るまで直して、最後に結果を報告する。
- テストを 1 クラスだけ回すときは `./vendor/bin/sail artisan test --filter=ReplyTest` の形で回す。
- データベースを使うテストには `use RefreshDatabase;` を付ける。
- テストが落ちた状態で作業を終えない。原因が自分の変更以外にあっても直す。

## 書き方（既存のテストに揃える）

- メソッド名は日本語（例: `test_いいねすると数が1つ増えて押した状態になる`）。
- `setUp()` で `author` / `other` / `category` / `post` を用意し、操作は private ヘルパー（`makePost` / `like` / `timeline` など）にまとめる。
- テストは `// 見る` `// 送る` `// 消す` `// 変わっていない` の区切りコメントで並べる（設計書の「テスト観点」の見出しと対応）。
- 画面の確認は生 HTML で見る（例: `assertSee('<span class="like-count">0</span>', false)`）。ビューのクラス名や文言を変えるとここが落ちる。
- factory は `UserFactory` だけ。`Post` / `Category` / `Tag` / `Reply` は `::create()` を直書きする。
