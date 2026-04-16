import api from './api';

export default {
  // Property CRUD operations
  async getProperties() {
    const response = await api.get('/properties');
    return response.data;
  },

  async getProperty(id) {
    const response = await api.get(`/properties/${id}`);
    return response.data;
  },

  async createProperty(data) {
    const response = await api.post('/properties', data);
    return response.data;
  },

  async updateProperty(id, data) {
    const response = await api.put(`/properties/${id}`, data);
    return response.data;
  },

  async deleteProperty(id) {
    const response = await api.delete(`/properties/${id}`);
    return response.data;
  },

  // Property tax calculations
  async calculateSDLT(data) {
    const response = await api.post('/properties/calculate-sdlt', data);
    return response.data;
  },

  async calculateCGT(propertyId, data) {
    const response = await api.post(`/properties/${propertyId}/calculate-cgt`, data);
    return response.data;
  },

  async calculateRentalIncomeTax(propertyId) {
    const response = await api.post(`/properties/${propertyId}/rental-income-tax`);
    return response.data;
  },

  // Mortgage operations for a property
  async getPropertyMortgages(propertyId) {
    const response = await api.get(`/properties/${propertyId}/mortgages`);
    return response.data;
  },

  async createPropertyMortgage(propertyId, data) {
    const response = await api.post(`/properties/${propertyId}/mortgages`, data);
    return response.data;
  },

  async updatePropertyMortgage(propertyId, mortgageId, data) {
    const response = await api.put(`/properties/${propertyId}/mortgages/${mortgageId}`, data);
    return response.data;
  },

  async deletePropertyMortgage(propertyId, mortgageId) {
    const response = await api.delete(`/properties/${propertyId}/mortgages/${mortgageId}`);
    return response.data;
  },
};
