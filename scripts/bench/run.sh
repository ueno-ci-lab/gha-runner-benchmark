#!/usr/bin/env bash
# CI を手動実行（workflow_dispatch）で N 回、1 回ずつ完了を待ちながら順番に実行する。
#
# 使い方: scripts/bench/run.sh <runners> <no-cache> <count> [blacksmith-vcpu]
#   runners         : both | github | blacksmith
#   no-cache        : true（コールド） | false（ウォーム）
#   count           : 実行回数
#   blacksmith-vcpu : 2 | 4 | 8（省略時 2）
#
# 例: scripts/bench/run.sh github true 10
#
# 実行した run ID は scripts/bench/runs.log に追記される。
set -euo pipefail

runners=${1:?runners を指定してください（both / github / blacksmith）}
no_cache=${2:?no-cache を指定してください（true / false）}
count=${3:?実行回数を指定してください}
vcpu=${4:-2}

case "$runners" in both|github|blacksmith) ;; *) echo "runners が不正です: $runners" >&2; exit 1;; esac
case "$no_cache" in true|false) ;; *) echo "no-cache が不正です: $no_cache" >&2; exit 1;; esac
case "$vcpu" in 2|4|8) ;; *) echo "blacksmith-vcpu が不正です: $vcpu" >&2; exit 1;; esac

cd "$(dirname "$0")/../.."
repo=$(gh repo view --json nameWithOwner --jq .nameWithOwner)
log=scripts/bench/runs.log

for i in $(seq 1 "$count"); do
  started=$(date -u +%Y-%m-%dT%H:%M:%SZ)
  gh workflow run ci.yml -R "$repo" --ref main \
    -f runners="$runners" -f no-cache="$no_cache" -f blacksmith-vcpu="$vcpu" >/dev/null

  # 今回の実行が一覧に現れるまで待つ
  run_id=""
  for _ in $(seq 1 30); do
    run_id=$(gh run list -R "$repo" --workflow ci.yml --event workflow_dispatch --limit 5 \
      --json databaseId,createdAt --jq "[.[] | select(.createdAt >= \"$started\")] | last | .databaseId // empty")
    [ -n "$run_id" ] && break
    sleep 2
  done
  [ -n "$run_id" ] || { echo "実行 ID を取得できませんでした" >&2; exit 1; }

  printf '[%s/%s] run %s (runners=%s no-cache=%s vcpu=%s) ... ' "$i" "$count" "$run_id" "$runners" "$no_cache" "$vcpu"
  if gh run watch "$run_id" -R "$repo" --interval 10 --exit-status >/dev/null 2>&1; then
    echo "success"
  else
    echo "$(gh run view "$run_id" -R "$repo" --json conclusion --jq .conclusion)"
  fi
  echo "$run_id" >> "$log"
done
