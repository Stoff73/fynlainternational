import api from './api';

/**
 * WS 1.3c — API wrapper for /api/za/investments/*. All wire values in minor
 * units. Resolves pack.za.investment / pack.za.investment.cgt /
 * pack.za.investment.lot_tracker bindings server-side.
 */
const zaInvestmentService = {
  async getDashboard(taxYear = null) {
    const params = taxYear ? { tax_year: taxYear } : {};
    const response = await api.get('/za/investments/dashboard', { params });
    return response.data;
  },

  async listAccounts() {
    const response = await api.get('/za/investments/accounts');
    return response.data;
  },

  async storeAccount(data) {
    const response = await api.post('/za/investments/accounts', data);
    return response.data;
  },

  async listHoldings(accountId = null) {
    const params = accountId ? { account_id: accountId } : {};
    const response = await api.get('/za/investments/holdings', { params });
    return response.data;
  },

  async listLots(holdingId) {
    const response = await api.get(`/za/investments/holdings/${holdingId}/lots`);
    return response.data;
  },

  async storePurchase(data) {
    const response = await api.post('/za/investments/holdings/purchase', data);
    return response.data;
  },

  async recordDisposal(data) {
    const response = await api.post('/za/investments/holdings/disposal', data);
    return response.data;
  },

  async calculateCgt(data) {
    const response = await api.post('/za/investments/cgt/calculate', data);
    return response.data;
  },
};

export default zaInvestmentService;
