# テストの決まり

- 機能のテストは `tests/Feature/` に置く。
- 実装を変えたら、テストも書いて `./vendor/bin/sail test` で回し、通るまで直して、最後に結果を報告する。
- テストを1クラスだけ回すときは `./vendor/bin/sail test --filter ReplyTest` の形で回す。
- データベースを使うテストには `use RefreshDatabase;` を付ける。
- テストが落ちた状態で作業を終えない。原因が自分の変更以外にあっても直す。
