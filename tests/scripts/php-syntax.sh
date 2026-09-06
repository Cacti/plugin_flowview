#!/usr/bin/env bash
set -euo pipefail

repo_root=$(cd "$(dirname "$0")/../.." && pwd)
failures=0

while IFS= read -r -d '' file; do
	if ! php -l "$file" >/dev/null; then
		failures=$((failures + 1))
	fi
done < <(find "$repo_root" -type f -name '*.php' -not -path '*/vendor/*' -print0)

if ((failures > 0)); then
	printf '%d PHP file(s) failed syntax validation\n' "$failures" >&2
	exit 1
fi

printf 'All first-party PHP files passed syntax validation\n'
