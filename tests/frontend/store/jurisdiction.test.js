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
});
