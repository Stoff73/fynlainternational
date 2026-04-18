/**
 * ZAR formatter. SA Research § 17: 'R 1 234 567.89' — narrow no-break
 * space thousands. Renders with en-ZA locale where supported; falls back
 * to explicit formatting when the browser returns different group
 * characters.
 */

export function formatZAR(value, { showDecimals = true } = {}) {
  if (value === null || value === undefined || Number.isNaN(Number(value))) {
    return 'R —';
  }
  const n = Number(value);
  const opts = {
    minimumFractionDigits: showDecimals ? 2 : 0,
    maximumFractionDigits: showDecimals ? 2 : 0,
    useGrouping: true,
  };
  try {
    const formatted = new Intl.NumberFormat('en-ZA', opts).format(n);
    // Normalise any non-breaking / narrow-no-break space variants to
    // U+00A0 for consistency with visual tests.
    return `R\u00a0${formatted.replace(/[\s\u202f]/g, '\u00a0')}`;
  } catch (e) {
    const [int, frac] = n.toFixed(showDecimals ? 2 : 0).split('.');
    const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0');
    return frac ? `R\u00a0${grouped}.${frac}` : `R\u00a0${grouped}`;
  }
}

export function formatZARMinor(valueMinor, opts = {}) {
  return formatZAR((Number(valueMinor) || 0) / 100, opts);
}

export function toMinorZAR(valueMajor) {
  if (valueMajor === null || valueMajor === undefined || valueMajor === '') return 0;
  return Math.round(Number(valueMajor) * 100);
}
