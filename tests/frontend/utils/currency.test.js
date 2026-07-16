import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  formatCurrency,
  formatCurrencyWithPence,
  formatCurrencyCompact,
  parseCurrency,
} from '@/utils/currency';
import { formatZAR, formatZARCompact } from '@/utils/zaCurrency';
import { setLocalisation, resetLocalisation } from '@/utils/localisation';
import { currencyMixin } from '@/mixins/currencyMixin';

const ZA = { currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' };
const NBSP = '\u00a0';

// currencyMixin methods use `this`-free helpers except formatLiability;
// calling via the methods object with a stub `this` is enough.
const mixin = {
  ...currencyMixin.methods,
};

describe('currency formatting — GB regression (config unset)', () => {
  beforeEach(() => resetLocalisation());

  it('formats GBP exactly as before', () => {
    expect(formatCurrency(1234.56)).toBe('£1,235');
    expect(formatCurrencyWithPence(1234.56)).toBe('£1,234.56');
    expect(formatCurrency(0)).toBe('£0');
    expect(formatCurrency(null)).toBe('£0');
  });

  it('formats compact GBP exactly as before', () => {
    expect(formatCurrencyCompact(1234567)).toBe('£1.2M');
    expect(formatCurrencyCompact(12345)).toBe('£12.3K');
    expect(formatCurrencyCompact(123)).toBe('£123');
    expect(formatCurrencyCompact(0)).toBe('£0');
  });

  it('parses GBP strings exactly as before', () => {
    expect(parseCurrency('£1,234.56')).toBe(1234.56);
    expect(parseCurrency('1234')).toBe(1234);
  });

  it('formatNumber stays en-GB', () => {
    expect(mixin.formatNumber(1234567)).toBe('1,234,567');
  });
});

describe('currency formatting — GBP session config', () => {
  beforeEach(() => setLocalisation({ currency_code: 'GBP', currency_symbol: '£', locale: 'en_GB', date_format: 'd/m/Y' }));
  afterEach(() => resetLocalisation());

  it('is byte-for-byte identical to the unset path', () => {
    expect(formatCurrency(1234.56)).toBe('£1,235');
    expect(formatCurrencyWithPence(1234.56)).toBe('£1,234.56');
    expect(formatCurrencyCompact(1234567)).toBe('£1.2M');
  });
});

describe('currency formatting — ZAR session config', () => {
  beforeEach(() => setLocalisation(ZA));
  afterEach(() => resetLocalisation());

  it('routes whole amounts through formatZAR without decimals', () => {
    expect(formatCurrency(1234.56)).toBe(`R${NBSP}1${NBSP}235`);
    expect(formatCurrency(0)).toBe(`R${NBSP}0`);
    expect(formatCurrency(null)).toBe(`R${NBSP}0`);
  });

  it('routes pence-precision amounts through formatZAR with decimals', () => {
    expect(formatCurrencyWithPence(1234.56)).toBe(`R${NBSP}1${NBSP}234.56`);
  });

  it('formats compact ZAR', () => {
    expect(formatCurrencyCompact(1234567)).toBe(`R${NBSP}1.2M`);
    expect(formatCurrencyCompact(12345)).toBe(`R${NBSP}12.3K`);
    expect(formatCurrencyCompact(123)).toBe(`R${NBSP}123`);
  });

  it('parses ZAR strings including the symbol and NBSP grouping', () => {
    expect(parseCurrency(`R${NBSP}1${NBSP}234.56`)).toBe(1234.56);
    expect(parseCurrency('R 1 234.56')).toBe(1234.56);
  });

  it('formatNumber uses the session locale grouping', () => {
    // en-ZA groups with non-breaking spaces in V8's ICU.
    expect(mixin.formatNumber(1234567).replace(/[\s\u00a0\u202f]/g, ' ')).toBe('1 234 567');
  });
});

describe('currency formatting — unknown currency (generic Intl path)', () => {
  beforeEach(() => setLocalisation({ currency_code: 'USD', currency_symbol: '$', locale: 'en_US', date_format: 'd/m/Y' }));
  afterEach(() => resetLocalisation());

  it('formats via Intl with the session locale and currency', () => {
    expect(formatCurrency(1234.56)).toBe('$1,235');
    expect(formatCurrencyWithPence(1234.56)).toBe('$1,234.56');
  });

  it('formats compact using the session symbol', () => {
    expect(formatCurrencyCompact(1234567)).toBe('$1.2M');
  });
});

describe('formatZAR (deterministic rewrite)', () => {
  it('renders period decimals with NBSP grouping per SA Research §17', () => {
    expect(formatZAR(1234567.89)).toBe(`R${NBSP}1${NBSP}234${NBSP}567.89`);
    expect(formatZAR(1234.56, { showDecimals: false })).toBe(`R${NBSP}1${NBSP}235`);
    expect(formatZAR(0)).toBe(`R${NBSP}0.00`);
  });

  it('places the sign before the symbol', () => {
    expect(formatZAR(-123.45)).toBe(`-R${NBSP}123.45`);
    expect(formatZAR(-1234.56, { showDecimals: false })).toBe(`-R${NBSP}1${NBSP}235`);
  });

  it('renders the null sentinel', () => {
    expect(formatZAR(null)).toBe('R —');
    expect(formatZAR(undefined)).toBe('R —');
    expect(formatZAR('abc')).toBe('R —');
  });
});

describe('formatZARCompact', () => {
  it('mirrors the compact thresholds with ZAR conventions', () => {
    expect(formatZARCompact(1234567)).toBe(`R${NBSP}1.2M`);
    expect(formatZARCompact(12345)).toBe(`R${NBSP}12.3K`);
    expect(formatZARCompact(123)).toBe(`R${NBSP}123`);
    expect(formatZARCompact(0)).toBe(`R${NBSP}0`);
    expect(formatZARCompact(-1234567)).toBe(`-R${NBSP}1.2M`);
  });
});
