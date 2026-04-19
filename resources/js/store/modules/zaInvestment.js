import zaInvestmentService from '@/services/zaInvestmentService';

/**
 * ZA Investment store (WS 1.3c). Mirrors zaSavings shape — namespaced,
 * state/getters/actions/mutations, API snake_case mapped to state
 * camelCase on mutation.
 */
const state = () => ({
  taxYear: null,
  wrappers: [],
  allowances: {
    tfsa: null,
    discretionary: null,
    endowment: null,
  },
  openLotSummary: {
    totalOpenCostBasisMinor: 0,
    lotCount: 0,
  },
  accounts: [],
  holdings: [],
  lotsByHolding: {},
  cgtScenario: null,
  loading: false,
  error: null,
});

const getters = {
  taxYear: (s) => s.taxYear,
  wrappers: (s) => s.wrappers,
  allowances: (s) => s.allowances,
  openLotSummary: (s) => s.openLotSummary,
  accounts: (s) => s.accounts,
  zaAccounts: (s) => s.accounts,
  holdings: (s) => s.holdings,
  lotsForHolding: (s) => (holdingId) => s.lotsByHolding[holdingId] || [],
  cgtScenario: (s) => s.cgtScenario,
  isLoading: (s) => s.loading,
  error: (s) => s.error,
};

const actions = {
  async fetchDashboard({ commit }, taxYear = null) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);
    try {
      const payload = await zaInvestmentService.getDashboard(taxYear);
      commit('SET_DASHBOARD', payload.data);
    } catch (err) {
      commit('SET_ERROR', err.response?.data?.message || err.message);
      throw err;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async fetchAccounts({ commit }) {
    commit('SET_LOADING', true);
    try {
      const payload = await zaInvestmentService.listAccounts();
      commit('SET_ACCOUNTS', payload.data);
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async storeAccount({ dispatch }, data) {
    const payload = await zaInvestmentService.storeAccount(data);
    await dispatch('fetchAccounts');
    return payload.data;
  },

  async fetchHoldings({ commit }, accountId = null) {
    commit('SET_LOADING', true);
    try {
      const payload = await zaInvestmentService.listHoldings(accountId);
      commit('SET_HOLDINGS', payload.data);
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async fetchLots({ commit }, holdingId) {
    const payload = await zaInvestmentService.listLots(holdingId);
    commit('SET_LOTS_FOR_HOLDING', { holdingId, lots: payload.data });
    return payload.data;
  },

  async storePurchase({ dispatch }, data) {
    const payload = await zaInvestmentService.storePurchase(data);
    await dispatch('fetchHoldings');
    if (data.holding_id) {
      await dispatch('fetchLots', data.holding_id);
    }
    return payload.data;
  },

  async recordDisposal({ dispatch }, data) {
    const payload = await zaInvestmentService.recordDisposal(data);
    await dispatch('fetchHoldings');
    if (data.holding_id) {
      await dispatch('fetchLots', data.holding_id);
    }
    return payload.data;
  },

  async calculateCgt({ commit }, data) {
    const payload = await zaInvestmentService.calculateCgt(data);
    commit('SET_CGT_SCENARIO', { inputs: data, result: payload.data });
    return payload.data;
  },

  reset({ commit }) {
    commit('RESET');
  },
};

const mutations = {
  SET_DASHBOARD(state, data) {
    state.taxYear = data.tax_year;
    state.wrappers = data.wrappers || [];
    state.allowances = data.allowances || { tfsa: null, discretionary: null, endowment: null };
    state.openLotSummary = {
      totalOpenCostBasisMinor: data.open_lot_summary?.total_open_cost_basis_minor ?? 0,
      lotCount: data.open_lot_summary?.lot_count ?? 0,
    };
  },
  SET_ACCOUNTS(state, accounts) {
    state.accounts = accounts || [];
  },
  SET_HOLDINGS(state, holdings) {
    state.holdings = holdings || [];
  },
  SET_LOTS_FOR_HOLDING(state, { holdingId, lots }) {
    state.lotsByHolding = { ...state.lotsByHolding, [holdingId]: lots || [] };
  },
  SET_CGT_SCENARIO(state, payload) {
    state.cgtScenario = payload;
  },
  SET_LOADING(state, v) {
    state.loading = v;
  },
  SET_ERROR(state, e) {
    state.error = e;
  },
  RESET(state) {
    state.taxYear = null;
    state.wrappers = [];
    state.allowances = { tfsa: null, discretionary: null, endowment: null };
    state.openLotSummary = { totalOpenCostBasisMinor: 0, lotCount: 0 };
    state.accounts = [];
    state.holdings = [];
    state.lotsByHolding = {};
    state.cgtScenario = null;
    state.loading = false;
    state.error = null;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  actions,
  mutations,
};
