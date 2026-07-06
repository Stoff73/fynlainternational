import api from './api';

export default {
  // Property CRUD operations
  async getProperties() {
    const response = await api.get('/gb/properties');
    return response.data;
  },

  async getProperty(id) {
    const response = await api.get(`/gb/properties/${id}`);
    return response.data;
  },

  async createProperty(data) {
    const response = await api.post('/gb/properties', data);
    return response.data;
  },

  async updateProperty(id, data) {
    const response = await api.put(`/gb/properties/${id}`, data);
    return response.data;
  },

  async deleteProperty(id) {
    const response = await api.delete(`/gb/properties/${id}`);
    return response.data;
  },

  // Property tax calculations
  async calculateSDLT(data) {
    const response = await api.post('/gb/properties/calculate-sdlt', data);
    return response.data;
  },

  async calculateCGT(propertyId, data) {
    const response = await api.post(`/gb/properties/${propertyId}/calculate-cgt`, data);
    return response.data;
  },

  async calculateRentalIncomeTax(propertyId) {
    const response = await api.post(`/gb/properties/${propertyId}/rental-income-tax`);
    return response.data;
  },

  // Mortgage operations for a property
  async getPropertyMortgages(propertyId) {
    const response = await api.get(`/gb/properties/${propertyId}/mortgages`);
    return response.data;
  },

  async createPropertyMortgage(propertyId, data) {
    const response = await api.post(`/gb/properties/${propertyId}/mortgages`, data);
    return response.data;
  },

  async updatePropertyMortgage(propertyId, mortgageId, data) {
    const response = await api.put(`/gb/properties/${propertyId}/mortgages/${mortgageId}`, data);
    return response.data;
  },

  async deletePropertyMortgage(propertyId, mortgageId) {
    const response = await api.delete(`/gb/properties/${propertyId}/mortgages/${mortgageId}`);
    return response.data;
  },
};
