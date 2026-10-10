# gha-runner-benchmark

GitHub Actions の標準ランナーと Blacksmith ランナーを比較検証するための Laravel アプリ（タスク管理 API）。

## 構成

- Laravel 13 / PHP 8.5（`docker/php/Dockerfile` でバージョンを一元管理）
- ローカル開発: Docker Compose（`app` + `mysql`）
- CI: 同じ Dockerfile の `ci` ステージをビルドし、コンテナ内で以下を実行
  - Lint: Laravel Pint
  - 静的解析: Larastan（level 8）
  - テスト: PHPUnit（DB はリポジトリをモック。クエリが発行されるとテストが失敗する）

## ローカル開発

```bash
docker compose up -d
docker compose exec app php artisan migrate

docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app php artisan test
```

API: `http://localhost:8000/api/tasks`

## CI（`.github/workflows/`）

| ファイル | 役割 |
|---|---|
| `ci.yml` | 同一コミットで GitHub 標準（`ubuntu-24.04`）と Blacksmith（`blacksmith-2vcpu-ubuntu-2404`）を同時に実行 |
| `_ci.yml` | 再利用ワークフロー。`build` → `lint` / `static analysis` / `test` を並列実行 |
| `../actions/build-ci-image` | イメージビルド。GitHub は `type=gha` キャッシュ、Blacksmith は専用ビルダーのレイヤーキャッシュを使用 |

コールドビルドを計測する場合は Actions 画面から `workflow_dispatch` で `no-cache` を有効にして実行する。
