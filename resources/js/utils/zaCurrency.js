/**
 * ZAR formatter. SA Research (section 17): 'R 1 234 567.89' — period
 * decimal, no-break-space (U+00A0) thousands grouping, sign before the
 * symbol ('-R 123.45', matching ZaLocalisation's sign placement).
 *
 * Formatted manually, NOT via Intl.NumberFormat('en-ZA'): CLDR data for
 * en-ZA uses comma decimals, which contradicts the research convention
 * and varies across ICU builds. Deterministic output keeps the 32 ZA
 * components and the Vitest assertions stable everywhere.
 */

export function formatZAR(value, { showDecimals = true } = {}) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) {
    return 'R \u2014';
  }
  const n = Number(value);
  const [int, frac] = Math.abs(n).toFixed(showDecimals ? 2 : 0).split('.');
  const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
  const sign = n < 0 ? '-' : '';
  return frac ? `${sign}R\u00a0${grouped}.${frac}` : `${sign}R\u00a0${grouped}`;
}

/**
 * Compact ZAR — mirrors currency.js formatCurrencyCompact thresholds
 * ("R 1.2M" / "R 12.3K") with the SA symbol + NBSP convention.
 */
export function formatZARCompact(value) {
  const n = Number(value) || 0;
  const abs = Math.abs(n);
  if (abs >= 1000000) return `R\u00a0${(n / 1000000).toFixed(1)}M`;
  if (abs >= 1000) return `R\u00a0${(n / 1000).toFixed(1)}K`;
  return formatZAR(n, { showDecimals: false });
}

export function formatZARMinor(valueMinor, opts = {}) {
  return formatZAR((Number(valueMinor) || 0) / 100, opts);
}

export function toMinorZAR(valueMajor) {
  if (valueMajor === null || valueMajor === undefined || valueMajor === '') return 0;
  return Math.round(Number(valueMajor) * 100);
}
