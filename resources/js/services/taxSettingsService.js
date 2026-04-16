import api from './api';

export default {
  // Tax Configuration
  getCurrent() {
    return api.get('/tax-settings/current');
  },

  getAll() {
    return api.get('/tax-settings/all');
  },

  getCalculations() {
    return api.get('/tax-settings/calculations');
  },

  create(configData) {
    return api.post('/tax-settings/create', configData);
  },

  update(configId, configData) {
    return api.put(`/tax-settings/${configId}`, configData);
  },

  setActive(configId) {
    return api.post(`/tax-settings/${configId}/activate`);
  },

  duplicate(configId, data) {
    return api.post(`/tax-settings/${configId}/duplicate`, data);
  },

  delete(configId) {
    return api.delete(`/tax-settings/${configId}`);
  },
};
