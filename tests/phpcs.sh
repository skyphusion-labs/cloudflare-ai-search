#!/usr/bin/env bash
# WordPress Coding Standards over the plugin PHP that ships.
#
# THE FILE LIST COMES FROM git ls-files, never from a pattern in
# phpcs.xml.dist. A pattern that misses a file is indistinguishable from a
# file that passed. Test files are excluded: this standard does not describe
# the harness, and php -l plus the suite already cover them.
#
# GATE: a file+sniff pair whose count is higher than
# tests/phpcs-baseline.txt fails (exit 1). So does a pair the baseline does
# not list. A count that fell is printed as FIXED and is not fatal; delete
# that line in the same change. Never raise a count to go green.
#
# EXIT: 0 = no new finding. 1 = new finding, a risen count, or a baseline
# that contradicts its own OWED heading. 2 = COULD NOT MEASURE (missing
# tool, install failed, phpcs did not produce a report). Exit 2 is never a
# pass.
#
# An empty report against a baseline that still owes findings fails. A
# standard that did not load looks the same as a tree that was fully
# cleaned, and the safe direction is red.
#
# Env:
#   PHPCS_HOME       cache for the composer phar (default
#                    ~/.cache/skyphusion-ai-phpcs)
#   PHPCS_ROOT       repo root (default: the repo holding this script)
#   PHPCS_BASELINE   baseline file (default tests/phpcs-baseline.txt)
#   PHPCS_PLANT      path:text; append text to that scanned file in a temp
#                    copy before the check. Proves the gate can fail.
# Needs: git, php, jq, curl. macOS and Linux.

set -u
set -o pipefail

ROOT="${PHPCS_ROOT:-$(cd "$(dirname "$0")/.." && pwd)}"
HOME_DIR="${PHPCS_HOME:-$HOME/.cache/skyphusion-ai-phpcs}"
BASELINE="${PHPCS_BASELINE:-$ROOT/tests/phpcs-baseline.txt}"
# Composer 2.10.3. The hash is of that exact phar from getcomposer.org.
COMPOSER_URL="https://getcomposer.org/download/2.10.3/composer.phar"
COMPOSER_SHA="7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6"
# 10 is under the 11 shipped PHP files this gate was built against, so a
# broken listing fails and deleting one file does not.
FILE_FLOOR=10

cannot() { echo "phpcs: COULD NOT MEASURE: $*" >&2; exit 2; }

for tool in git php jq curl; do
    command -v "$tool" >/dev/null 2>&1 || cannot "missing tool '$tool'"
done

cd "$ROOT" || cannot "cannot cd to $ROOT"

# THE BASELINE MUST AGREE WITH ITSELF before any tool is downloaded. The
# OWED heading states a total and a file count. The gate reads the data
# lines. Those are two facts, and a heading that drifts stays green if
# nothing compares them.
#
# A heading that owes nothing fails. So does a missing heading when data
# lines exist. No data lines and no heading is the clean end state, and
# that is the only case with nothing to sum.
check_baseline_self_consistency() {
    [ -f "$BASELINE" ] || cannot "baseline $BASELINE not found"
    awk -F'\t' '
        function say(s) { print "phpcs: " s > "/dev/stderr" }
        /^# OWED:/ {
            headings++
            if (match($0, /OWED: [0-9]+ findings across [0-9]+ files/)) {
                split(substr($0, RSTART, RLENGTH), p, /[ :]+/)
                hfindings = p[2] + 0
                hfiles = p[5] + 0
                seen = 1
            } else {
                say("baseline OWED heading is unparseable: " $0)
                say("expected the form: # OWED: <N> findings across <M> files")
                bad = 1
            }
            next
        }
        /^#/ { cl[++nc] = $0; cn[nc] = FNR; next }
        $0 ~ /^[ \t]*$/ { next }
        NF == 3 && $1 !~ /^#/ {
            lines++
            sum += $3
            files[$1] = 1
            next
        }
        {
            say("BASELINE SELF-CHECK FAILED: unreadable data line " FNR ": " $0)
            bad = 1
        }
        END {
            if (bad) { exit 1 }
            if (lines + 0 == 0 && !seen) {
                say("baseline has no owed findings.")
                exit 0
            }
            if (!seen) {
                say("BASELINE SELF-CHECK FAILED: data lines exist and there is no OWED heading.")
                exit 1
            }
            if (headings > 1) {
                say("BASELINE SELF-CHECK FAILED: " headings " OWED headings found; expected exactly one.")
                exit 1
            }
            if (hfindings < 1) {
                say("BASELINE SELF-CHECK FAILED: the OWED heading claims " hfindings " findings. A heading that owes nothing is vacuous; delete it.")
                exit 1
            }
            if (lines + 0 < 1) {
                say("BASELINE SELF-CHECK FAILED: the OWED heading names findings but there are no data lines.")
                exit 1
            }
            if (sum + 0 != hfindings) {
                say("BASELINE SELF-CHECK FAILED: the OWED heading says " hfindings " findings, but its data lines sum to " sum + 0 ".")
                say("Fix whichever side is wrong; the data lines are what the gate enforces.")
                exit 1
            }
            nfiles = 0
            for (f in files) { nfiles++ }
            if (nfiles != hfiles) {
                say("BASELINE SELF-CHECK FAILED: the OWED heading says " hfiles " files, but " nfiles " distinct file(s) are listed.")
                say("Fix whichever side is wrong; the data lines are what the gate enforces.")
                exit 1
            }
            re = "(^|[^0-9#])" (sum + 0) "([^0-9]|$)"
            dup = 0
            for (i = 1; i <= nc; i++) {
                if (cl[i] ~ re) {
                    say("BASELINE SELF-CHECK FAILED: the total " (sum + 0) " is restated in prose at line " cn[i] ".")
                    say("  " cl[i])
                    dup++
                }
            }
            if (dup > 0) {
                say("The total belongs on the OWED heading alone, where it is checked. Reword the line(s) above.")
                exit 1
            }
            say("baseline agrees with itself: " hfindings " findings across " hfiles " files, summed from " (lines + 0) " data line(s), stated once.")
        }
    ' "$BASELINE"
    return $?
}

check_baseline_self_consistency || exit 1

# Shipped PHP only. tests/ is the harness.
files=()
while IFS= read -r -d '' f; do
    case "$f" in
        tests/*) ;;
        *) files+=("$f") ;;
    esac
done < <(git ls-files -z '*.php')

nfiles=${#files[@]}
if [ "$nfiles" -lt "$FILE_FLOOR" ]; then
    echo "phpcs: only $nfiles shipped PHP file(s); the listing is wrong, not the tree" >&2
    exit 1
fi

tmp=""
cleanup() {
    if [ -n "$tmp" ]; then
        rm -rf "$tmp"
    fi
}
trap cleanup EXIT

scan_files=()
scan_root="$ROOT"
if [ -n "${PHPCS_PLANT:-}" ]; then
    plant_rel="${PHPCS_PLANT%%:*}"
    plant_text="${PHPCS_PLANT#*:}"
    if [ "$plant_rel" = "$PHPCS_PLANT" ] || [ -z "$plant_text" ]; then
        cannot "PHPCS_PLANT must be path:text"
    fi
    known=0
    for f in "${files[@]}"; do
        if [ "$f" = "$plant_rel" ]; then
            known=1
        fi
    done
    if [ "$known" -ne 1 ]; then
        cannot "plant path $plant_rel is not a scanned file"
    fi
    # pwd -P: macOS mktemp returns /var/folders, and phpcs canonicalises that
    # to /private/var/folders. Stripping the uncanonical prefix then matches
    # nothing, so every existing finding looks new and the plant proves
    # nothing. Resolve the directory before any path is handed to phpcs.
    tmp=$(mktemp -d)
    tmp=$(cd "$tmp" && pwd -P)
    scan_root="$tmp/tree"
    mkdir -p "$scan_root"
    for f in "${files[@]}"; do
        mkdir -p "$scan_root/$(dirname "$f")"
        cp "$ROOT/$f" "$scan_root/$f"
        scan_files+=("$scan_root/$f")
    done
    printf '\n%s\n' "$plant_text" >> "$scan_root/$plant_rel"
else
    scan_files=("${files[@]}")
fi

install_phpcs() {
    if [ -x "$ROOT/vendor/bin/phpcs" ]; then
        return 0
    fi
    [ -f "$ROOT/composer.lock" ] || cannot "composer.lock is missing; the coding-standard versions are not pinned"
    mkdir -p "$HOME_DIR"
    export COMPOSER_HOME="$HOME_DIR/composer-home"
    export COMPOSER_NO_INTERACTION=1
    mkdir -p "$COMPOSER_HOME"
    local -a composer_cmd
    if command -v composer >/dev/null 2>&1; then
        composer_cmd=(composer)
    else
        local phar="$HOME_DIR/composer.phar"
        if [ ! -f "$phar" ]; then
            curl -fsSL -o "$phar" "$COMPOSER_URL" || cannot "could not download composer"
        fi
        local got
        got=$(php -r 'echo hash_file("sha256", $argv[1]);' "$phar") || cannot "could not hash composer.phar"
        if [ "$got" != "$COMPOSER_SHA" ]; then
            rm -f "$phar"
            cannot "composer.phar checksum mismatch"
        fi
        composer_cmd=(php "$phar")
    fi
    "${composer_cmd[@]}" install --no-interaction --no-progress --working-dir="$ROOT" \
        || cannot "composer install failed"
    [ -x "$ROOT/vendor/bin/phpcs" ] || cannot "composer install did not produce vendor/bin/phpcs"
}

install_phpcs

report=$(mktemp)
phpcs_err=$(mktemp)
phpcs_out=$(mktemp)
"$ROOT/vendor/bin/phpcs" \
    --standard="$ROOT/phpcs.xml.dist" \
    --report=json \
    --report-file="$report" \
    --no-cache \
    "${scan_files[@]}" >"$phpcs_out" 2>"$phpcs_err"
rc=$?
if [ "$rc" -gt 2 ]; then
    cat "$phpcs_out" "$phpcs_err" >&2
    cannot "phpcs exited $rc"
fi
php -r 'json_decode((string) file_get_contents($argv[1])); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);' "$report" \
    || cannot "phpcs did not write a JSON report"

observed=$(mktemp)
jq -r --arg root "${scan_root%/}/" '
    .files // {} | to_entries[]
    | .key as $p
    | (if ($p | startswith($root)) then $p[($root | length):] else $p end) as $rel
    | .value.messages[]?
    | [$rel, .source]
    | @tsv
' "$report" | sort | uniq -c | awk '{ print $2 "\t" $3 "\t" $1 }' > "$observed" \
    || cannot "could not read the phpcs report"

awk -F'\t' '
    function say(s) { print "phpcs: " s > "/dev/stderr" }
    NR == FNR {
        if ($0 ~ /^#/ || $0 ~ /^[ \t]*$/) { next }
        if (NF != 3) { next }
        base[$1 "\t" $2] = $3 + 0
        bsum += $3
        next
    }
    NF == 3 {
        obs[$1 "\t" $2] = $3 + 0
        osum += $3
    }
    END {
        if ((bsum + 0) > 0 && (osum + 0) == 0) {
            say("the standard reported no findings, and the baseline still owes some.")
            say("A standard that did not load looks the same as a tree that was fully cleaned. Trim the baseline in the same change only if the tree is actually clean.")
            exit 1
        }
        fail = 0
        for (k in obs) {
            b = (k in base) ? base[k] : 0
            if (obs[k] > b) {
                say("NEW  " k "  (found " obs[k] ", baseline " b ")")
                fail = 1
            }
        }
        for (k in base) {
            o = (k in obs) ? obs[k] : 0
            if (o < base[k]) {
                say("FIXED  " k "  (baseline " base[k] ", now " o "); trim the baseline")
            }
        }
        if (fail) { exit 1 }
        say("PASS, no finding beyond the baseline (" (osum + 0) " findings, " (bsum + 0) " baselined)")
    }
' "$BASELINE" "$observed"
cmp=$?
rm -f "$report" "$observed" "$phpcs_err" "$phpcs_out"
exit "$cmp"
