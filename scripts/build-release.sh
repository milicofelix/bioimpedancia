#!/usr/bin/env sh
set -eu

version="${1:-$(date +%Y%m%d%H%M%S)}"
release_dir="output/releases"
archive="${release_dir}/ricostyemagrecimento-${version}.zip"

mkdir -p "$release_dir"

rm -f "$archive"
git ls-files --cached --others --exclude-standard -z | xargs -0 zip -q "$archive"

echo "$archive"
