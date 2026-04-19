#!/bin/bash
# Hook: check for hardcoded tax values in changed files.
# Fires on Stop (end of turn) AND PostToolUse for Write/Edit (immediate feedback).
# Catches UK AND South Africa (ZA pack) tax hardcodes.

# Self-locate: repo root is two parents above this script. Falls back to
# CLAUDE_PROJECT_DIR (set by Claude Code at hook invocation), then $(pwd).
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
REPO_ROOT_FROM_SCRIPT="$( cd "$SCRIPT_DIR/../.." && pwd )"

if [ -d "${CLAUDE_PROJECT_DIR:-}" ]; then
  REPO_ROOT="$CLAUDE_PROJECT_DIR"
elif [ -d "$REPO_ROOT_FROM_SCRIPT" ]; then
  REPO_ROOT="$REPO_ROOT_FROM_SCRIPT"
else
  REPO_ROOT="$(pwd)"
fi

cd "$REPO_ROOT" || exit 0

CHANGED_FILES=$(git diff --name-only HEAD 2>/dev/null; git diff --cached --name-only 2>/dev/null)
[ -z "$CHANGED_FILES" ] && exit 0

# Files to skip entirely — config/tests/seeders where hardcodes are legitimate.
is_skipped_path() {
  case "$1" in
    tests/*) return 0 ;;
    database/seeders/*) return 0 ;;
    database/migrations/*) return 0 ;;
    *.json) return 0 ;;
    resources/js/constants/taxConfig.js) return 0 ;;
    resources/js/constants/za-taxConfig.js) return 0 ;;
    resources/js/utils/dateFormatter.js) return 0 ;;
    resources/js/views/Version.vue) return 0 ;;
    app/Services/TaxConfigService.php) return 0 ;;
    packs/country-za/src/Services/*TaxConfigService.php) return 0 ;;
    packs/country-za/config/*) return 0 ;;
    config/taxes.php) return 0 ;;
  esac
  return 1
}

VIOLATIONS=""

# Drop duplicates so a file changed twice isn't reported twice.
for file in $(printf '%s\n' $CHANGED_FILES | sort -u); do
  case "$file" in
    *.vue|*.js|*.php) ;;
    *) continue ;;
  esac
  is_skipped_path "$file" && continue
  [ ! -f "$file" ] && continue

  # --- UK tax-year string: '2025/26', '2026/27' etc. ---
  # Skip lines that look legitimate (comments, service calls, historical refs).
  UK_YEAR=$(grep -n "'20[2-3][0-9]/[0-9][0-9]'" "$file" 2>/dev/null \
    | grep -v "//\|getTaxYear\|getCurrentTaxYear\|TaxConfigService\|@param\|@return\|e\.g\.\|example\|getHistorical\|carryForward\|carry_forward\|previous.*year" \
    || true)
  [ -n "$UK_YEAR" ] && VIOLATIONS="$VIOLATIONS\n  $file [UK tax-year string]:\n$UK_YEAR"

  # --- SA tax-year string: '2025/2026', '2026/2027' (4-digit SA style) ---
  SA_YEAR=$(grep -n "'20[2-3][0-9]/20[2-3][0-9]'" "$file" 2>/dev/null \
    | grep -v "//\|getTaxYear\|getCurrentTaxYear\|TaxConfigService\|ZaTaxConfig\|@param\|@return\|e\.g\.\|example\|getHistorical" \
    || true)
  [ -n "$SA_YEAR" ] && VIOLATIONS="$VIOLATIONS\n  $file [SA tax-year string]:\n$SA_YEAR"

  # --- UK hardcoded allowance values ---
  # ISA £20k, personal allowance £12,570, CGT £3k, NI threshold £12,570, IHT NRB £325k, RNRB £175k,
  # pension annual allowance £60k, MPAA £10k
  UK_VAL=$(grep -nE "(ISA_ALLOWANCE|isa_allowance|isaAllowance)\s*[=:]\s*20000|personalAllowance\s*[=:]\s*12570|cgtAllowance\s*[=:]\s*3000|niThreshold\s*[=:]\s*12570|nilRateBand\s*[=:]\s*325000|residenceNilRateBand\s*[=:]\s*175000|annualAllowance\s*[=:]\s*60000|mpaa\s*[=:]\s*10000" "$file" 2>/dev/null || true)
  [ -n "$UK_VAL" ] && VIOLATIONS="$VIOLATIONS\n  $file [UK hardcoded allowance]:\n$UK_VAL"

  # --- SA hardcoded allowance values ---
  # TFSA annual R36k, TFSA lifetime R500k, retirement deduction cap 27.5% or R350k,
  # primary/secondary/tertiary rebates, CGT annual exclusion R40k, interest exemption R23,800
  SA_VAL=$(grep -nE "tfsaAnnual(Limit)?\s*[=:]\s*36000|tfsaLifetime(Limit)?\s*[=:]\s*500000|retirementDeductionCap\s*[=:]\s*350000|retirementDeductionPercent\s*[=:]\s*27\.5|primaryRebate\s*[=:]\s*17235|secondaryRebate\s*[=:]\s*9444|tertiaryRebate\s*[=:]\s*3145|cgtAnnualExclusion\s*[=:]\s*40000|interestExemption\s*[=:]\s*23800" "$file" 2>/dev/null || true)
  [ -n "$SA_VAL" ] && VIOLATIONS="$VIOLATIONS\n  $file [SA hardcoded allowance]:\n$SA_VAL"
done

if [ -n "$VIOLATIONS" ]; then
  # JSON output for Claude Code's hook protocol — shows as a system message in the transcript.
  printf '{"systemMessage": "⚠ Hardcoded tax values detected in changed files.\\nRule: never hardcode tax years or allowance amounts.\\n- UK values → TaxConfigService (PHP) or getCurrentTaxYear() / taxConfig.js (frontend)\\n- SA values → ZaTaxConfigService (PHP) or za-taxConfig.js (frontend)\\n\\nFiles:%s"}\n' "$(printf "$VIOLATIONS" | sed 's/"/\\"/g' | tr '\n' ' ' | head -c 2000)"
fi

exit 0
