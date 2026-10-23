#!/usr/bin/env bash
# Run WordPress "Plugin Check" (wp plugin check) against the plugin as it SHIPS.
#
# WHY THE ARTIFACT, NOT THE TREE: the tree carries tests/ and dotfiles that
# .gitattributes export-ignore strips from every archive. Checking the tree
# reports findings in files no site owner installs and buries the real ones.
# So this builds the archive with `git archive` and checks that.
#
# GATE: any ERROR finding that is not in tests/plugin-check-baseline.txt fails
# the run (exit 1). A finding in the baseline is owed work, printed but not
# fatal. A baseline entry that no longer occurs is reported as FIXED so the
# list is trimmed. Warnings are printed and never gate.
#
# EXIT: 0 = no new error. 1 = new error. 2 = COULD NOT MEASURE (no harness,
# no network, tool failure). Exit 2 is never a pass.
#
# Env:
#   PLUGIN_CHECK_HOME  harness dir (default ~/.cache/skyphusion-ai-plugin-check)
#   PLUGIN_CHECK_PREFIX  directory name inside the archive (default
#                      cloudflare-ai-search, what the release zip uses today)
#   PLUGIN_CHECK_REF   git ref to archive (default HEAD)
#   PLUGIN_CHECK_BASELINE  baseline file (default tests/plugin-check-baseline.txt)
#   PLUGIN_CHECK_ROOT  repo to archive (default: the repo holding this script)
#   PLUGIN_CHECK_PLANT  path:text; append text to that archived file before the
#                      check. Test hook to prove the gate can fail.
# Needs: git, php, wp (WP-CLI), curl, unzip, jq. macOS and Linux.

set -u
set -o pipefail

ROOT="${PLUGIN_CHECK_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
HOME_DIR="${PLUGIN_CHECK_HOME:-$HOME/.cache/skyphusion-ai-plugin-check}"
PREFIX="${PLUGIN_CHECK_PREFIX:-cloudflare-ai-search}"
REF="${PLUGIN_CHECK_REF:-HEAD}"
BASELINE="${PLUGIN_CHECK_BASELINE:-$ROOT/tests/plugin-check-baseline.txt}"
WPDIR="$HOME_DIR/wordpress"

cannot() { echo "plugin-check: COULD NOT MEASURE: $*" >&2; exit 2; }

for tool in git php wp curl unzip jq; do
    command -v "$tool" >/dev/null 2>&1 || cannot "missing tool '$tool' (macOS: brew install wp-cli jq)"
done

# wp-cli's bundled libraries print PHP 8.5 deprecation noise; it is not failure.
export WP_CLI_PHP_ARGS='-d memory_limit=-1'
wpq() { wp --path="$WPDIR" --quiet "$@" 2> >(grep -v -E 'Deprecated: Case statements|^$' >&2); }

fetch() { # url dest : fail on HTTP error or a tiny body (a 404 body is 9 bytes)
    curl -fsSL -o "$2" "$1" || return 1
    [ "$(wc -c <"$2")" -gt 1000 ] || return 1
}

build_harness() {
    echo "plugin-check: building harness in $HOME_DIR (one time)" >&2
    mkdir -p "$HOME_DIR/dl" || cannot "cannot create $HOME_DIR"
    # Not `wp core download`: its phar extractor exhausts memory on some hosts.
    fetch https://wordpress.org/latest.zip "$HOME_DIR/dl/wordpress.zip" || cannot "download of WordPress failed"
    rm -rf "$WPDIR"
    unzip -q "$HOME_DIR/dl/wordpress.zip" -d "$HOME_DIR/dl/wp-x" || cannot "unzip of WordPress failed"
    mv "$HOME_DIR/dl/wp-x/wordpress" "$WPDIR" || cannot "cannot place WordPress"
    # SQLite instead of a DB server. NO version in this URL: a versioned URL 404s.
    fetch https://downloads.wordpress.org/plugin/sqlite-database-integration.zip "$HOME_DIR/dl/sqlite.zip" \
        || cannot "download of sqlite-database-integration failed"
    unzip -q "$HOME_DIR/dl/sqlite.zip" -d "$WPDIR/wp-content/plugins/" || cannot "unzip of sqlite plugin failed"
    cp "$WPDIR/wp-content/plugins/sqlite-database-integration/db.copy" "$WPDIR/wp-content/db.php" \
        || cannot "cannot install db.php"
    wpq config create --dbname=pcheck --dbuser=root --dbpass= --skip-check --force \
        --extra-php <<'PHP' || cannot "wp config create failed"
define('DB_ENGINE', 'sqlite');
PHP
    wpq core install --url=http://localhost:8080 --title=pcheck --admin_user=pcheck \
        --admin_password="$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 24)" \
        --admin_email=pcheck@example.test --skip-email || cannot "wp core install failed"
    wpq plugin install plugin-check --activate || cannot "installing plugin-check failed"
    rm -rf "$HOME_DIR/dl"
}

if [ ! -f "$WPDIR/wp-content/plugins/plugin-check/plugin.php" ] && [ ! -d "$WPDIR/wp-content/plugins/plugin-check" ]; then
    build_harness
fi
wpq plugin is-active plugin-check || cannot "harness at $HOME_DIR is broken; delete it and rerun"

# Build the shipped artifact. An archive failure is a measurement failure.
PLUGDIR="$WPDIR/wp-content/plugins/$PREFIX"
rm -rf "$PLUGDIR"
mkdir -p "$PLUGDIR" || cannot "cannot create $PLUGDIR"
git -C "$ROOT" archive --format=tar --prefix="$PREFIX/" "$REF" | tar -x -C "$WPDIR/wp-content/plugins" \
    || cannot "git archive of $REF failed"
[ -f "$PLUGDIR/$PREFIX.php" ] || [ -n "$(ls "$PLUGDIR"/*.php 2>/dev/null)" ] || cannot "archive has no plugin file"

if [ -n "${PLUGIN_CHECK_PLANT:-}" ]; then
    plant_file="${PLUGIN_CHECK_PLANT%%:*}"
    plant_text="${PLUGIN_CHECK_PLANT#*:}"
    [ -f "$PLUGDIR/$plant_file" ] || cannot "PLUGIN_CHECK_PLANT file $plant_file not in archive"
    printf '\n%s\n' "$plant_text" >>"$PLUGDIR/$plant_file"
    echo "plugin-check: PLANTED text in archived $plant_file (test hook)" >&2
fi

OUT="$(mktemp "${TMPDIR:-/tmp}/plugin-check.XXXXXX")" || cannot "mktemp failed"
ALL="$OUT.all"
FOUND="$OUT.found"
trap 'rm -f "$OUT" "$ALL" "$FOUND"' EXIT
wpq plugin check "$PREFIX" --format=json >"$OUT" 2>/dev/null || true

# Output is "FILE: <name>" lines, each followed by one JSON array of findings,
# mixed with PHP deprecation noise. Fold it to TSV: type, code, file.
: >"$ALL"
file=""
sawfile=0
while IFS= read -r line; do
    case "$line" in
        "FILE: "*) file="${line#FILE: }"; sawfile=1 ;;
        "["*)
            [ -n "$file" ] || cannot "findings array with no FILE line"
            printf '%s\n' "$line" | jq -r --arg f "$file" '.[] | [.type, .code, $f] | @tsv' >>"$ALL" \
                || cannot "unparseable findings for $file"
            ;;
    esac
done <"$OUT"
# A clean plugin prints no FILE lines. Silence is not a pass: require the
# tool's own success marker, else report that nothing was measured.
if [ "$sawfile" -eq 0 ] && ! grep -q -E '^Success' "$OUT"; then
    sed -n '1,20p' "$OUT" >&2
    cannot "wp plugin check printed no findings and no success marker"
fi

echo "plugin-check: warnings in the shipped artifact (never gate):" >&2
awk -F'\t' '$1=="WARNING" {print $2}' "$ALL" | sort | uniq -c | sed 's/^ */  x/' >&2

# Errors as file<TAB>code<TAB>count.
awk -F'\t' -v OFS='\t' '$1=="ERROR" {n[$3 OFS $2]++} END {for (k in n) print k, n[k]}' "$ALL" | sort >"$FOUND"

# Baseline lines: file<TAB>code<TAB>count. Only an INCREASE, or a (file, code)
# pair not in the baseline, fails. Line numbers are not keyed: they drift with
# every unrelated edit.
base="$(grep -v -e '^#' -e '^$' "$BASELINE" 2>/dev/null || true)"
new=0
while IFS=$'\t' read -r f c n; do
    [ -n "$f" ] || continue
    case "$n" in ''|*[!0-9]*) cannot "malformed count for $f $c" ;; esac
    b="$(printf '%s\n' "$base" | awk -F'\t' -v f="$f" -v c="$c" '$1==f && $2==c {print $3}')"
    b="${b:-0}"
    if [ "$n" -gt "$b" ]; then
        echo "plugin-check: NEW ERROR  $f  $c  (found $n, baseline $b)" >&2
        new=1
    fi
done <"$FOUND"
# Baseline entries that no longer occur: the owed list should shrink.
while IFS=$'\t' read -r f c n; do
    [ -n "$f" ] || continue
    cur="$(awk -F'\t' -v f="$f" -v c="$c" '$1==f && $2==c {print $3}' "$FOUND")"
    if [ "${cur:-0}" -lt "$n" ]; then
        echo "plugin-check: FIXED  $f  $c  (baseline $n, now ${cur:-0}); trim the baseline" >&2
    fi
done <<EOT
$base
EOT
echo "plugin-check: errors in the shipped artifact (file, code, count):" >&2
sed 's/^/  /' "$FOUND" >&2
if [ "$new" -ne 0 ]; then
    echo "plugin-check: FAIL, error(s) not in $BASELINE" >&2
    exit 1
fi
echo "plugin-check: PASS, no error beyond the baseline" >&2
exit 0
