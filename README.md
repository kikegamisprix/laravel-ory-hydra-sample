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

1. rp-legacy の `login.php` が `state` を生成してセッションに置き、broker の `start.php?return_to=...&state=...` へ。broker は戻り先からアプリを特定（`BROKER_APPS` の前方一致）して `state` と一緒にセッションに保存し、上の 1〜4 と同じ流れを実行する
2. broker の `callback.php` がトークン交換と ID トークン検証を済ませ、発行先アプリに紐づく 60 秒・一回限りの引換コードを発行し、預かった `state` と一緒に rp-legacy の `callback.php` にリダイレクト
3. rp-legacy は `state` をセッションの値と照合してから、サーバー間通信で broker の `exchange.php` に `app_id`・アプリのシークレット・引換コードを送り、ユーザー情報を受け取ってセッションを作る

レガシー側のコードは curl と json_decode だけで、OIDC のライブラリは使っていません。`state` を省くと、他人が始めたログインの引換コードを踏まされてその人としてログインしてしまう（ログイン CSRF）ので省けません。

## 無効化とログアウト

- ポータルからログアウトすると、Hydra のログインセッションも失効させる（`SessionController::logout`）。これを呼ばないと `remember_for` の間は他のアプリから再ログインなしで通る
- Hydra のログインセッションが残っている場合（`skip` が true）でも、台帳で無効化されたユーザーは reject する（`LoginController::show`）
- ユーザーの無効化は `php artisan user:disable <email>`。台帳の `disabled_at` を立て、Hydra のログインセッションと発行済みトークンを失効させる。`--enable` で解除

```sh
docker compose exec idp php artisan user:disable demo@example.com
docker compose exec idp php artisan user:disable demo@example.com --enable
```

## 設計上の注意

- `sub` には不変の内部 ID（ここでは users.id）を使う。メールやログイン ID は変わりうる
- ログイン POST は `throttle:5,1`（1 分に 5 回）で制限している
- Hydra の issuer はブラウザ向け URL（`http://localhost:4444`）。コンテナからのトークン交換は `http://hydra:4444` に向ける必要があるため、クライアントは Discovery を使わずエンドポイントを個別に設定している（`rp-modern/oidc.php`）
- 同じ `localhost` 上で複数の PHP アプリが動くため、セッション Cookie 名をアプリごとに分けている
- Hydra は `--dev` で起動しており HTTPS を要求しない。本番では TLS 終端を前に置く
- Admin API（4445）はローカル確認のため公開しているが、本番では公開しない
- クライアント側から始めるログアウト（RP-Initiated Logout）と Token Introspection はこのサンプルには含めていない
- `exchange.php` はサンプルではブラウザからも到達できる。本番ではサーバー間のネットワークに閉じる

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
