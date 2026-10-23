# post-app

投稿を一覧・編集・削除できる、小さな Laravel アプリです。Tutorial 13 から 15 まで、このアプリ 1 本を題材に設計・実装・仕組み化を進めます。

## できること

- ユーザー登録・ログイン・ログアウト
- 投稿の一覧表示（投稿者の名前とカテゴリつき）
- 投稿の編集（タイトル・カテゴリ・本文）と削除
- 自分の投稿だけを編集・削除（他人の投稿は編集・削除ボタンが出ず、URL を直接開くと 403 になります）

## フォルダについて

`answers/` は Tutorial 13 の答え合わせ用で、アプリのコードとは関係ありません。設計書の解答例が入っているだけなので、アプリの動きには影響しません。詳しくは `answers/README.md` を見てください。

自分で作る設計書は `docs/` に置きます（Tutorial 13 の中で作ります）。

## 使用技術

| 項目 | 内容 |
|:-----|:-----|
| フレームワーク | Laravel 10 |
| 言語 | PHP |
| データベース | MySQL |
| 実行環境 | Laravel Sail（Docker） |
| 認証 | Laravel Fortify |

PHP と MySQL のバージョンは Sail のコンテナが決めます。手元で確かめるときは `./vendor/bin/sail php -v` と `./vendor/bin/sail mysql --version` を実行してください。

## セットアップ

Docker Desktop（または Docker Engine）を起動してから実行してください。Windows の方は WSL（Ubuntu）のターミナルで実行します。

```bash
# パッケージをインストールする
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install

# 環境ファイルを作る
cp .env.example .env

# Sail を起動する
./vendor/bin/sail up -d

# アプリケーションキーを作る
./vendor/bin/sail artisan key:generate

# テーブルを作り、練習用データを入れる
./vendor/bin/sail artisan migrate --seed
```

ブラウザで `http://localhost` を開くと、トップページが表示されます。

初回起動時は `migrate` で「Connection refused」が出ることがあります。MySQL の起動が終わっていないだけなので、少し待ってからもう一度実行してください。

## 練習用のアカウント

`migrate --seed` で、カテゴリ 3 件（お知らせ・技術メモ・雑記）と、次の 2 人、それぞれの投稿 2 件（計 4 件）が入ります。

| メールアドレス | パスワード | 投稿 |
|:---------------|:-----------|:-----|
| `usera@example.com` | `password` | `/posts/1`・`/posts/2` |
| `userb@example.com` | `password` | `/posts/3`・`/posts/4` |

本物の値は入っていません。すべて練習用のダミーです。

## テスト

```bash
./vendor/bin/sail artisan test
```

## 停止

```bash
./vendor/bin/sail down
```

## 設計のメモ

リプライ機能をなぜそう作ったのか。

### 作らないと決めたもの

- リプライへのリプライ（入れ子）。リプライはポストに対してだけ付ける
- リプライの編集。できるのは投稿と削除だけ

### 選ばなかった作り方

- 本文の140字は、`content` 列の型では守らない。`posts.content` と同じく入力チェックで守る
- 「削除」ボタンを画面で隠すだけにはしない。コントローラーでも `authorize` して、URL を直接開かれても 403 にする
- `PostPolicy` にリプライの判定を足さない。`ReplyPolicy` を別に作り、判定の書き方（`user_id` が本人か）だけ揃えた
- リプライ先のポストは入力チェックの項目にしない。URL の `{post}` で決め、存在しなければ 404 にする

### 知らずに変えると壊れる決めごと

- 本文の上限は `Post::CONTENT_MAX_LENGTH` を使い、ポストの更新と同じルール・同じメッセージにそろえている。変えるときは、ポスト編集画面の残り字数表示も合わせて確認する
- 本文は検証の前に CRLF を LF に置き換えている。外すと、ブラウザが送る改行が2文字に数えられ、140字ちょうどでもエラーになる
- `replies` の `user_id` / `post_id` は外部キーで連動削除にしている。外すと、ポストやユーザーを消したあとにリプライだけが残る
- 詳細画面のリプライは `created_at` を使って新しい順に並べている。この列を使わない形に変えると並びが崩れる
- ポスト詳細・リプライの投稿・リプライの削除の3つのルートは、`auth` ミドルウェアのグループの中に置いている。外に出すと未ログインでも開ける

### 設計書のありか

- `docs/reply.md`（Issue #1）。実装した時点の記録で、そのあとは更新していないので、今のコードと食い違うことがある
