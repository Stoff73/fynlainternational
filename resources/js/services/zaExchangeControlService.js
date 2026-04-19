import api from './api';

/**
 * WS 1.3c — API wrapper for /api/za/exchange-control/*. All wire values in
 * minor units. Resolves pack.za.exchange_control and
 * pack.za.exchange_control.ledger bindings server-side.
 */
const zaExchangeControlService = {
  async getDashboard(calendarYear = null) {
    const params = calendarYear ? { calendar_year: calendarYear } : {};
    const response = await api.get('/za/exchange-control/dashboard', { params });
    return response.data;
  },

  async listTransfers(calendarYear = null) {
    const params = calendarYear ? { calendar_year: calendarYear } : {};
    const response = await api.get('/za/exchange-control/transfers', { params });
    return response.data;
  },

  async storeTransfer(data) {
    const response = await api.post('/za/exchange-control/transfers', data);
    return response.data;
  },

  async checkApproval(data) {
    const response = await api.post('/za/exchange-control/check-approval', data);
    return response.data;
  },
};

export default zaExchangeControlService;
