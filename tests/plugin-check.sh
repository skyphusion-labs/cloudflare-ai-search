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
# EXIT: 0 = no new error. 1 = new error, or a baseline that contradicts its
# own OWED: heading. 2 = COULD NOT MEASURE (no harness, no network, tool
# failure). Exit 2 is never a pass.
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

# THE BASELINE MUST AGREE WITH ITSELF. The OWED: heading states a total and a
# file count in PROSE; the gate reads only the data LINES. Those are two facts
# and nothing compared them, so on 2026-10-23 three prose figures said 66 while
# the lines summed to 67 and every run stayed green. A reader decides what is
# owed from the heading, so a stale heading is a wrong answer to the question
# this file exists to answer.
#
# TWO ASSERTIONS, and the second is what keeps the first meaningful:
#
#   1. The OWED heading's total equals the sum of the data lines, and its file
#      count equals the number of distinct files carrying that code.
#   2. That total appears on NO other comment line. One derived number is
#      safe; the same number restated in prose is what drifted, and it had
#      reached eight places in this file before it was de-duplicated.
#
# Derived, never hardcoded: the total and the distinct-file count come from the
# lines themselves. Runs before the harness because it needs no network and no
# WordPress, so a self-contradicting baseline fails in about 30ms.
#
# The heading names its own family ("OWED: TextDomainMismatch, N calls across
# M files") and only lines whose code ENDS with that family are counted, so a
# future baseline carrying a second code does not break the arithmetic.
#
# Assertion 2 checks the TOTAL only, not the per-file counts, and that is a
# measured decision rather than laziness. With issue references excluded, the
# per-file counts on main appear in 0 comment lines each except "2", which
# collides with "wp-cli 2.12.0" and "WordPress 2.9"; and a legitimate history
# note such as "includes/cli.php moved 5 to 6 in PR #48" states per-file
# counts on purpose. Checking those would reject correct prose, and a guard
# that cries wolf is a guard someone disables. The total is the one that
# drifted and the one worth enforcing.
#
# A match preceded by "#" is ignored, because "#38" is an issue reference and
# not a count. That exclusion is load-bearing: without it, seven lines here
# would false-positive the day the total happens to equal an issue number.
#
# FLOOR, and it is the part that stops this guard having the disease it cures:
# a heading claiming 0, or a heading no data line matches, FAILS rather than
# satisfying 0 == 0. A guard that passes on an empty input is not a guard.
#
# Each message is ONE line on purpose. The awk SOURCE wraps with backslashes;
# the printed text never does. A wrapped diagnostic cannot be grepped, and its
# tail gets orphaned from its head by the noise wp-cli interleaves into stderr.
check_baseline_self_consistency() {
    [ -f "$BASELINE" ] || cannot "baseline $BASELINE not found"
    awk -F'\t' '
        function say(s) { print "plugin-check: " s > "/dev/stderr" }
        /^# OWED:/ {
            headings++
            if (match($0, /OWED: [A-Za-z0-9._]+, [0-9]+ calls across [0-9]+ files/)) {
                split(substr($0, RSTART, RLENGTH), p, /[ ,]+/)
                hcode = p[2]; hcalls = p[3] + 0; hfiles = p[6] + 0
                seen = 1
            } else {
                say("baseline OWED heading is unparseable: " $0)
                say("expected the form: # OWED: <Code>, <N> calls across <M> files")
                bad = 1
            }
            next
        }
        /^#/ { cl[++nc] = $0; cn[nc] = FNR; next }
        $0 ~ /^[ \t]*$/ { next }
        NF == 3 {
            lines++
            if (seen) {
                n = length(hcode)
                if (substr($2, length($2) - n + 1) == hcode) {
                    sum += $3; files[$1] = 1; matched++
                }
            }
            next
        }
        END {
            if (bad) { exit 1 }
            if (!seen) {
                say("baseline has no OWED: heading, so the self-sum check did not apply.")
                exit 0
            }
            if (headings > 1) {
                say("BASELINE SELF-CHECK FAILED: " headings " OWED: headings found; expected exactly one.")
                exit 1
            }
            if (hcalls < 1) {
                say("BASELINE SELF-CHECK FAILED: the OWED heading claims " hcalls \
                    " calls. A heading that owes nothing is vacuous; delete it.")
                exit 1
            }
            if (matched + 0 < 1) {
                say("BASELINE SELF-CHECK FAILED: the OWED heading names " hcode \
                    " but no data line carries that code; it describes nothing.")
                exit 1
            }
            if (sum + 0 != hcalls) {
                say("BASELINE SELF-CHECK FAILED: the OWED heading says " hcalls " " \
                    hcode " calls, but its data lines sum to " sum + 0 ".")
                say("Fix whichever side is wrong; the data lines are what the gate enforces.")
                exit 1
            }
            nfiles = length(files)
            if (nfiles != hfiles) {
                say("BASELINE SELF-CHECK FAILED: the OWED heading says " hfiles \
                    " files, but " nfiles " distinct file(s) carry " hcode ".")
                say("Fix whichever side is wrong; the data lines are what the gate enforces.")
                exit 1
            }
            re = "(^|[^0-9#])" sum + 0 "([^0-9]|$)"
            dup = 0
            for (i = 1; i <= nc; i++) {
                if (cl[i] ~ re) {
                    say("BASELINE SELF-CHECK FAILED: the total " sum + 0 " is restated in prose at line " cn[i] ".")
                    say("  " cl[i])
                    dup++
                }
            }
            if (dup > 0) {
                say("The total belongs on the OWED: heading alone, where it is checked. Reword the line(s) above.")
                exit 1
            }
            say("baseline agrees with itself: " hcalls " " hcode " calls across " \
                hfiles " files, summed from " matched + 0 " data line(s), stated once.")
        }
    ' "$BASELINE"
    return $?
}

check_baseline_self_consistency || exit 1

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
