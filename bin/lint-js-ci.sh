#!/usr/bin/env bash
# Run ESLint via wp-scripts and turn results into GitHub annotations.
set -o pipefail
npx wp-scripts lint-js "wp-content/**/assets/js/**/*.js" "wp-content/plugins/techvenza-core/blocks/**/*.js" \
	--no-error-on-unmatched-pattern -f unix | tee /tmp/eslint.txt
status=${PIPESTATUS[0]}
grep -E '^/.+:[0-9]+:[0-9]+:' /tmp/eslint.txt | while IFS=: read -r file line col msg; do
	level=error; [[ "$msg" == *"[Warning/"* ]] && level=warning
	echo "::${level} file=${file#"$PWD/"},line=${line},col=${col}::${msg# }"
done
exit "$status"
