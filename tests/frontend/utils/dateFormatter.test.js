import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import {
  formatDate,
  formatDateForInput,
  formatDateLong,
  getTaxYearStart,
  getTaxYearEnd,
  getCurrentTaxYear,
  setActiveTaxYear,
  setJurisdictionTaxYear,
} from '@/utils/dateFormatter';
import { setLocalisation, resetLocalisation } from '@/utils/localisation';

const ZA = { currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' };
const ZA_TAX_YEAR = { label: '2026/27', starts_on: '2026-03-01', ends_on: '2027-02-28' };

afterEach(() => {
  resetLocalisation();
  setJurisdictionTaxYear(null);
  setActiveTaxYear(null);
});

describe('formatDate', () => {
  it('renders DD/MM/YYYY when no localisation is set (GB regression)', () => {
    expect(formatDate(new Date(2026, 6, 16))).toBe('16/07/2026');
  });

  it('renders d M Y for the ZA date format', () => {
    setLocalisation(ZA);
    expect(formatDate(new Date(2026, 6, 16))).toBe('16 Jul 2026');
    expect(formatDate(new Date(2026, 0, 3))).toBe('03 Jan 2026');
  });

  it('falls back to DD/MM/YYYY for an unmapped format string', () => {
    setLocalisation({ ...ZA, date_format: 'm-d-Y' });
    expect(formatDate(new Date(2026, 6, 16))).toBe('16/07/2026');
  });

  it('still returns empty string for invalid input', () => {
    setLocalisation(ZA);
    expect(formatDate(null)).toBe('');
    expect(formatDate('not-a-date')).toBe('');
  });
});

describe('formatDateForInput', () => {
  it('stays ISO regardless of localisation', () => {
    setLocalisation(ZA);
    expect(formatDateForInput(new Date(2026, 6, 16))).toBe('2026-07-16');
  });
});

describe('formatDateLong', () => {
  it('uses the session locale', () => {
    setLocalisation(ZA);
    // en-ZA and en-GB both render "16 July 2026" — assert it does not throw
    // on the BCP-47 locale and contains the pieces.
    const result = formatDateLong(new Date(2026, 6, 16));
    expect(result).toContain('2026');
    expect(result).toContain('July');
  });
});

describe('jurisdiction tax year', () => {
  beforeEach(() => setJurisdictionTaxYear(ZA_TAX_YEAR));

  it('getCurrentTaxYear returns the session label when set', () => {
    expect(getCurrentTaxYear()).toBe('2026/27');
  });

  it('boundary maths: 28 Feb is inside the prior year, 1 Mar starts the next', () => {
    expect(getCurrentTaxYear(new Date(2027, 1, 28))).toBe('2026/27');
    expect(getCurrentTaxYear(new Date(2027, 2, 1))).toBe('2027/28');
  });

  it('getTaxYearStart rolls on the 1-March boundary', () => {
    expect(getTaxYearStart(new Date(2026, 6, 16))).toEqual(new Date(2026, 2, 1));
    expect(getTaxYearStart(new Date(2027, 1, 28))).toEqual(new Date(2026, 2, 1));
    expect(getTaxYearStart(new Date(2027, 2, 1))).toEqual(new Date(2027, 2, 1));
  });

  it('getTaxYearEnd is the day before the next boundary (leap-year safe)', () => {
    expect(getTaxYearEnd(new Date(2026, 6, 16))).toEqual(new Date(2027, 1, 28));
    expect(getTaxYearEnd(new Date(2027, 6, 16))).toEqual(new Date(2028, 1, 29));
  });

  it('session tax year wins over the GB admin override; clearing it restores the chain', () => {
    setActiveTaxYear('2025/26');
    expect(getCurrentTaxYear()).toBe('2026/27');

    setJurisdictionTaxYear(null);
    expect(getCurrentTaxYear()).toBe('2025/26');
  });
});

describe('GB tax year regression (no jurisdiction tax year)', () => {
  it('keeps the 6-April boundary and 5-April end', () => {
    expect(getTaxYearStart(new Date(2026, 6, 16))).toEqual(new Date(2026, 3, 6));
    expect(getTaxYearStart(new Date(2026, 3, 5))).toEqual(new Date(2025, 3, 6));
    expect(getTaxYearEnd(new Date(2026, 6, 16))).toEqual(new Date(2027, 3, 5));
    expect(getCurrentTaxYear(new Date(2026, 6, 16))).toBe('2026/27');
  });

  it('honours the GB admin override when no referenceDate is passed', () => {
    setActiveTaxYear('2025/26');
    expect(getCurrentTaxYear()).toBe('2025/26');
    expect(getCurrentTaxYear(new Date(2026, 6, 16))).toBe('2026/27');
  });
});
