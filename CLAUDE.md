# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

小さな投稿アプリ（Laravel 10 / PHP 8.1+ / MySQL / Laravel Fortify 認証）。ポストの一覧・編集・削除と、ポストへのリプライ機能がある。README・設計書・コミットメッセージは日本語で書かれており、コミットメッセージは末尾に Issue 番号を付ける形式（例: `ポストへのリプライ機能を追加 (#1)`）。

## Commands

実行環境は Laravel Sail（Docker）。`.env` は MySQL 前提なので、`artisan` / `phpunit` / `pint` は `./vendor/bin/sail` 経由で実行する。

```bash
./vendor/bin/sail up -d                      # 起動（http://localhost）
./vendor/bin/sail artisan migrate --seed     # テーブル作成＋練習用データ（usera/userb@example.com, パスワード password）
./vendor/bin/sail artisan test               # 全テスト
./vendor/bin/sail artisan test --filter=ReplyTest                 # クラス単位
./vendor/bin/sail artisan test tests/Feature/ReplyTest.php --filter=test_name   # 1 テスト
./vendor/bin/sail php vendor/bin/pint        # コード整形（Laravel Pint）
./vendor/bin/sail down
```

テストは `RefreshDatabase` を使い、`phpunit.xml` で `DB_DATABASE=testing` に切り替わる。MySQL に `testing` データベースが必要。

## Architecture

- ルートは `routes/web.php` に集約。ポスト・リプライ関連はすべて `auth` ミドルウェアのグループ内（未ログインは見られない）。`/` だけ公開の welcome ページ。
- モデルの関係: `User` 1─n `Post` / `Reply`、`Post` 1─n `Reply`、`Category` 1─n `Post`。`replies` は `posts` / `users` への外部キーで連動削除（ポストを消すとリプライも消える）。
- 認可は Policy（`PostPolicy` の update/delete、`ReplyPolicy` の delete）。`AuthServiceProvider::$policies` には登録しておらず、Laravel の自動検出に任せている。コントローラーでは `$this->authorize(...)` で呼ぶ。判定は「`user_id` が本人か」のみ。他人のものはビューでボタンを隠し、URL 直打ちは 403。
- 本文の入力チェックは `PostController::update` と `ReplyController::store` で同じルール・同じメッセージを使う（`required|string|max:Post::CONTENT_MAX_LENGTH`、上限 140 字）。ブラウザの CRLF 改行が 2 文字に数えられないよう、検証前に `\r\n` → `\n` へ置換している。上限値を変えるときは `Post::CONTENT_MAX_LENGTH` を使い、ビューの残り字数表示（`resources/views/posts/edit.blade.php`）も合わせて確認する。
- ポスト詳細（`PostController::show`）のリプライは新しい順（`latest()->latest('id')`）。認証は Fortify（`app/Actions/Fortify`、`FortifyServiceProvider`）、ビューは `resources/views/{auth,posts}` の Blade。

## Docs

- `docs/` は自分で作る設計書置き場（機能一覧、画面一覧、入力チェック表、メッセージ一覧など）。機能追加の設計は `docs/reply.md` のように Issue 番号を書いて置く。リプライ機能の仕様（作らないもの: 入れ子リプライ・リプライ編集）は `docs/reply.md` が正。
- `answers/` は Tutorial 13 の解答例で、アプリのコードとは無関係。読み書きの対象にしない。
