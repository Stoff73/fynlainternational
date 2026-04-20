import api from './api';

const BASE = '/api/za/retirement';

export default {
  getDashboard(taxYear) {
    return api.get(`${BASE}/dashboard`, { params: taxYear ? { tax_year: taxYear } : {} });
  },
  listFunds() { return api.get(`${BASE}/funds`); },
  createFund(payload) { return api.post(`${BASE}/funds`, payload); },
  getBuckets(fundId) { return api.get(`${BASE}/funds/${fundId}/buckets`); },
  createContribution(payload) { return api.post(`${BASE}/contributions`, payload); },
  simulateSavingsPot(payload) { return api.post(`${BASE}/savings-pot/simulate`, payload); },
  withdrawSavingsPot(payload) { return api.post(`${BASE}/savings-pot/withdraw`, payload); },
  calculateTaxRelief(payload) { return api.post(`${BASE}/tax-relief/calculate`, payload); },
  quoteLivingAnnuity(payload) { return api.post(`${BASE}/annuities/living/quote`, payload); },
  quoteLifeAnnuity(payload) { return api.post(`${BASE}/annuities/life/quote`, payload); },
  apportionCompulsory(payload) { return api.post(`${BASE}/annuities/compulsory-apportion`, payload); },
  checkReg28(payload) { return api.post(`${BASE}/reg28/check`, payload); },
  listReg28Snapshots(taxYear) {
    return api.get(`${BASE}/reg28/snapshots`, { params: taxYear ? { tax_year: taxYear } : {} });
  },
  storeReg28Snapshot(payload) { return api.post(`${BASE}/reg28/snapshots`, payload); },
};
