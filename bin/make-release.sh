#!/usr/bin/env bash
# Build a deployable ZIP containing only runtime files of the theme and plugin.
set -euo pipefail
VERSION="${1:-$(node -p "require('./package.json').version")}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist"
STAGE="$OUT/stage"
rm -rf "$OUT" && mkdir -p "$STAGE/themes" "$STAGE/plugins"
EXCLUDES=(--exclude 'node_modules' --exclude '*.map' --exclude '.DS_Store' --exclude 'tests' --exclude '*.md' --exclude 'phpcs.xml*' --exclude 'package*.json')
rsync -a "${EXCLUDES[@]}" "$ROOT/wp-content/themes/techvenza" "$STAGE/themes/"
rsync -a "${EXCLUDES[@]}" "$ROOT/wp-content/plugins/techvenza-core" "$STAGE/plugins/"
( cd "$STAGE" && zip -qr "$OUT/techvenza-$VERSION.zip" themes plugins )
# Separate installable ZIPs for wp-admin uploads.
( cd "$STAGE/themes" && zip -qr "$OUT/techvenza-theme-$VERSION.zip" techvenza )
( cd "$STAGE/plugins" && zip -qr "$OUT/techvenza-core-$VERSION.zip" techvenza-core )
rm -rf "$STAGE"
ls -1 "$OUT"
