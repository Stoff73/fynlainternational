import api from './api';

/**
 * WS 1.4d — API wrapper for /api/za/retirement/*. All wire values in
 * minor units. Resolves pack.za.retirement* and pack.za.reg28.monitor
 * bindings server-side.
 *
 * Each method returns the response JSON body (`{ data: ... }`), matching
 * the zaExchangeControlService pattern.
 */
const BASE = '/za/retirement';

const zaRetirementService = {
  async getDashboard(taxYear = null) {
    const params = taxYear ? { tax_year: taxYear } : {};
    const r = await api.get(`${BASE}/dashboard`, { params });
    return r.data;
  },
  async listFunds() {
    const r = await api.get(`${BASE}/funds`);
    return r.data;
  },
  async createFund(payload) {
    const r = await api.post(`${BASE}/funds`, payload);
    return r.data;
  },
  async getBuckets(fundId) {
    const r = await api.get(`${BASE}/funds/${fundId}/buckets`);
    return r.data;
  },
  async createContribution(payload) {
    const r = await api.post(`${BASE}/contributions`, payload);
    return r.data;
  },
  async simulateSavingsPot(payload) {
    const r = await api.post(`${BASE}/savings-pot/simulate`, payload);
    return r.data;
  },
  async withdrawSavingsPot(payload) {
    const r = await api.post(`${BASE}/savings-pot/withdraw`, payload);
    return r.data;
  },
  async calculateTaxRelief(payload) {
    const r = await api.post(`${BASE}/tax-relief/calculate`, payload);
    return r.data;
  },
  async quoteLivingAnnuity(payload) {
    const r = await api.post(`${BASE}/annuities/living/quote`, payload);
    return r.data;
  },
  async quoteLifeAnnuity(payload) {
    const r = await api.post(`${BASE}/annuities/life/quote`, payload);
    return r.data;
  },
  async apportionCompulsory(payload) {
    const r = await api.post(`${BASE}/annuities/compulsory-apportion`, payload);
    return r.data;
  },
  async checkReg28(payload) {
    const r = await api.post(`${BASE}/reg28/check`, payload);
    return r.data;
  },
  async listReg28Snapshots(taxYear = null) {
    const params = taxYear ? { tax_year: taxYear } : {};
    const r = await api.get(`${BASE}/reg28/snapshots`, { params });
    return r.data;
  },
  async storeReg28Snapshot(payload) {
    const r = await api.post(`${BASE}/reg28/snapshots`, payload);
    return r.data;
  },
};

export default zaRetirementService;
