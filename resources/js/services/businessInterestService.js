import api from './api';

export default {
  // Business Interest CRUD operations
  async getBusinessInterests() {
    const response = await api.get('/gb/business-interests');
    return response.data;
  },

  async getBusinessInterest(id) {
    const response = await api.get(`/gb/business-interests/${id}`);
    return response.data;
  },

  async createBusinessInterest(data) {
    const response = await api.post('/gb/business-interests', data);
    return response.data;
  },

  async updateBusinessInterest(id, data) {
    const response = await api.put(`/gb/business-interests/${id}`, data);
    return response.data;
  },

  async deleteBusinessInterest(id) {
    const response = await api.delete(`/gb/business-interests/${id}`);
    return response.data;
  },

  // Tax deadlines for a business
  async getTaxDeadlines(id) {
    const response = await api.get(`/gb/business-interests/${id}/tax-deadlines`);
    return response.data;
  },

  // Exit/sale CGT calculation
  async getExitCalculation(id) {
    const response = await api.get(`/gb/business-interests/${id}/exit-calculation`);
    return response.data;
  },
};
