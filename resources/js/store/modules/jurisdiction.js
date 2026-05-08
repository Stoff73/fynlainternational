/**
 * Jurisdiction store module.
 *
 * Single source of truth for the authenticated user's active jurisdictions.
 * Hydrated from the /api/auth/user response, which includes:
 *   {
 *     active_jurisdictions: ['gb'],  // ISO alpha-2, lower-case
 *     primary_jurisdiction: 'gb',
 *     cross_border: false,
 *   }
 *
 * The sidebar composition and route guards read from this module. UK users
 * always have `active_jurisdictions === ['gb']` until WS 0.6 activates
 * cross-border automatically from asset location.
 *
 * NOTE: users never see the word "jurisdiction" in the UI — this is backend
 * plumbing. Cross-border activates silently from asset data, not manual
 * opt-in (ADR: product model memory).
 */

import gbNavigation from '@gb/navigation';
import zaNavigation from '@za/navigation';

// Per-pack navigation registry (R-12). Each pack ships a default-exported
// `navigation()` thunk that returns its sidebar manifest:
//   { code, modules: [...] }
// where `modules` is a flat list of strings (UK — used by `sidebarModules`
// for route guards / feature gating) or full config objects (ZA — used by
// SideMenu.vue's data-driven `<SideMenuItem v-for>`). When R-13a converts
// the UK sidebar to data-driven rendering, the GB manifest gains the
// richer `rootItems` + `sections` shape without touching the registry.
const PACK_NAVIGATIONS = {
  gb: gbNavigation,
  za: zaNavigation,
};

const CROSS_BORDER_MODULES = ['cross-border'];

const state = () => ({
  activeJurisdictions: [],
  primaryJurisdiction: null,
  crossBorder: false,
});

const getters = {
  activeJurisdictions: (state) => state.activeJurisdictions,
  primaryJurisdiction: (state) => state.primaryJurisdiction,
  isCrossBorder: (state) => state.crossBorder,

  /**
   * Compose the module list from the user's active jurisdictions. Empty
   * array for users with no jurisdictions yet — callers must handle that.
   */
  sidebarModules: (state) => {
    const modules = [];
    for (const code of state.activeJurisdictions) {
      const pack = PACK_NAVIGATIONS[code];
      if (!pack) continue;
      const entries = pack()?.modules || [];
      for (const entry of entries) {
        modules.push(typeof entry === 'string' ? entry : entry.key);
      }
    }
    if (state.crossBorder) {
      modules.push(...CROSS_BORDER_MODULES);
    }
    // De-duplicate in case two jurisdictions share a module name.
    return [...new Set(modules)];
  },

  /**
   * The ZA sidebar-config objects for the current user. Empty array
   * when the user isn't ZA-active. Consumed by SideMenu.vue to render
   * the ZA section via v-for.
   */
  zaModules: (state) => {
    if (!state.activeJurisdictions.includes('za')) return [];
    const pack = PACK_NAVIGATIONS.za;
    if (!pack) return [];
    const entries = pack()?.modules || [];
    return entries.filter((e) => typeof e === 'object');
  },

  /**
   * Predicate used by route guards: does the user hold this jurisdiction?
   */
  hasJurisdiction: (state) => (code) =>
    state.activeJurisdictions.includes(String(code).toLowerCase()),
};

const actions = {
  /**
   * Called from auth.fetchUser after /api/auth/user returns. Accepts the
   * raw data payload (not the wrapping response object) so it can be
   * invoked from any API path that re-returns the session shape.
   */
  hydrateFromSession({ commit }, data) {
    const payload = data || {};
    commit('SET_JURISDICTION_STATE', {
      active: Array.isArray(payload.active_jurisdictions)
        ? payload.active_jurisdictions.map((c) => String(c).toLowerCase())
        : [],
      primary: payload.primary_jurisdiction
        ? String(payload.primary_jurisdiction).toLowerCase()
        : null,
      crossBorder: Boolean(payload.cross_border),
    });
  },

  reset({ commit }) {
    commit('SET_JURISDICTION_STATE', {
      active: [],
      primary: null,
      crossBorder: false,
    });
  },
};

const mutations = {
  SET_JURISDICTION_STATE(state, { active, primary, crossBorder }) {
    state.activeJurisdictions = active;
    state.primaryJurisdiction = primary;
    state.crossBorder = crossBorder;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
