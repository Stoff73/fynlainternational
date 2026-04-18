import api from './api';

/**
 * WS 1.2b — API wrapper for /api/za/savings/*. All wire values in minor units.
 * Resolves pack.za.savings / pack.za.tfsa.tracker / pack.za.savings.emergency_fund
 * bindings server-side.
 */
const zaSavingsService = {
  async getDashboard(taxYear = null) {
    const params = taxYear ? { tax_year: taxYear } : {};
    const response = await api.get('/za/savings/dashboard', { params });
    return response.data;
  },

  async listContributions(taxYear = null) {
    const params = taxYear ? { tax_year: taxYear } : {};
    const response = await api.get('/za/savings/contributions', { params });
    return response.data;
  },

  async storeContribution(data) {
    const response = await api.post('/za/savings/contributions', data);
    return response.data;
  },

  async assessEmergencyFund(data) {
    const response = await api.post('/za/savings/emergency-fund/assess', data);
    return response.data;
  },

  async listAccounts() {
    const response = await api.get('/za/savings/accounts');
    return response.data;
  },

  async storeAccount(data) {
    const response = await api.post('/za/savings/accounts', data);
    return response.data;
  },
};

export default zaSavingsService;
