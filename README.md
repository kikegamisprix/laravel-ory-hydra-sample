# laravel-ory-hydra-sample

Laravel 12 のアプリを IdP（ユーザー台帳とログイン画面）にし、Ory Hydra を OIDC プロバイダとして前に置いて、2 種類のクライアントに SSO を提供するサンプルです。

- OIDC 対応済みのクライアント（PHP 8）は Hydra と直接通信する
- OIDC 非対応のレガシーアプリ（PHP 5.6）は、PHP 8 の認証ブローカーを経由して同じログインを使う

ローカルの Docker だけで完結します。クラウドのアカウントは不要です。

## 構成

```
ブラウザ
  │
  ├─ localhost:8082  rp-modern   PHP 8.3。OIDC クライアント（jumbojett/openid-connect-php）
  ├─ localhost:8084  rp-legacy   PHP 5.6。OIDC 非対応。ブローカーに委ねる
  ├─ localhost:8083  broker      PHP 8.3。OIDC を代行し、一回限りの引換コードを rp-legacy に渡す
  ├─ localhost:8081  idp         Laravel 12。Hydra のログイン / 同意プロバイダ。ユーザー台帳を持つ
  └─ localhost:4444  hydra       Ory Hydra v26.2.0。OIDC のプロトコル処理（PostgreSQL 16）
```

| ディレクトリ | 役割 |
|---|---|
| `hydra/` | Hydra の設定。秘密値は compose の環境変数で渡す |
| `idp/` | Laravel 12。`/login` と `/consent` で Hydra Admin API を呼ぶ。`app/Http/Controllers/Hydra/`、`app/Services/HydraAdmin.php` |
| `rp-modern/` | OIDC 対応済みクライアント。`oidc.php` と `public/*.php` |
| `broker/` | 認証ブローカー。`public/start.php`（開始）、`callback.php`（Hydra から戻る）、`exchange.php`（サーバー間で引換） |
| `rp-legacy/` | PHP 5.6 のアプリ。`public/callback.php` が引換コードをユーザー情報に変換してセッションを作る |
| `scripts/` | 秘密値の生成と Hydra へのクライアント登録 |

## 動かす

前提: Docker Desktop（compose v2）、openssl。

```sh
./scripts/setup.sh            # .env を生成。デモユーザーのパスワードが表示される
docker compose up -d --build  # 初回は composer install が走るので数分かかる
./scripts/create-clients.sh   # Hydra に rp-modern と broker を登録し、各 .env に client_id / secret を書く
```

### 確認シナリオ

1. http://localhost:8082/ （rp-modern）で「Hydra 経由でログイン」を押す。Laravel のログイン画面（localhost:8081）に飛ぶので、`setup.sh` が表示したデモユーザーでログインする。戻ると ID トークンの `sub` / `email` / `name` が表示される
2. http://localhost:8084/ （rp-legacy）で「ブローカー経由でログイン」を押す。1 でログイン済みなので、ログイン画面を経ずに PHP 5.6 側のセッションにユーザーが入る

2 が通れば、OIDC 非対応のアプリが同じ IdP で SSO できています。

### 片付け

```sh
docker compose down -v   # DB と vendor のボリュームも消す
```

## 流れ

### rp-modern（OIDC 対応済み）

1. `login.php` が state / nonce / PKCE を生成して Hydra の `/oauth2/auth` へリダイレクト
2. Hydra が `login_challenge` を付けて idp の `/login` へリダイレクト
3. idp が Admin API でリクエストを取得し、ログイン画面を出す。認証できたら `PUT /admin/oauth2/auth/requests/login/accept` で `subject` を返す
4. Hydra が `consent_challenge` を付けて idp の `/consent` へ。クライアントに `skip_consent` が付いているので画面は出さず、`grant_scope` と ID トークンの claim を付けて受理する
5. Hydra が `code` を `callback.php` に返し、rp-modern が `/oauth2/token` でトークンに交換して ID トークンを検証する

### rp-legacy（OIDC 非対応）

1. rp-legacy の `login.php` が `state` を生成してセッションに置き、broker の `start.php?return_to=...&state=...` へ。broker は戻り先からアプリを特定（`BROKER_APPS` の `return_to` と完全一致）して `state` と一緒にセッションに保存し、上の 1〜4 と同じ流れを実行する
2. broker の `callback.php` がトークン交換と ID トークン検証を済ませ、発行先アプリと `state` のハッシュに紐づく 60 秒・一回限りの引換コードを発行し、預かった `state` と一緒に、自動送信の POST フォームで rp-legacy の `callback.php` に渡す（URL に載せないので、アクセスログ・履歴・Referer に残らない）
3. rp-legacy はフォームの `state` をセッションの値と照合し、合否にかかわらずサーバー間通信で broker の `exchange.php` に `app_id`・アプリのシークレット・引換コード・セッション側の `state` を送る。broker は引き換え要求を受けた時点でコードを失効させ、`state` のハッシュが発行時と一致する場合だけユーザー情報を返す

レガシー側のコードは curl と json_decode だけで、OIDC のライブラリは使っていません。`state` の照合は 2 か所にあります。rp-legacy 側の照合は、他人が始めたログインの引換コードを踏まされてその人としてログインしてしまう事故（ログイン CSRF）を防ぎます。broker 側の照合は、引換コードをログインを始めたレガシー側のセッションに結びつけるもので、攻撃者は自分のセッションに被害者の `state` を入れられないため、コードだけ盗んでも引き換えられません。`state` 自体は URL に出るので秘密ではありません。攻撃者が始めたフローを被害者に完了させる攻撃に対しては、不一致の引き換え要求でもコードを失効させることと、コードを URL に載せないことで備えています。

## 無効化とログアウト

- ポータルからログアウトすると、Hydra のログインセッションも失効させる（`SessionController::logout`）。これを呼ばないと `remember_for` の間は他のアプリから再ログインなしで通る
- Hydra のログインセッションが残っている場合（`skip` が true）でも、台帳で無効化されたユーザーは reject する（`LoginController::show`）
- SSO のセッションは Laravel 側のもの。Hydra のログインセッション（`remember`）は実際にパスワードを入力したときだけ作る。Laravel セッションの再利用で Hydra に「今認証した」セッションを作ると、`max_age` の再認証要求が Hydra の skip 経由ですり抜けるため
- RP が `prompt=login` か `max_age` で再認証を求めた場合は、Laravel のセッションや Hydra の skip があってもパスワード入力を求める（rp-modern の「再認証」リンクで確認できる）。skip の要求をフォームで受理するときは、Hydra が示す subject と同じユーザーでしか受理しない
- この経路で受理した場合、Hydra から見た認証時刻は受理した時刻になる（accept login に認証時刻を渡す項目はない）
- ユーザーの無効化は `php artisan user:disable <email>`。台帳の `disabled_at` を立て、Hydra のログインセッションと発行済みトークンを失効させる。`--enable` で解除

```sh
docker compose exec idp php artisan user:disable demo@example.com
docker compose exec idp php artisan user:disable demo@example.com --enable
```

## 設計上の注意

- `sub` には不変の内部 ID（ここでは users.id）を使う。メールやログイン ID は変わりうる
- ログイン POST は `throttle:5,1`（1 分に 5 回）で制限している
- SSO の実効寿命は Hydra のログインセッション（`remember_for`）と Laravel のセッション寿命の長い方で決まる。どちらで制御するかを決めておく
- 引換コードの使用済み化は条件付き UPDATE 1 回で行う。SELECT してから UPDATE すると文の間に別のリクエストが割り込める（SQLite でも同じ）。Redis に移すなら GETDEL か Lua で判定と削除を 1 操作にする
- Hydra の issuer はブラウザ向け URL（`http://localhost:4444`）。コンテナからのトークン交換は `http://hydra:4444` に向ける必要があるため、クライアントは Discovery を使わずエンドポイントを個別に設定している（`rp-modern/oidc.php`）
- 同じ `localhost` 上で複数の PHP アプリが動くため、セッション Cookie 名をアプリごとに分けている
- PHP のセッションは `session.use_strict_mode=1` で起動し、rp-legacy はログイン開始時と完了時にセッション ID を再生成する（セッション固定対策）
- broker のセッションは進行中のフローを 1 件しか持たない。タブを 2 つ開いて同時にログインを始めると先のフローが壊れる
- Hydra は `--dev` で起動しており HTTPS を要求しない。本番では TLS 終端を前に置く
- Admin API（4445）はローカル確認のため 127.0.0.1 に開けているが、本番では公開しない
- クライアント側から始めるログアウト（RP-Initiated Logout）と Token Introspection はこのサンプルには含めていない
- `exchange.php` はサンプルではブラウザからも到達できる。本番では内部向けを別のポートかバーチャルホストに分けるか、リバースプロキシでパス単位に遮断する

## バージョン

| 対象 | バージョン |
|---|---|
| Ory Hydra | v26.2.0 |
| Laravel | 12.x（PHP 8.3 イメージ、composer の platform は 8.2） |
| jumbojett/openid-connect-php | 1.0.x |
| PHP（rp-legacy） | 5.6.40 |
| PostgreSQL | 16 |

## 参考

- Ory Hydra: https://github.com/ory/hydra
- Login & Consent フロー: https://www.ory.com/docs/oauth2-oidc/custom-login-consent/flow
- Hydra Admin API: https://www.ory.com/docs/hydra/reference/api
- jumbojett/openid-connect-php: https://github.com/jumbojett/OpenID-Connect-PHP
