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
 *
 * Sidebar entry shape:
 *   { key, label, icon, to, match, requiredPlan?, dynamicLabel? }
 *
 *   key           Stable identifier, unique across all packs (prefix with the
 *                 pack code, e.g. `gb-protection`, `za-protection`).
 *   label         User-facing string. Omit if `dynamicLabel` is set.
 *   dynamicLabel  SideMenu resolver key for labels that depend on runtime
 *                 state (e.g. 'letter' resolves to 'Letter to Spouse' or
 *                 'Expression of Wishes' based on hasSpouse).
 *   icon          Name from resources/js/components/SideMenuIcon.vue.
 *   to            SPA path string OR `{ path, query }` object.
 *   match         How SideMenu computes the "active" highlight state. One of:
 *                   - omitted: prefix-match the route's path against the
 *                     entry's `to` (default behaviour);
 *                   - 'exact': exact path match;
 *                   - 'valuableInfo:<section>': match /valuable-info?section=…;
 *                   - a named matcher key registered in SideMenu's matcher
 *                     map (see ROUTE_MATCHERS in SideMenu.vue), used for the
 *                     handful of items that have bespoke active rules
 *                     (Net Worth, Investments, Estate, etc.).
 *   requiredPlan  Subscription tier needed to use this module. SideMenu
 *                 renders the entry as locked (no router-link, tooltip prompts
 *                 upgrade) when the user's plan is below this tier. Omit for
 *                 entries that are always available.
 *
 * Each pack contributes:
 *   { rootItems: [...], sections: { sectionKey: [item, ...], ... } }
 *
 * Section keys (cashManagement / finances / family / planning) are SHARED
 * across packs so a UK-only user and a SA-only user see the same section
 * structure with country-appropriate items inside. The SideMenu reads
 * sidebarSections (below) which merges items from every active pack into the
 * same section key. An empty section is hidden — packs that don't yet have an
 * item for `cashManagement` (e.g. ZA today) simply produce no header for it.
 */

const SECTION_ORDER = ['cashManagement', 'finances', 'family', 'planning'];

const SECTION_LABELS = {
  cashManagement: { label: 'Cash Management' },
  finances: { label: 'Finances' },
  // 'family' header label depends on hasSpouse — resolved in SideMenu via
  // dynamicSectionLabel='family' so the same key works for both copy variants.
  family: { dynamicLabel: 'family' },
  planning: { label: 'Planning' },
};

const MODULES_BY_JURISDICTION = {
  gb: {
    rootItems: [
      { key: 'gb-dashboard', label: 'Dashboard', icon: 'home', to: '/dashboard', match: 'exact' },
      { key: 'gb-net-worth', label: 'Net Worth', icon: 'chart-bar', to: '/net-worth/wealth-summary', match: 'netWorth' },
    ],
    sections: {
      cashManagement: [
        { key: 'gb-cash', label: 'Bank Accounts', icon: 'banknotes', to: '/net-worth/cash' },
        { key: 'gb-income', label: 'Income', icon: 'currency-pound', to: { path: '/valuable-info', query: { section: 'income' } }, match: 'valuableInfo:income' },
        { key: 'gb-expenditure', label: 'Expenditure', icon: 'arrow-up-tray', to: { path: '/valuable-info', query: { section: 'expenditure' } }, match: 'valuableInfo:expenditure' },
      ],
      finances: [
        { key: 'gb-investments', label: 'Investments', icon: 'trending-up', to: '/net-worth/investments', match: 'investments' },
        { key: 'gb-retirement', label: 'Retirement', icon: 'clock', to: '/net-worth/retirement' },
        { key: 'gb-property', label: 'Property', icon: 'home-modern', to: '/net-worth/property', requiredPlan: 'standard' },
        { key: 'gb-liabilities', label: 'Liabilities', icon: 'credit-card', to: '/net-worth/liabilities', match: 'liabilities', requiredPlan: 'standard' },
        { key: 'gb-chattels', label: 'Personal Valuables', icon: 'cube', to: '/net-worth/chattels', requiredPlan: 'standard' },
        { key: 'gb-risk-profile', label: 'Risk Profile', icon: 'chart-pie', to: '/risk-profile' },
        { key: 'gb-business', label: 'Business', icon: 'briefcase', to: '/net-worth/business', requiredPlan: 'standard' },
      ],
      family: [
        { key: 'gb-protection', label: 'Protection', icon: 'shield-check', to: '/protection' },
        { key: 'gb-will', label: 'Will', icon: 'document-check', to: '/estate/will-builder', match: 'willBuilder', requiredPlan: 'pro' },
        { key: 'gb-letter', dynamicLabel: 'letter', icon: 'envelope', to: { path: '/valuable-info', query: { section: 'letter' } }, match: 'valuableInfo:letter', requiredPlan: 'standard' },
        { key: 'gb-trusts', label: 'Trusts', icon: 'building-library', to: '/trusts', requiredPlan: 'pro' },
        { key: 'gb-estate', label: 'Estate Planning', icon: 'document-text', to: '/estate', match: 'estate', requiredPlan: 'pro' },
        { key: 'gb-poa', label: 'Power of Attorney', icon: 'key', to: '/estate/power-of-attorney', match: 'lpa', requiredPlan: 'pro' },
      ],
      planning: [
        { key: 'gb-holistic-plan', label: 'Holistic Plan', icon: 'puzzle-piece', to: '/holistic-plan', requiredPlan: 'pro' },
        { key: 'gb-plans', label: 'Plans', icon: 'clipboard-list', to: '/plans' },
        { key: 'gb-journeys', label: 'Journeys', icon: 'map', to: '/planning/journeys' },
        { key: 'gb-what-if', label: 'What If Scenarios', icon: 'beaker', to: '/planning/what-if', requiredPlan: 'standard' },
        { key: 'gb-goals', label: 'Goals', icon: 'flag', to: '/goals', match: 'goalsOverview' },
        { key: 'gb-life-events', label: 'Life Events', icon: 'calendar', to: { path: '/goals', query: { tab: 'events' } }, match: 'goalsEvents' },
        { key: 'gb-actions', label: 'Actions', icon: 'lightning-bolt', to: '/actions' },
      ],
    },
  },

  za: {
    rootItems: [],
    sections: {
      finances: [
        { key: 'za-savings', label: 'Savings', icon: 'banknotes', to: '/za/savings' },
        { key: 'za-investments', label: 'Investments', icon: 'trending-up', to: '/za/investments' },
        { key: 'za-retirement', label: 'Retirement', icon: 'clock', to: '/za/retirement' },
        { key: 'za-exchange-control', label: 'Exchange Control', icon: 'map', to: '/za/exchange-control' },
        // WS 1.6b will add za-estate to the family section here.
      ],
      family: [
        { key: 'za-protection', label: 'Protection', icon: 'shield-check', to: '/za/protection' },
      ],
    },
  },
};

const CROSS_BORDER_MODULES = [
  { key: 'cross-border', label: 'Cross-Border', icon: 'globe-alt', to: '/cross-border', section: 'planning' },
];

const state = () => ({
  activeJurisdictions: [],
  primaryJurisdiction: null,
  crossBorder: false,
});

function packFor(code) {
  return MODULES_BY_JURISDICTION[code] || null;
}

const getters = {
  activeJurisdictions: (state) => state.activeJurisdictions,
  primaryJurisdiction: (state) => state.primaryJurisdiction,
  isCrossBorder: (state) => state.crossBorder,

  /**
   * Root-level sidebar items (no section header) for the active jurisdictions.
   * Used by SideMenu to render Dashboard / Net Worth above the section list.
   */
  sidebarRootItems: (state) => {
    const items = [];
    for (const code of state.activeJurisdictions) {
      const pack = packFor(code);
      if (pack && Array.isArray(pack.rootItems)) {
        items.push(...pack.rootItems);
      }
    }
    return items;
  },

  /**
   * Sidebar sections to render, in fixed order. Items from each active pack
   * are merged into the same section key. Empty sections are omitted so the
   * UI doesn't show a header with nothing underneath.
   */
  sidebarSections: (state) => {
    const out = [];
    for (const sectionKey of SECTION_ORDER) {
      const items = [];
      for (const code of state.activeJurisdictions) {
        const pack = packFor(code);
        const sectionItems = pack && pack.sections ? pack.sections[sectionKey] : null;
        if (Array.isArray(sectionItems) && sectionItems.length) {
          items.push(...sectionItems);
        }
      }
      if (state.crossBorder) {
        for (const cb of CROSS_BORDER_MODULES) {
          if (cb.section === sectionKey) items.push(cb);
        }
      }
      if (!items.length) continue;
      out.push({
        key: sectionKey,
        label: SECTION_LABELS[sectionKey].label || null,
        dynamicLabel: SECTION_LABELS[sectionKey].dynamicLabel || null,
        items,
      });
    }
    return out;
  },

  /**
   * Flat list of every sidebar entry's key for the user's active jurisdictions.
   * Retained for non-UI consumers (route guards, tests) — the sidebar itself
   * uses sidebarRootItems and sidebarSections.
   */
  sidebarModules: (_state, localGetters) => {
    const keys = [];
    for (const item of localGetters.sidebarRootItems) keys.push(item.key);
    for (const section of localGetters.sidebarSections) {
      for (const item of section.items) keys.push(item.key);
    }
    return [...new Set(keys)];
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
