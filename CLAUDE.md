# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## このリポジトリ

Laravel の学習用リポジトリ。Tutorial 13〜15 を通して、この投稿アプリ 1 本を題材に設計・実装・仕組み化を進めている。作業は Issue → `docs/design/` の設計書 → 実装 → テスト → PR の順で、書くもの（設計書・コミットメッセージ・画面の文言）はすべて日本語。
コードを触る前に、次の表から対象の設計書を読む。

## 決めごとの在りか

中身はここに写さない。必要になったら開くこと。

| 何が書いてあるか | どこにあるか |
|:--|:--|
| 仕様書（クライアントの要求） | `docs/spec.md`。**変えない** |
| 設計書一式（このアプリの決めごとの正本） | `docs/design/`（構成は `docs/design/README.md`） |
| 画面の見本 | `mockup/`（`index.html` から全画面） |
| 受け入れ条件とテスト観点 | `docs/design/acceptance.md` |

実装と設計書が食い違ったら、**コードではなく `docs/design/` を先に直す**。設計書は提出物の中心で、最後まで実物と一致させる。

## Overview

小さな投稿アプリ（Laravel 10 / PHP 8.1+ / MySQL / Laravel Fortify 認証）。ポストの一覧・作成・編集・削除、ポストへのリプライ、いいね、タグでの絞り込みがある。コミットメッセージは末尾に Issue 番号を付ける（例: `ポストへのリプライ機能を追加 (#1)`）。

## アプリの前提

- 今ある動き（一覧・作成・編集・削除、リプライ、いいね、タグでの絞り込み）を壊さない。
- 利用者に見せるメッセージは日本語。`validate()` の第 2 引数でフィールドごとに指定する（例: `'content.required' => '本文を入力してください。'`）。
- `config/app.php` の `locale` は `en` で `lang/ja` もないため、メッセージを指定していない項目（現状 `title`・`category_id`）は英語の既定文言になる。検証ルールを足すときは日本語メッセージも必ず付ける。

## Commands

実行環境は Laravel Sail（Docker）。`artisan` / `phpunit` / `pint` は `./vendor/bin/sail` 経由で実行する。

```bash
./vendor/bin/sail up -d                      # 起動（http://localhost）
./vendor/bin/sail artisan migrate --seed     # テーブル作成＋練習用データ（usera/userb@example.com, パスワード password）
./vendor/bin/sail artisan test               # 全テスト
./vendor/bin/sail artisan test --filter=ReplyTest                               # クラス単位
./vendor/bin/sail artisan test tests/Feature/ReplyTest.php --filter=test_name    # 1 テスト
./vendor/bin/sail php vendor/bin/pint        # コード整形（Laravel Pint）
./vendor/bin/sail down
```

- ファイルを編集するたび、`.claude/settings.json` の PostToolUse フックが `./vendor/bin/sail artisan test` を全部回し、落ちると編集がブロックされる（sail が無ければ素通り）。先に `./vendor/bin/sail up -d` し、テストが赤い状態を放置しない。
- テストは `RefreshDatabase` を使い、`phpunit.xml` で `DB_DATABASE=testing` に切り替わる。MySQL に `testing` データベースが必要。
- `./vendor/bin/sail artisan migrate`（`migrate:fresh` / `migrate:rollback` も）を回したら、出力のエラーと `./vendor/bin/sail artisan migrate:status` が全部 `Ran` かを確認してから先へ進む。

## Architecture

- ルートは `routes/web.php` に集約。ポスト・リプライ・いいねはすべて `auth` ミドルウェアのグループ内。`/` だけ公開の welcome ページ。
- モデルの関係: `User` 1─n `Post` / `Reply`、`Post` 1─n `Reply`、`Category` 1─n `Post`、`User` n─n `Post`（いいね。中間テーブル `likes`）、`Post` n─n `Tag`（ハッシュタグ。中間テーブル `post_tag`）。中間テーブルはどちらも同じ組は 1 件だけ。タグはシーダーで入れた 5 件から選ぶだけで、作る画面はない。
- 「編集済み」は列を持たず `Post::isEdited()`（`updated_at` > `created_at`）で判定する。そのため、いいねとタグの付け外しでは `posts` の行を更新しない（いいね数は `likes` の行数から数え、タグは `sync()` で中間テーブルだけ動かす）。
- `replies` は `posts` / `users` への外部キーで連動削除（ポストを消すとリプライも消える）。
- 認可は Policy（`PostPolicy` の update/delete、`ReplyPolicy` の delete）。`AuthServiceProvider::$policies` には登録せず Laravel の自動検出に任せ、コントローラーでは `$this->authorize(...)` で呼ぶ。判定は「`user_id` が本人か」のみ。他人のものはビューでボタンを隠し、URL 直打ちは 403。
- 本文の入力チェックは `PostController::validatePost()`（private。`store` と `update` が共用）と `ReplyController::store` で同じルール・同じメッセージを使う（`required|string|max:Post::CONTENT_MAX_LENGTH`、上限 140 字）。ブラウザの CRLF 改行が 2 文字に数えられないよう、検証前に `\r\n` → `\n` へ置換している。上限値は `Post::CONTENT_MAX_LENGTH` を使い、変えるときは残り字数表示（`resources/views/posts/create.blade.php`・`edit.blade.php` の両方）も合わせて確認する。
- ビューが関連モデルを引くときは、コントローラーで `with()` / `load()` を付けて先読みする（N+1 を避ける）。今は `PostController::index` が `Post::with(['user', 'category', 'tags'])`（`?tag=` があればそのタグだけに絞る。一覧にないタグ ID は無視して全件）＋ `withCount`（`likes_count`）＋ `withExists`（`liked_by_me`）、`show` が `$post->load(['user', 'category'])` と `replies()->with('user')`。クエリを足す・直すときは、ビューで使う関連が入っているかを差分で確かめる。
- 一覧のポストも詳細のリプライも新しい順（`latest()->latest('id')`）。
- ビューは `resources/views/{auth,posts}` の Blade。共通レイアウトはなく、各ファイルが `<style>` ごと 1 ファイルで完結している（CSS は画面ごとに重複）。テストはクラス名や文言を生 HTML で見ているので、見た目を直したらテストも確認する。
- 認証は Fortify（`app/Actions/Fortify`、`FortifyServiceProvider`）。

## Docs

- 機能追加の設計は `docs/<機能>.md` として `docs/` の直下に置く。見出しは `docs/reply.md` と `docs/post-create.md` に揃える。先頭に `# 〇〇 設計` と `Issue: #番号「タイトル」`（未作成なら `Issue: 未作成`）を置き、`##` は次の順に並べる。「決めごと」と「確認の手順（手動）」は必要なときだけ入れ、ほかは省かない。
  1. 概要
  2. 受け入れ条件
  3. 決めごと（任意）
  4. 変えないもの
  5. 増えるテーブル
  6. 増える画面
  7. 入力チェックとメッセージ一覧（`###` で「入力チェック表」「メッセージ一覧」に分ける）
  8. テスト観点（`###` で「見る」「送る」「消す」「変わっていない」のように操作ごとに分ける）
  9. 確認の手順（手動）（任意）
- `answers/` は Tutorial 13 の解答例で、アプリのコードとは無関係。読み書きの対象にしない。

## 作業の進め方

### Pull Request

PR の本文に次の 2 つを書く。

- テスト結果: 実行したコマンドと結果。
- 受け入れ条件の突き合わせ: Issue の受け入れ条件を 1 つずつ挙げ、どのテストや確認で満たしたかを対応づける。
