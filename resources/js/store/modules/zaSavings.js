import zaSavingsService from '@/services/zaSavingsService';

/**
 * ZA Savings store (WS 1.2b): TFSA caps, contributions list, emergency
 * fund assessment, and ZA savings accounts. Pattern established here;
 * later SA module stores (zaInvestment, zaRetirement, etc.) follow the
 * same shape — namespaced, state/getters/actions/mutations, API
 * snake_case mapped to state camelCase on mutation.
 */
const state = () => ({
  taxYear: null,
  tfsa: {
    annualCapMinor: 0,
    lifetimeCapMinor: 0,
    annualUsedMinor: 0,
    lifetimeUsedMinor: 0,
    annualRemainingMinor: 0,
    lifetimeRemainingMinor: 0,
  },
  contributions: [],
  accounts: [],
  emergencyFund: null,
  loading: false,
  error: null,
});

const getters = {
  taxYear: (s) => s.taxYear,
  tfsa: (s) => s.tfsa,
  contributions: (s) => s.contributions,
  accounts: (s) => s.accounts,
  tfsaAccounts: (s) => s.accounts.filter((a) => a.is_tfsa),
  annualRemainingMinor: (s) => s.tfsa.annualRemainingMinor,
  lifetimeRemainingMinor: (s) => s.tfsa.lifetimeRemainingMinor,
  emergencyFund: (s) => s.emergencyFund,
  isLoading: (s) => s.loading,
  error: (s) => s.error,
};

const actions = {
  async fetchDashboard({ commit }, taxYear = null) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);
    try {
      const payload = await zaSavingsService.getDashboard(taxYear);
      commit('SET_DASHBOARD', payload.data);
    } catch (err) {
      commit('SET_ERROR', err.response?.data?.message || err.message);
      throw err;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async storeContribution({ dispatch }, data) {
    const payload = await zaSavingsService.storeContribution(data);
    await dispatch('fetchDashboard', data.tax_year);
    return payload.data;
  },

  async fetchAccounts({ commit }) {
    commit('SET_LOADING', true);
    try {
      const payload = await zaSavingsService.listAccounts();
      commit('SET_ACCOUNTS', payload.data);
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async storeAccount({ dispatch }, data) {
    const payload = await zaSavingsService.storeAccount(data);
    await dispatch('fetchAccounts');
    return payload.data;
  },

  async assessEmergencyFund({ commit }, data) {
    const payload = await zaSavingsService.assessEmergencyFund(data);
    commit('SET_EMERGENCY_FUND', payload.data);
    return payload.data;
  },

  reset({ commit }) {
    commit('RESET');
  },
};

const mutations = {
  SET_DASHBOARD(state, data) {
    state.taxYear = data.tax_year;
    state.tfsa = {
      annualCapMinor: data.tfsa.annual_cap_minor,
      lifetimeCapMinor: data.tfsa.lifetime_cap_minor,
      annualUsedMinor: data.tfsa.annual_used_minor,
      lifetimeUsedMinor: data.tfsa.lifetime_used_minor,
      annualRemainingMinor: data.tfsa.annual_remaining_minor,
      lifetimeRemainingMinor: data.tfsa.lifetime_remaining_minor,
    };
    state.contributions = data.contributions || [];
  },
  SET_ACCOUNTS(state, accounts) {
    state.accounts = accounts;
  },
  SET_EMERGENCY_FUND(state, payload) {
    state.emergencyFund = payload;
  },
  SET_LOADING(state, v) {
    state.loading = v;
  },
  SET_ERROR(state, e) {
    state.error = e;
  },
  RESET(state) {
    state.taxYear = null;
    state.tfsa = {
      annualCapMinor: 0,
      lifetimeCapMinor: 0,
      annualUsedMinor: 0,
      lifetimeUsedMinor: 0,
      annualRemainingMinor: 0,
      lifetimeRemainingMinor: 0,
    };
    state.contributions = [];
    state.accounts = [];
    state.emergencyFund = null;
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
