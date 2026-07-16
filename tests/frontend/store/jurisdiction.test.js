import { createStore } from 'vuex';
import { describe, it, expect, beforeEach } from 'vitest';
import jurisdiction from '@/store/modules/jurisdiction';
import { getLocalisation } from '@/utils/localisation';
import { getCurrentTaxYear, setJurisdictionTaxYear } from '@/utils/dateFormatter';

function makeStore() {
  return createStore({
    modules: { jurisdiction },
  });
}

describe('jurisdiction store module', () => {
  let store;

  beforeEach(() => {
    store = makeStore();
    store.dispatch('jurisdiction/reset');
  });

  describe('initial state', () => {
    it('starts empty', () => {
      expect(store.state.jurisdiction.activeJurisdictions).toEqual([]);
      expect(store.state.jurisdiction.primaryJurisdiction).toBeNull();
      expect(store.state.jurisdiction.crossBorder).toBe(false);
    });

    it('returns an empty sidebar module list before hydration', () => {
      expect(store.getters['jurisdiction/sidebarModules']).toEqual([]);
    });
  });

  describe('hydrateFromSession', () => {
    it('populates state from a UK-only session payload', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      expect(store.state.jurisdiction.activeJurisdictions).toEqual(['gb']);
      expect(store.state.jurisdiction.primaryJurisdiction).toBe('gb');
      expect(store.state.jurisdiction.crossBorder).toBe(false);
    });

    it('lowercases incoming codes so callers can pass either case', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['GB', 'ZA'],
        primary_jurisdiction: 'GB',
        cross_border: true,
      });

      expect(store.state.jurisdiction.activeJurisdictions).toEqual(['gb', 'za']);
      expect(store.state.jurisdiction.primaryJurisdiction).toBe('gb');
      expect(store.state.jurisdiction.crossBorder).toBe(true);
    });

    it('handles missing or null fields without throwing', () => {
      store.dispatch('jurisdiction/hydrateFromSession', null);

      expect(store.state.jurisdiction.activeJurisdictions).toEqual([]);
      expect(store.state.jurisdiction.primaryJurisdiction).toBeNull();
      expect(store.state.jurisdiction.crossBorder).toBe(false);

      store.dispatch('jurisdiction/hydrateFromSession', {});
      expect(store.state.jurisdiction.activeJurisdictions).toEqual([]);
    });
  });

  describe('sidebarModules getter', () => {
    it('returns UK modules for a GB user', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      const modules = store.getters['jurisdiction/sidebarModules'];
      expect(modules).toContain('protection');
      expect(modules).toContain('savings');
      expect(modules).toContain('investment');
      expect(modules).toContain('retirement');
      expect(modules).toContain('estate');
    });

    it('adds cross-border modules when the flag is on', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: true,
      });

      expect(store.getters['jurisdiction/sidebarModules']).toContain('cross-border');
    });

    it('de-duplicates if two jurisdictions overlap on module names', () => {
      // The module registry is UK-only today; this test proves the Set-based
      // de-duplication works. Once SA ships, a shared module (e.g. goals)
      // should appear once, not twice.
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb', 'gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      const modules = store.getters['jurisdiction/sidebarModules'];
      const unique = [...new Set(modules)];
      expect(modules.length).toBe(unique.length);
    });
  });

  describe('hasJurisdiction getter', () => {
    it('is case-insensitive', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      expect(store.getters['jurisdiction/hasJurisdiction']('gb')).toBe(true);
      expect(store.getters['jurisdiction/hasJurisdiction']('GB')).toBe(true);
      expect(store.getters['jurisdiction/hasJurisdiction']('za')).toBe(false);
    });
  });

  describe('reset', () => {
    it('clears state back to empty', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      store.dispatch('jurisdiction/reset');

      expect(store.state.jurisdiction.activeJurisdictions).toEqual([]);
      expect(store.state.jurisdiction.primaryJurisdiction).toBeNull();
      expect(store.state.jurisdiction.crossBorder).toBe(false);
    });
  });

  describe('localisation side effects (WS3)', () => {
    it('hydrates the localisation singleton and jurisdiction tax year from the session payload', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['za'],
        primary_jurisdiction: 'za',
        cross_border: false,
        localisation: {
          currency_code: 'ZAR',
          currency_symbol: 'R',
          locale: 'en_ZA',
          date_format: 'd M Y',
        },
        tax_year: { label: '2026/27', starts_on: '2026-03-01', ends_on: '2027-02-28' },
      });

      expect(getLocalisation()).toEqual({
        currencyCode: 'ZAR',
        currencySymbol: 'R',
        locale: 'en-ZA',
        dateFormat: 'd M Y',
      });
      expect(getCurrentTaxYear()).toBe('2026/27');
    });

    it('clears both singletons when the payload has no localisation blocks', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      expect(getLocalisation()).toBeNull();
    });

    it('reset clears both singletons', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['za'],
        primary_jurisdiction: 'za',
        cross_border: false,
        localisation: { currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' },
        tax_year: { label: '2026/27', starts_on: '2026-03-01', ends_on: '2027-02-28' },
      });

      store.dispatch('jurisdiction/reset');

      expect(getLocalisation()).toBeNull();
      expect(getCurrentTaxYear(new Date(2026, 6, 16))).toBe('2026/27'); // GB calendar math again
      expect(getCurrentTaxYear(new Date(2026, 2, 5))).toBe('2025/26'); // 5 Mar < 6 Apr ⇒ prior GB year
    });
  });
});
