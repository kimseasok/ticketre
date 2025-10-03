#!/usr/bin/env bash
set -Eeuo pipefail
trap 'echo "[ERR] line:$LINENO"; exit 1' ERR

REPO="${REPO:-}"
RATE_LIMIT_SLEEP="${RATE_LIMIT_SLEEP:-2}"
MAX_RETRY="${MAX_RETRY:-5}"
MODE="${1:---help}"

usage() {
  cat <<'USAGE'
Usage: gh_issues.sh [--try-run|--execute|--verify]
USAGE
}

require_repo() {
  if [[ -z "$REPO" ]]; then
    echo "⚠️ No remote repository configured. Please set REPO=<owner>/<repo> and re-run." >&2
    exit 1
  fi
}

ensure_gh() {
  if ! command -v gh >/dev/null 2>&1; then
    echo "[INFO] Installing GitHub CLI..."
    if command -v apt-get >/dev/null 2>&1; then
      sudo apt-get update && sudo apt-get install -y gh || {
        echo "[ERR] Failed to install gh. Please install GitHub CLI manually." >&2
        exit 1
      }
    else
      echo "[ERR] gh not found and auto-install unsupported." >&2
      exit 1
    fi
  fi
  gh --version >/dev/null
}

ensure_remote() {
  if git remote get-url origin >/dev/null 2>&1; then
    return
  fi
  if ! gh repo view "$REPO" >/dev/null 2>&1; then
    echo "⚠️ No remote repository configured. Please set REPO=<owner>/<repo> and re-run." >&2
    exit 1
  fi
}

ensure_auth() {
  if gh auth status --hostname github.com >/dev/null 2>&1; then
    return
  fi
  echo "[INFO] Authenticating with GitHub..."
  gh auth login --hostname github.com --web || {
    echo "[ERR] gh auth login failed." >&2
    exit 1
  }
}

lint_csv() {
  python - <<'PY'
import csv, json, re, sys
from pathlib import Path
errors = []
path = Path('issues.csv')
if not path.exists():
    errors.append('issues.csv missing')
else:
    with path.open(newline='') as f:
        reader = csv.DictReader(f)
        if reader.fieldnames != ['id', 'title', 'body', 'labels', 'milestone']:
            errors.append(f'Invalid header: {reader.fieldnames}')
        ids = set()
        titles = set()
        rows = []
        for idx, row in enumerate(reader, start=2):
            rid = row['id'].strip()
            title = row['title'].strip()
            body = row['body']
            labels = row['labels'].strip()
            milestone = row['milestone'].strip()
            if not all([rid, title, body, labels, milestone]):
                errors.append(f'Line {idx}: empty field')
            if not re.fullmatch(r'E\d+-F\d+-I\d+', rid):
                errors.append(f'Line {idx}: invalid id {rid}')
            if not title.startswith(f"{rid}:"):
                errors.append(f'Line {idx}: title missing prefix')
            if rid in ids:
                errors.append(f'Duplicate id {rid}')
            if title in titles:
                errors.append(f'Duplicate title {title}')
            ids.add(rid)
            titles.add(title)
            parts = [p.strip() for p in labels.split(',') if p.strip()]
            if len(parts) != 4:
                errors.append(f'Line {idx}: expected 4 labels, got {len(parts)}')
            for lbl in parts:
                if not re.fullmatch(r'(epic|type|priority|area):[A-Za-z0-9]+', lbl):
                    errors.append(f'Line {idx}: invalid label {lbl}')
            if milestone not in {'Week 1','Week 2','Week 3','Week 4'}:
                errors.append(f'Line {idx}: invalid milestone {milestone}')
            for heading in ('### Summary', '### Scope', '### Acceptance Criteria', '### Notes'):
                if heading not in body:
                    errors.append(f'Line {idx}: body missing heading {heading}')
            rows.append(row)
if errors:
    print('CSV Lint FAIL:', file=sys.stderr)
    for err in errors:
        print(f' - {err}', file=sys.stderr)
    sys.exit(1)
with open('issues.json', 'w') as f:
    json.dump(rows, f)
print('CSV Lint PASS')
PY
}

ensure_labels() {
  local existing
  existing=$(gh label list --repo "$REPO" --limit 200 --json name --jq '.[].name')
  declare -A have
  while IFS= read -r name; do
    have[$name]=1
  done <<<"$existing"
  jq -r '.[].labels' issues.json | tr ',' '\n' | while read -r lbl; do
    lbl=$(echo "$lbl" | xargs)
    [[ -z "$lbl" ]] && continue
    if [[ -z "${have[$lbl]:-}" ]]; then
      gh label create "$lbl" --repo "$REPO" --force >/dev/null
      have[$lbl]=1
    fi
  done
}

ensure_milestones() {
  local milestones
  milestones=$(gh api repos/$REPO/milestones --paginate --jq '.[].title')
  declare -A have
  while IFS= read -r title; do
    have[$title]=1
  done <<<"$milestones"
  for title in "Week 1" "Week 2" "Week 3" "Week 4"; do
    if [[ -z "${have[$title]:-}" ]]; then
      gh api repos/$REPO/milestones -f title="$title" >/dev/null
    fi
  done
}

retry() {
  local attempt=1
  local delay=$RATE_LIMIT_SLEEP
  while true; do
    if "$@"; then
      return 0
    fi
    if (( attempt >= MAX_RETRY )); then
      return 1
    fi
    sleep "$delay"
    delay=$((delay*2))
    ((attempt++))
  done
}

iter_issues() {
  jq -c '.[]' issues.json
}

get_milestone_number() {
  local title="$1"
  gh api repos/$REPO/milestones | jq -r --arg title "$title" '.[] | select(.title == $title) | .number' | head -n1
}

try_run() {
  ensure_labels
  ensure_milestones
  local create=0 skip=0
  while IFS= read -r row; do
    local id title labels milestone
    id=$(jq -r '.id' <<<"$row")
    title=$(jq -r '.title' <<<"$row")
    labels=$(jq -r '.labels' <<<"$row")
    milestone=$(jq -r '.milestone' <<<"$row")
    local existing
    existing=$(gh issue list --repo "$REPO" --state all --search "\"$title\" in:title" --limit 1 --json title --jq '.[].title')
    if [[ "$existing" == "$title" ]]; then
      echo "SKIP: $title"
      ((skip+=1))
      continue
    fi
    echo "CREATE: $title -> [$labels] milestone [$milestone]"
    ((create+=1))
  done < <(iter_issues)
  echo "Planned create: $create, skip: $skip"
}

execute() {
  ensure_labels
  ensure_milestones
  touch created-tracing.md
  local create=0 skip=0 fail=0
  while IFS= read -r row; do
    local id title body labels milestone
    id=$(jq -r '.id' <<<"$row")
    title=$(jq -r '.title' <<<"$row")
    body=$(jq -r '.body' <<<"$row")
    labels=$(jq -r '.labels' <<<"$row")
    milestone=$(jq -r '.milestone' <<<"$row")
    if grep -Fq "| $id |" created-tracing.md 2>/dev/null; then
      echo "SKIP(traced): $title"
      ((skip+=1))
      continue
    fi
    local existing
    existing=$(gh issue list --repo "$REPO" --state all --search "\"$title\" in:title" --limit 1 --json number,title --jq '.[0]')
    if [[ -n "$existing" ]]; then
      echo "SKIP(existing): $title"
      ((skip+=1))
      continue
    fi
    IFS=',' read -ra lbls <<<"$labels"
    local labels_json
    labels_json=$(printf '%s\n' "${lbls[@]}" | sed 's/^ *//;s/ *$//' | sed '/^$/d' | jq -R . | jq -s .)
    local milestone_id
    milestone_id=$(get_milestone_number "$milestone")
    if [[ -z "$milestone_id" ]]; then
      echo "[ERR] Milestone not found: $milestone" >&2
      exit 1
    fi
    local payload_file
    payload_file=$(mktemp)
    jq -n --arg title "$title" --arg body "$body" --argjson labels "$labels_json" --argjson milestone "$milestone_id" '{title:$title, body:$body, labels:$labels, milestone:$milestone}' >"$payload_file"
    if resp=$(retry gh api repos/$REPO/issues --method POST --input "$payload_file"); then
      local number state
      number=$(jq '.number' <<<"$resp")
      state=$(jq -r '.state' <<<"$resp")
      printf '| %s | %s | %s | %s | #%s | %s | %s |\n' "$id" "$title" "$labels" "$milestone" "$number" "$state" "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> created-tracing.md
      echo "CREATED: $title (#$number)"
      ((create+=1))
    else
      echo "FAILED: $title" >&2
      ((fail+=1))
    fi
    rm -f "$payload_file"
  done < <(iter_issues)
  echo "Created=$create Skipped=$skip Failed=$fail"
  if (( fail > 0 )); then
    exit 1
  fi
}

verify() {
  if [[ ! -s created-tracing.md ]]; then
    echo "No entries in created-tracing.md" >&2
    exit 1
  fi
  echo '| ID | Title | Labels | Milestone | Issue # | State | Timestamp |'
  echo '|----|-------|--------|-----------|---------|-------|-----------|'
  tail -n +2 created-tracing.md
}

case "$MODE" in
  --try-run)
    ensure_gh
    require_repo
    ensure_remote
    ensure_auth
    lint_csv
    try_run
    ;;
  --execute)
    ensure_gh
    require_repo
    ensure_remote
    ensure_auth
    lint_csv
    execute
    ;;
  --verify)
    verify
    ;;
  *)
    usage
    exit 1
    ;;
esac
