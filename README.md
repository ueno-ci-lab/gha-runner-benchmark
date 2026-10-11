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

## 計測（`scripts/bench/`）

前提: `gh` CLI で `ueno2121` としてログイン済みであること。

```bash
# CI を手動実行で N 回、順番に実行（runners: both / github / blacksmith、no-cache: true=コールド / false=ウォーム）
scripts/bench/run.sh github true 10
scripts/bench/run.sh github false 10
scripts/bench/run.sh blacksmith false 10 4   # 第4引数は Blacksmith の vCPU 数（2 / 4 / 8）

# 計測実行（実行名が [bench] で始まるもの）を集計し、Notion 取り込み用の CSV を出力
python3 -I scripts/bench/collect.py -o scripts/bench/results.csv --summary
```

- push / PR で Blacksmith 側も実行するには、リポジトリ変数 `BLACKSMITH_ENABLED` を `true` にする（`gh variable set BLACKSMITH_ENABLED --body true`）。
- 待ち時間は「ジョブ作成からランナーが最初のステップを開始するまで」、各ジョブの時間は「最初のステップ開始から完了まで」。
- 課金対象はジョブごとに分単位で切り上げた合計。単価は GitHub 標準 $0.006/分、Blacksmith $0.004/分（2vCPU あたり）。
