#!/usr/bin/env python3
"""計測用の CI 実行（実行名が [bench] で始まるもの）の所要時間を集計し、CSV を出力する。

使い方:
  python3 -I scripts/bench/collect.py                    # すべての計測実行を集計して標準出力へ
  python3 -I scripts/bench/collect.py -o results.csv     # ファイルへ出力
  python3 -I scripts/bench/collect.py --since 2026-10-11 # 指定日以降の実行のみ
  python3 -I scripts/bench/collect.py --summary          # 条件ごとの中央値・p90 も表示

出力する CSV の列は docs/notion/05_計測結果_インポート用.csv と同じ。
"""

import argparse
import csv
import json
import math
import re
import statistics
import subprocess
import sys
from datetime import datetime, timedelta, timezone

WORKFLOW = "ci.yml"
JST = timezone(timedelta(hours=9))

# 1 分あたりの単価（USD）。GitHub は Linux 2 コア、Blacksmith は 2vCPU あたりの単価で vCPU 数に比例
GITHUB_PRICE = 0.006
BLACKSMITH_PRICE_PER_2VCPU = 0.004

PROVIDERS = {"GitHub hosted": "GitHub 標準", "Blacksmith": "Blacksmith"}
CHECKS = {
    "build image": "ビルド（秒）",
    "lint (Pint)": "Lint（秒）",
    "static analysis (Larastan)": "静的解析（秒）",
    "test (PHPUnit)": "テスト（秒）",
}
COLUMNS = [
    "名前", "実行日時", "ランナー", "vCPU", "キャッシュ", "待ち時間（秒）",
    *CHECKS.values(), "全体（秒）", "課金対象（分）", "単価（$/分）", "結果", "実行 URL", "メモ",
]


def gh_api(path):
    out = subprocess.run(["gh", "api", "--paginate", "--slurp", path], check=True, capture_output=True, text=True)
    return json.loads(out.stdout)


def repo_name():
    out = subprocess.run(["gh", "repo", "view", "--json", "nameWithOwner", "--jq", ".nameWithOwner"],
                         check=True, capture_output=True, text=True)
    return out.stdout.strip()


def ts(value):
    return datetime.fromisoformat(value.replace("Z", "+00:00")) if value else None


def seconds(start, end):
    return round((end - start).total_seconds()) if start and end else None


def bench_runs(repo, since):
    pages = gh_api(f"/repos/{repo}/actions/workflows/{WORKFLOW}/runs?event=workflow_dispatch&per_page=100")
    runs = [run for page in pages for run in page["workflow_runs"]]
    runs = [r for r in runs if r["name"].startswith("[bench]") and r["status"] == "completed"]
    if since:
        runs = [r for r in runs if ts(r["created_at"]).astimezone(JST).date().isoformat() >= since]
    return sorted(runs, key=lambda r: r["created_at"])


def parse_conditions(run_name):
    params = dict(re.findall(r"([\w-]+)=(\S+)", run_name))
    return params.get("no-cache") == "true", int(params.get("blacksmith-vcpu", "2"))


def provider_rows(repo, run):
    no_cache, blacksmith_vcpu = parse_conditions(run["name"])
    pages = gh_api(f"/repos/{repo}/actions/runs/{run['id']}/attempts/{run['run_attempt']}/jobs?per_page=100")
    jobs = [job for page in pages for job in page["jobs"]]

    rows = []
    for prefix, label in PROVIDERS.items():
        provider_jobs = [j for j in jobs if j["name"].startswith(prefix + " / ")]
        if not provider_jobs:
            continue  # このランナーは実行対象外（スキップ）

        row = {
            "名前": f"{label} / {'コールド' if no_cache else 'ウォーム'} / {run['id']}",
            "実行日時": ts(run["created_at"]).astimezone(JST).strftime("%Y-%m-%d %H:%M"),
            "ランナー": label,
            "vCPU": blacksmith_vcpu if label == "Blacksmith" else 2,
            "キャッシュ": "なし（コールド）" if no_cache else "あり（ウォーム）",
            "実行 URL": run["html_url"],
            "メモ": "",
        }

        billable = 0
        for job in provider_jobs:
            check = job["name"].split(" / ", 1)[1]
            # ランナーがジョブを受け取った時刻 = 最初のステップの開始時刻
            picked_up = ts(job["steps"][0]["started_at"]) if job.get("steps") else None
            duration = seconds(picked_up, ts(job["completed_at"]))
            if check in CHECKS:
                row[CHECKS[check]] = duration
            if check == "build image":
                row["待ち時間（秒）"] = seconds(ts(job["created_at"]), picked_up)
            if duration is not None:
                billable += max(1, math.ceil(duration / 60))

        row["全体（秒）"] = seconds(min(ts(j["created_at"]) for j in provider_jobs),
                                 max(ts(j["completed_at"]) for j in provider_jobs if j["completed_at"]))
        row["課金対象（分）"] = billable
        row["単価（$/分）"] = (GITHUB_PRICE if label == "GitHub 標準"
                            else BLACKSMITH_PRICE_PER_2VCPU * blacksmith_vcpu / 2)
        row["結果"] = "成功" if all(j["conclusion"] == "success" for j in provider_jobs) else "失敗"
        rows.append(row)
    return rows


def print_summary(rows):
    groups = {}
    for row in rows:
        if row["結果"] != "成功":
            continue
        groups.setdefault((row["ランナー"], row["vCPU"], row["キャッシュ"]), []).append(row)

    metrics = ["待ち時間（秒）", *CHECKS.values(), "全体（秒）", "課金対象（分）"]
    print(f"\n{'条件':<34}{'件数':>4}  " + "  ".join(f"{m}" for m in metrics), file=sys.stderr)
    for (runner, vcpu, cache), items in sorted(groups.items()):
        cells = []
        for metric in metrics:
            values = sorted(v for v in (i.get(metric) for i in items) if v is not None)
            if not values:
                cells.append("-")
                continue
            p90 = values[min(len(values) - 1, math.ceil(len(values) * 0.9) - 1)]
            cells.append(f"{statistics.median(values):g}/{p90:g}")
        print(f"{runner} {vcpu}vCPU {cache:<14}{len(items):>4}  " + "  ".join(cells), file=sys.stderr)
    print("（各列は 中央値/p90）", file=sys.stderr)


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("-o", "--output", help="CSV の出力先（省略時は標準出力）")
    parser.add_argument("--since", help="この日付（JST, YYYY-MM-DD）以降の実行のみ集計する")
    parser.add_argument("--summary", action="store_true", help="条件ごとの中央値と p90 を標準エラー出力に表示する")
    args = parser.parse_args()

    repo = repo_name()
    rows = [row for run in bench_runs(repo, args.since) for row in provider_rows(repo, run)]

    out = open(args.output, "w", newline="", encoding="utf-8-sig") if args.output else sys.stdout
    writer = csv.DictWriter(out, fieldnames=COLUMNS)
    writer.writeheader()
    writer.writerows(rows)
    if args.output:
        out.close()
        print(f"{len(rows)} 行を {args.output} に出力しました", file=sys.stderr)

    if args.summary:
        print_summary(rows)


if __name__ == "__main__":
    main()
