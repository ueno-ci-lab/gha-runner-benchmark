#!/usr/bin/env bash
# 指定したファイルを Windows のクリップボードに UTF-8 でコピーする（WSL 用）
# 使い方: docs/notion/copy.sh 01        ← ファイル名の先頭の番号で指定
set -euo pipefail
cd "$(dirname "$0")"
file=$(ls "$1"*.md 2>/dev/null | head -1 || true)
[ -z "$file" ] && file="$1"
[ -f "$file" ] || { echo "ファイルが見つかりません: $1" >&2; exit 1; }
powershell.exe -NoProfile -Command "Get-Content -Raw -Encoding UTF8 -LiteralPath '$(wslpath -w "$PWD/$file")' | Set-Clipboard"
echo "コピーしました: $file"
