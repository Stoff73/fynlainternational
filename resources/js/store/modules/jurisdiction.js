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

// Module registry. Add SA entries in Phase 1 as those modules ship.
const MODULES_BY_JURISDICTION = {
  gb: [
    'protection',
    'savings',
    'investment',
    'retirement',
    'estate',
    'goals',
    'coordination',
  ],
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
      const jurisdictionModules = MODULES_BY_JURISDICTION[code];
      if (jurisdictionModules) {
        modules.push(...jurisdictionModules);
      }
    }
    if (state.crossBorder) {
      modules.push(...CROSS_BORDER_MODULES);
    }
    // De-duplicate in case two jurisdictions share a module name.
    return [...new Set(modules)];
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
