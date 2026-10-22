<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ポスト / つぶやき投稿アプリ</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Hiragino Sans", "Noto Sans JP", sans-serif; background: #FAF9F6; color: #1A1A1A; font-feature-settings: "palt"; }
        a { text-decoration: none; color: inherit; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 1.2rem 1rem 3rem; }
        .brand { display: inline-flex; align-items: center; gap: 0.6rem; font-weight: 800; font-size: 1.25rem; letter-spacing: -0.02em; margin-bottom: 1rem; }
        .brand .mark { width: 36px; height: 36px; border-radius: 30%; background: linear-gradient(135deg, #FFB03A, #FF7A59); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 800; flex-shrink: 0; }
        .card { background: #fff; border: 1px solid #EFEDE8; border-radius: 22px; overflow: hidden; }
        .card-head { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #EFEDE8; }
        .card-head h1 { font-size: 1rem; font-weight: 800; }
        .card-head a { color: #7A7F85; font-size: 0.8rem; font-weight: 600; }
        .card-head a:hover { color: #1A1A1A; text-decoration: underline; text-underline-offset: 3px; }
        .card-head a:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; border-radius: 4px; }
        .item { display: flex; gap: 0.9rem; padding: 1.1rem 1.25rem; border-bottom: 1px solid #EFEDE8; }
        .avatar { width: 52px; height: 52px; border-radius: 30%; flex-shrink: 0; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; }
        .reply .avatar { width: 42px; height: 42px; font-size: 1rem; }
        .item-body { flex: 1; min-width: 0; }
        .item-head { display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap; }
        .item-head .name { font-weight: 800; font-size: 0.95rem; }
        .item-head .time { color: #9AA0A6; font-size: 0.78rem; font-variant-numeric: tabular-nums; }
        .topic { margin-left: auto; border-radius: 999px; padding: 0.2rem 0.7rem; font-size: 0.7rem; font-weight: 700; }
        .post-title { font-weight: 800; font-size: 0.98rem; margin: 0.35rem 0 0.15rem; }
        .item-text { font-size: 0.92rem; line-height: 1.85; color: #3A3F45; overflow-wrap: anywhere; white-space: pre-wrap; }
        .reply .item-text { margin-top: 0.2rem; color: #1A1A1A; }
        .reply-delete { margin-top: 0.4rem; }
        .reply-delete button { background: none; border: none; padding: 0; color: #9AA0A6; font-size: 0.78rem; font-weight: 600; cursor: pointer; font-family: inherit; }
        .reply-delete button:hover { color: #D6402C; text-decoration: underline; text-underline-offset: 3px; }
        .reply-delete button:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; border-radius: 4px; }
        .section-title { padding: 0.9rem 1.25rem; font-weight: 800; font-size: 0.9rem; background: #FAF9F6; border-bottom: 1px solid #EFEDE8; }
        .empty { color: #7A7F85; text-align: center; padding: 2rem 1rem; border-bottom: 1px solid #EFEDE8; font-size: 0.9rem; }
        .reply-form { padding: 1.2rem 1.25rem 1.5rem; }
        .reply-form label { display: block; color: #6B7178; margin-bottom: 0.6rem; font-size: 0.8rem; font-weight: 700; }
        .reply-form textarea { width: 100%; min-height: 100px; padding: 0.8rem 1rem; border: 1px solid #DEDBD4; border-radius: 14px; font-size: 0.95rem; font-family: inherit; line-height: 1.7; resize: vertical; background: #fff; color: #1A1A1A; }
        .reply-form textarea:focus { outline: none; border-color: #1A1A1A; box-shadow: 0 0 0 3px rgba(232, 121, 43, 0.18); }
        .error { color: #D6402C; font-size: 0.83rem; margin-top: 0.4rem; }
        .btn-primary { margin-top: 1rem; background: #131316; color: #fff; border: none; padding: 0.8rem 1.6rem; border-radius: 14px; font-size: 0.9rem; font-weight: 700; cursor: pointer; font-family: inherit; }
        .btn-primary:hover { background: #2B2B30; }
        .btn-primary:focus-visible { outline: 2px solid #E8792B; outline-offset: 2px; }
    </style>
</head>
<body>
    @php
        // アイコンの色は登録した名前から決まる（crc32 % 18）。タイムラインと同じ並び
        $grad = [
            ['#5B8DEF', '#3F5FD0'],
            ['#E8A23D', '#C97F1B'],
            ['#34B58B', '#1E8A6E'],
            ['#E05252', '#B93245'],
            ['#8E6AE0', '#6748BE'],
            ['#3FB0C9', '#2884A5'],
            ['#F2865C', '#DB5A34'],
            ['#6D7BE0', '#4A55C2'],
            ['#8FAE3E', '#6C8A26'],
            ['#31B183', '#188064'],
            ['#7C93B5', '#56718F'],
            ['#F7A934', '#E8721F'],
            ['#EF6A6A', '#CE3A50'],
            ['#9B6CE8', '#6E48C9'],
            ['#E45FA3', '#C13A85'],
            ['#B58A5C', '#8F653A'],
            ['#55A8E2', '#3380BC'],
            ['#4E9E8E', '#2F7A6B'],
        ];
        $topicTone = [
            'お知らせ' => ['#FFF3DC', '#8A5714'],
            '技術メモ' => ['#E8F1FD', '#2F5AA8'],
            '雑記' => ['#F0F1F3', '#57606A'],
        ];
        [$g1, $g2] = $grad[crc32($post->user->name) % count($grad)];
        [$tBg, $tFg] = $topicTone[$post->category->name] ?? ['#F0F1F3', '#57606A'];
    @endphp
    <div class="wrap">
        <a href="{{ route('posts.index') }}" class="brand"><span class="mark">つ</span>つぶやき投稿アプリ</a>

        <main class="card">
            <div class="card-head">
                <h1>ポスト</h1>
                <a href="{{ route('posts.index') }}">ホームに戻る</a>
            </div>

            <article class="item">
                <div class="avatar" style="background: linear-gradient(135deg, {{ $g1 }}, {{ $g2 }});">{{ mb_substr($post->user->name, 0, 1) }}</div>
                <div class="item-body">
                    <div class="item-head">
                        <span class="name">{{ $post->user->name }}</span>
                        <span class="time">{{ $post->created_at->format('n月j日 H:i') }}</span>
                        <span class="topic" style="background: {{ $tBg }}; color: {{ $tFg }};">{{ $post->category->name }}</span>
                    </div>
                    <p class="post-title">{{ $post->title }}</p>
                    <p class="item-text">{{ $post->content }}</p>
                </div>
            </article>

            <h2 class="section-title">リプライ({{ $replies->count() }})</h2>
            @forelse ($replies as $reply)
                @php [$r1, $r2] = $grad[crc32($reply->user->name) % count($grad)]; @endphp
                <article class="item reply">
                    <div class="avatar" style="background: linear-gradient(135deg, {{ $r1 }}, {{ $r2 }});">{{ mb_substr($reply->user->name, 0, 1) }}</div>
                    <div class="item-body">
                        <div class="item-head">
                            <span class="name">{{ $reply->user->name }}</span>
                            <span class="time">{{ $reply->created_at->format('n月j日 H:i') }}</span>
                        </div>
                        <p class="item-text">{{ $reply->content }}</p>
                        @can('delete', $reply)
                            <form class="reply-delete" action="{{ route('replies.destroy', $reply) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button type="submit">削除</button>
                            </form>
                        @endcan
                    </div>
                </article>
            @empty
                <p class="empty">まだリプライがありません。</p>
            @endforelse

            <form class="reply-form" action="{{ route('replies.store', $post) }}" method="POST">
                @csrf
                <label for="content">リプライする</label>
                <textarea id="content" name="content" required>{{ old('content') }}</textarea>
                @error('content')
                    <p class="error">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn-primary">送信</button>
            </form>
        </main>
    </div>
</body>
</html>
