import api from './api';

export default {
  // Mortgage CRUD operations
  async getMortgage(id) {
    const response = await api.get(`/gb/mortgages/${id}`);
    return response.data;
  },

  async updateMortgage(id, data) {
    const response = await api.put(`/gb/mortgages/${id}`, data);
    return response.data;
  },

  async deleteMortgage(id) {
    const response = await api.delete(`/gb/mortgages/${id}`);
    return response.data;
  },

  // Mortgage calculations
  async getAmortizationSchedule(id) {
    const response = await api.get(`/gb/mortgages/${id}/amortization-schedule`);
    return response.data;
  },

  async calculatePayment(data) {
    const response = await api.post('/gb/mortgages/calculate-payment', data);
    return response.data;
  },
};
