/**
 * @vitest-environment node
 */
import { describe, it, expect, beforeEach } from 'vitest';
import {
  setLocalisation,
  getLocalisation,
  resetLocalisation,
} from '@/utils/localisation';

describe('localisation singleton', () => {
  beforeEach(() => {
    resetLocalisation();
  });

  it('is null before hydration', () => {
    expect(getLocalisation()).toBeNull();
  });

  it('stores a session payload block under camelCase keys', () => {
    setLocalisation({
      currency_code: 'ZAR',
      currency_symbol: 'R',
      locale: 'en_ZA',
      date_format: 'd M Y',
    });

    expect(getLocalisation()).toEqual({
      currencyCode: 'ZAR',
      currencySymbol: 'R',
      locale: 'en-ZA',
      dateFormat: 'd M Y',
    });
  });

  it('normalises POSIX locales to BCP-47 once at set time', () => {
    setLocalisation({ currency_code: 'GBP', currency_symbol: '£', locale: 'en_GB', date_format: 'd/m/Y' });
    expect(getLocalisation().locale).toBe('en-GB');
  });

  it('treats null, undefined, and non-object payloads as unset', () => {
    setLocalisation({ currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' });
    setLocalisation(null);
    expect(getLocalisation()).toBeNull();

    setLocalisation('nonsense');
    expect(getLocalisation()).toBeNull();
  });

  it('tolerates a partial payload without throwing', () => {
    setLocalisation({ currency_code: 'ZAR' });
    expect(getLocalisation()).toEqual({
      currencyCode: 'ZAR',
      currencySymbol: null,
      locale: null,
      dateFormat: null,
    });
  });

  it('resets to null', () => {
    setLocalisation({ currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' });
    resetLocalisation();
    expect(getLocalisation()).toBeNull();
  });
});
