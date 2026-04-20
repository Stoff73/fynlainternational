import zaProtectionService from '@/services/zaProtectionService';

const state = () => ({
  policies: [],
  beneficiaries: {}, // keyed by policy id
  policyTypes: [],
  taxTreatments: {}, // keyed by product_type
  coverageGap: null,
  dashboard: null,
  loading: false,
  error: null,
});

const getters = {
  isLoaded: (state) => state.dashboard !== null,
  policiesByType: (state) => {
    return state.policies.reduce((acc, p) => {
      acc[p.product_type] = acc[p.product_type] || [];
      acc[p.product_type].push(p);
      return acc;
    }, {});
  },
  beneficiariesForPolicy: (state) => (policyId) => state.beneficiaries[policyId] || [],
};

const mutations = {
  setLoading(state, v) { state.loading = v; },
  setError(state, err) { state.error = err; },
  setDashboard(state, payload) { state.dashboard = payload; },
  setPolicies(state, list) { state.policies = list; },
  addPolicy(state, p) { state.policies.push(p); },
  updatePolicy(state, p) {
    const i = state.policies.findIndex((x) => x.id === p.id);
    if (i >= 0) state.policies.splice(i, 1, p);
  },
  removePolicy(state, id) {
    const i = state.policies.findIndex((x) => x.id === id);
    if (i >= 0) state.policies.splice(i, 1);
  },
  setPolicyTypes(state, list) { state.policyTypes = list; },
  setTaxTreatment(state, { type, data }) { state.taxTreatments = { ...state.taxTreatments, [type]: data }; },
  setCoverageGap(state, payload) { state.coverageGap = payload; },
  setBeneficiaries(state, { policyId, list }) {
    state.beneficiaries = { ...state.beneficiaries, [policyId]: list };
  },
  reset(state) {
    state.policies = [];
    state.beneficiaries = {};
    state.policyTypes = [];
    state.taxTreatments = {};
    state.coverageGap = null;
    state.dashboard = null;
    state.loading = false;
    state.error = null;
  },
};

const actions = {
  async fetchDashboard({ commit }) {
    commit('setLoading', true);
    try {
      const response = await zaProtectionService.getDashboard();
      commit('setDashboard', response.data);
    } catch (e) { commit('setError', e.message); throw e; } finally { commit('setLoading', false); }
  },
  async fetchPolicies({ commit }) {
    commit('setLoading', true);
    try {
      const response = await zaProtectionService.listPolicies();
      commit('setPolicies', response.data);
    } catch (e) { commit('setError', e.message); throw e; } finally { commit('setLoading', false); }
  },
  async createPolicy({ commit, dispatch }, payload) {
    const response = await zaProtectionService.createPolicy(payload);
    commit('addPolicy', response.data);
    await dispatch('fetchCoverageGap');
    return response.data;
  },
  async updatePolicy({ commit, dispatch }, { id, payload }) {
    const response = await zaProtectionService.updatePolicy(id, payload);
    commit('updatePolicy', response.data);
    await dispatch('fetchCoverageGap');
    return response.data;
  },
  async deletePolicy({ commit, dispatch }, id) {
    await zaProtectionService.deletePolicy(id);
    commit('removePolicy', id);
    await dispatch('fetchCoverageGap');
  },
  async fetchPolicyTypes({ commit, state }) {
    if (state.policyTypes.length) return;
    const response = await zaProtectionService.getPolicyTypes();
    commit('setPolicyTypes', response.data);
  },
  async fetchTaxTreatment({ commit, state }, type) {
    if (state.taxTreatments[type]) return state.taxTreatments[type];
    const response = await zaProtectionService.getTaxTreatment(type);
    commit('setTaxTreatment', { type, data: response.data });
    return response.data;
  },
  async fetchCoverageGap({ commit }) {
    const response = await zaProtectionService.getCoverageGap();
    commit('setCoverageGap', { categories: response.data, inputs: response.meta?.inputs });
  },
  async fetchBeneficiaries({ commit }, policyId) {
    const response = await zaProtectionService.listBeneficiaries(policyId);
    commit('setBeneficiaries', { policyId, list: response.data });
  },
  async saveBeneficiaries({ commit }, { policyId, beneficiaries }) {
    const response = await zaProtectionService.saveBeneficiaries(policyId, beneficiaries);
    commit('setBeneficiaries', { policyId, list: response.data });
    return response.data;
  },
  reset({ commit }) { commit('reset'); },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
