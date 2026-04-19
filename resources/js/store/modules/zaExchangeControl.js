import zaExchangeControlService from '@/services/zaExchangeControlService';

/**
 * ZA Exchange Control store (WS 1.3c). Calendar-year keyed.
 * Mirrors zaSavings shape — namespaced, state/getters/actions/mutations,
 * API snake_case mapped to state camelCase on mutation.
 */
const state = () => ({
  calendarYear: null,
  allowances: {
    sda: null,
    fia: null,
  },
  consumed: {
    sdaMinor: 0,
    fiaMinor: 0,
    totalMinor: 0,
  },
  remaining: {
    sdaMinor: 0,
    fiaMinor: 0,
  },
  sarbThresholdMinor: 0,
  transfers: [],
  approvalCheck: null,
  loading: false,
  error: null,
});

const getters = {
  calendarYear: (s) => s.calendarYear,
  allowances: (s) => s.allowances,
  consumed: (s) => s.consumed,
  remaining: (s) => s.remaining,
  sarbThresholdMinor: (s) => s.sarbThresholdMinor,
  transfers: (s) => s.transfers,
  approvalCheck: (s) => s.approvalCheck,
  sdaPercentConsumed: (s) => {
    const cap = s.allowances.sda?.annual_limit || 0;
    return cap > 0 ? (s.consumed.sdaMinor / cap) * 100 : 0;
  },
  fiaPercentConsumed: (s) => {
    const cap = s.allowances.fia?.annual_limit || 0;
    return cap > 0 ? (s.consumed.fiaMinor / cap) * 100 : 0;
  },
  isLoading: (s) => s.loading,
  error: (s) => s.error,
};

const actions = {
  async fetchDashboard({ commit }, calendarYear = null) {
    commit('SET_LOADING', true);
    commit('SET_ERROR', null);
    try {
      const payload = await zaExchangeControlService.getDashboard(calendarYear);
      commit('SET_DASHBOARD', payload.data);
    } catch (err) {
      commit('SET_ERROR', err.response?.data?.message || err.message);
      throw err;
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async fetchTransfers({ commit }, calendarYear = null) {
    commit('SET_LOADING', true);
    try {
      const payload = await zaExchangeControlService.listTransfers(calendarYear);
      commit('SET_TRANSFERS', payload.data);
    } finally {
      commit('SET_LOADING', false);
    }
  },

  async storeTransfer({ dispatch }, data) {
    const payload = await zaExchangeControlService.storeTransfer(data);
    const year = new Date(data.transfer_date).getFullYear();
    await dispatch('fetchDashboard', year);
    await dispatch('fetchTransfers', year);
    return payload.data;
  },

  async checkApproval({ commit }, data) {
    const payload = await zaExchangeControlService.checkApproval(data);
    commit('SET_APPROVAL_CHECK', { inputs: data, result: payload.data });
    return payload.data;
  },

  reset({ commit }) {
    commit('RESET');
  },
};

const mutations = {
  SET_DASHBOARD(state, data) {
    state.calendarYear = data.calendar_year;
    state.allowances = {
      sda: data.allowances?.sda || null,
      fia: data.allowances?.fia || null,
    };
    state.consumed = {
      sdaMinor: data.consumed?.sda_minor ?? 0,
      fiaMinor: data.consumed?.fia_minor ?? 0,
      totalMinor: data.consumed?.total_minor ?? 0,
    };
    state.remaining = {
      sdaMinor: data.remaining?.sda_minor ?? 0,
      fiaMinor: data.remaining?.fia_minor ?? 0,
    };
    state.sarbThresholdMinor = data.sarb_threshold_minor ?? 0;
  },
  SET_TRANSFERS(state, transfers) {
    state.transfers = transfers || [];
  },
  SET_APPROVAL_CHECK(state, payload) {
    state.approvalCheck = payload;
  },
  SET_LOADING(state, v) {
    state.loading = v;
  },
  SET_ERROR(state, e) {
    state.error = e;
  },
  RESET(state) {
    state.calendarYear = null;
    state.allowances = { sda: null, fia: null };
    state.consumed = { sdaMinor: 0, fiaMinor: 0, totalMinor: 0 };
    state.remaining = { sdaMinor: 0, fiaMinor: 0 };
    state.sarbThresholdMinor = 0;
    state.transfers = [];
    state.approvalCheck = null;
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
