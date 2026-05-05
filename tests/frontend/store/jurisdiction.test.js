import { createStore } from 'vuex';
import { describe, it, expect, beforeEach } from 'vitest';
import jurisdiction from '@/store/modules/jurisdiction';

function makeStore() {
  return createStore({
    modules: { jurisdiction },
  });
}

describe('jurisdiction store module', () => {
  let store;

  beforeEach(() => {
    store = makeStore();
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
    it('returns UK module keys for a GB user', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      const modules = store.getters['jurisdiction/sidebarModules'];
      // Manifest keys are pack-prefixed so GB and ZA can coexist without
      // colliding on shared names like 'protection' or 'retirement'.
      expect(modules).toContain('gb-protection');
      expect(modules).toContain('gb-investments');
      expect(modules).toContain('gb-retirement');
      expect(modules).toContain('gb-estate');
      expect(modules).toContain('gb-dashboard');
      expect(modules).toContain('gb-net-worth');
      // No SA leakage in a UK-only session.
      expect(modules.some((k) => k.startsWith('za-'))).toBe(false);
    });

    it('returns SA module keys for a ZA user with no UK leakage', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['za'],
        primary_jurisdiction: 'za',
        cross_border: false,
      });

      const modules = store.getters['jurisdiction/sidebarModules'];
      expect(modules).toContain('za-savings');
      expect(modules).toContain('za-investments');
      expect(modules).toContain('za-retirement');
      expect(modules).toContain('za-protection');
      expect(modules).toContain('za-exchange-control');
      // A pure SA user must not see any UK modules — that's the contract.
      expect(modules.some((k) => k.startsWith('gb-'))).toBe(false);
    });

    it('adds cross-border modules when the flag is on', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: true,
      });

      expect(store.getters['jurisdiction/sidebarModules']).toContain('cross-border');
    });

    it('de-duplicates if two jurisdictions overlap on module keys', () => {
      // Pack-prefixed keys make collisions unlikely, but if a future pack ever
      // contributes a duplicate key the Set-based de-dup keeps each entry once.
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

  describe('sidebarSections getter', () => {
    it('returns no sections when the user has no jurisdiction', () => {
      expect(store.getters['jurisdiction/sidebarSections']).toEqual([]);
    });

    it('groups GB items under shared section keys', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });

      const sections = store.getters['jurisdiction/sidebarSections'];
      const sectionKeys = sections.map((s) => s.key);
      expect(sectionKeys).toEqual(['cashManagement', 'finances', 'family', 'planning']);
    });

    it('GB and ZA users see the same section structure (the two-apps contract)', () => {
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['gb'],
        primary_jurisdiction: 'gb',
        cross_border: false,
      });
      const gbSections = store.getters['jurisdiction/sidebarSections'].map((s) => s.key);

      store.dispatch('jurisdiction/reset');
      store.dispatch('jurisdiction/hydrateFromSession', {
        active_jurisdictions: ['za'],
        primary_jurisdiction: 'za',
        cross_border: false,
      });
      const zaSections = store.getters['jurisdiction/sidebarSections'].map((s) => s.key);

      // ZA today has no cashManagement or planning items — so those sections
      // are correctly hidden. The sections ZA does have must use the same
      // section keys as GB (no SA-specific 'zaSection' bucket).
      for (const key of zaSections) {
        expect(gbSections).toContain(key);
      }
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
});
