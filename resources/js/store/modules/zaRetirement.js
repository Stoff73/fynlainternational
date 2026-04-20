import zaRetirementService from '@/services/zaRetirementService';

const DEFAULT_ALLOCATION = {
  offshore: 0,
  equity: 0,
  property: 0,
  private_equity: 0,
  commodities: 0,
  hedge_funds: 0,
  other: 0,
  single_entity: 0,
};

const state = () => ({
  taxYear: null,
  annualAllowanceMinor: 0,
  totalBalanceMinor: 0,
  fundCount: 0,
  funds: [],
  bucketsByFundId: {},
  simulatorResult: null,
  taxReliefResult: null,
  annuityQuotes: { living: null, life: null, compulsoryApportion: null },
  reg28Allocation: { ...DEFAULT_ALLOCATION },
  reg28CheckResult: null,
  reg28Snapshots: [],
  loading: false,
  error: null,
});

const getters = {
  fundById: (s) => (id) => s.funds.find((f) => f.id === id),
  bucketsFor: (s) => (id) => s.bucketsByFundId[id] || null,
};

const mutations = {
  setLoading(s, v) { s.loading = v; },
  setError(s, v) { s.error = v; },
  setDashboard(s, d) {
    s.taxYear = d.tax_year;
    s.annualAllowanceMinor = d.annual_allowance_minor;
    s.totalBalanceMinor = d.total_balance_minor;
    s.fundCount = d.fund_count;
  },
  setFunds(s, funds) { s.funds = funds; },
  addFund(s, fund) { s.funds = [...s.funds, fund]; },
  setBucketsForFund(s, { fundId, buckets }) {
    s.bucketsByFundId = { ...s.bucketsByFundId, [fundId]: buckets };
  },
  setSimulatorResult(s, r) { s.simulatorResult = r; },
  setTaxReliefResult(s, r) { s.taxReliefResult = r; },
  setAnnuityQuote(s, { kind, result }) {
    s.annuityQuotes = { ...s.annuityQuotes, [kind]: result };
  },
  setReg28Allocation(s, a) { s.reg28Allocation = { ...s.reg28Allocation, ...a }; },
  setReg28CheckResult(s, r) { s.reg28CheckResult = r; },
  setReg28Snapshots(s, rows) { s.reg28Snapshots = rows; },
  addReg28Snapshot(s, row) { s.reg28Snapshots = [row, ...s.reg28Snapshots]; },
};

const actions = {
  async fetchDashboard({ commit }, { taxYear } = {}) {
    commit('setLoading', true);
    commit('setError', null);
    try {
      const { data } = await zaRetirementService.getDashboard(taxYear);
      commit('setDashboard', data);
    } catch (e) {
      commit('setError', e?.response?.data?.message || 'Failed to load dashboard');
      throw e;
    } finally {
      commit('setLoading', false);
    }
  },
  async fetchFunds({ commit }) {
    const { data } = await zaRetirementService.listFunds();
    commit('setFunds', data);
  },
  async storeFund({ commit }, payload) {
    const { data } = await zaRetirementService.createFund(payload);
    commit('addFund', data);
    return data;
  },
  async fetchBuckets({ commit }, fundId) {
    const { data } = await zaRetirementService.getBuckets(fundId);
    commit('setBucketsForFund', { fundId, buckets: data });
    return data;
  },
  async storeContribution({ commit }, payload) {
    const { data } = await zaRetirementService.createContribution(payload);
    commit('setBucketsForFund', { fundId: payload.fund_holding_id, buckets: data.buckets });
    return data;
  },
  async simulateSavingsPotWithdrawal({ commit }, payload) {
    const { data } = await zaRetirementService.simulateSavingsPot(payload);
    commit('setSimulatorResult', data);
    return data;
  },
  async withdrawSavingsPot({ commit }, payload) {
    const { data } = await zaRetirementService.withdrawSavingsPot(payload);
    commit('setBucketsForFund', { fundId: payload.fund_holding_id, buckets: data.buckets });
    return data;
  },
  async calculateTaxRelief({ commit }, payload) {
    const { data } = await zaRetirementService.calculateTaxRelief(payload);
    commit('setTaxReliefResult', data);
    return data;
  },
  async quoteLivingAnnuity({ commit }, payload) {
    const { data } = await zaRetirementService.quoteLivingAnnuity(payload);
    commit('setAnnuityQuote', { kind: 'living', result: data });
    return data;
  },
  async quoteLifeAnnuity({ commit }, payload) {
    const { data } = await zaRetirementService.quoteLifeAnnuity(payload);
    commit('setAnnuityQuote', { kind: 'life', result: data });
    return data;
  },
  async apportionCompulsory({ commit }, payload) {
    const { data } = await zaRetirementService.apportionCompulsory(payload);
    commit('setAnnuityQuote', { kind: 'compulsoryApportion', result: data });
    return data;
  },
  async checkReg28({ commit }, payload) {
    const { data } = await zaRetirementService.checkReg28(payload);
    commit('setReg28CheckResult', data);
    return data;
  },
  async fetchReg28Snapshots({ commit }, { taxYear } = {}) {
    const { data } = await zaRetirementService.listReg28Snapshots(taxYear);
    commit('setReg28Snapshots', data);
  },
  async storeReg28Snapshot({ commit }, payload) {
    const { data } = await zaRetirementService.storeReg28Snapshot(payload);
    commit('addReg28Snapshot', data);
    return data;
  },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
