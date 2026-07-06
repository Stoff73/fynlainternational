<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Test Gauntlet G-3-d — the automatable slice of the CLAUDE.md numbered rules,
 * enforced statically over the Vue frontend (more reliable than DOM scans).
 *
 * Covers: Rule 6 (currencyMixin, no local formatCurrency), Rule 9 (no banned
 * colour tokens), Rule 13 (no user-facing score displays). These are grep-shaped
 * invariants, so a static ratchet is the right tool — it catches regressions
 * on every run without a browser.
 */

/** @return list<string> every .vue file under the frontend trees */
function vueFiles(): array
{
    $dirs = [
        base_path('resources/js'),
        base_path('packs/country-gb/resources/js'),
        base_path('packs/country-za/resources/js'),
    ];
    $files = [];
    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }
        foreach (File::allFiles($dir) as $f) {
            if ($f->getExtension() === 'vue') {
                $files[] = $f->getPathname();
            }
        }
    }

    return $files;
}

it('Rule 9 — no banned amber/orange colour tokens in Vue', function () {
    $violations = [];
    foreach (vueFiles() as $file) {
        $contents = File::get($file);
        if (preg_match('/\b(amber|orange)-(50|100|200|300|400|500|600|700|800|900)\b/', $contents)) {
            $violations[] = str_replace(base_path().'/', '', $file);
        }
    }

    expect($violations)->toBeEmpty(
        "Banned amber/orange tokens found (use violet for warnings, raspberry for errors):\n".implode("\n", $violations)
    );
});

it('Rule 6 — no local formatCurrency methods (use currencyMixin)', function () {
    $violations = [];
    foreach (vueFiles() as $file) {
        $contents = File::get($file);
        // A method DEFINITION named formatCurrency/formatCurrencyWithPence in the
        // component (not the mixin import, not a call site).
        if (preg_match('/^\s+(formatCurrency|formatCurrencyWithPence)\s*\([^)]*\)\s*\{/m', $contents)) {
            $violations[] = str_replace(base_path().'/', '', $file);
        }
    }

    expect($violations)->toBeEmpty(
        "Local formatCurrency methods found (use the currencyMixin instead):\n".implode("\n", $violations)
    );
});

it('Rule 13 — no user-facing score displays in Vue templates', function () {
    $violations = [];
    // "<label> Score" heading/label text and "/100"-style score values.
    $scoreLabel = '/>[^<]*\b(?:Adequacy|Diversification|Health|Efficiency|Risk|Drift|Portfolio)\s+Score\b/i';
    $scoreOutOf = '/\{\{[^}]*\}\}\s*\/\s*100\b/';

    foreach (vueFiles() as $file) {
        // Version.vue is the dev-facing changelog — it describes the removal of
        // scores ("Score gauge replaced with…"), which is not a score display.
        if (str_ends_with($file, 'views/Version.vue')) {
            continue;
        }
        $contents = File::get($file);
        // Only scan the <template> section (user-facing), not <script>.
        if (! preg_match('/<template>(.*)<\/template>/s', $contents, $m)) {
            continue;
        }
        $template = $m[1];
        if (preg_match($scoreLabel, $template) || preg_match($scoreOutOf, $template)) {
            $violations[] = str_replace(base_path().'/', '', $file);
        }
    }

    expect($violations)->toBeEmpty(
        "User-facing score displays found (Rule 13 — use descriptive text/metrics, not scores):\n".implode("\n", $violations)
    );
});
