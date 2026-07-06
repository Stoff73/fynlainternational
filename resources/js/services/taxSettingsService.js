import api from './api';

export default {
  // Tax Configuration
  getCurrent() {
    return api.get('/gb/tax-settings/current');
  },

  getAll() {
    return api.get('/gb/tax-settings/all');
  },

  getCalculations() {
    return api.get('/gb/tax-settings/calculations');
  },

  create(configData) {
    return api.post('/gb/tax-settings/create', configData);
  },

  update(configId, configData) {
    return api.put(`/gb/tax-settings/${configId}`, configData);
  },

  setActive(configId) {
    return api.post(`/gb/tax-settings/${configId}/activate`);
  },

  duplicate(configId, data) {
    return api.post(`/gb/tax-settings/${configId}/duplicate`, data);
  },

  delete(configId) {
    return api.delete(`/gb/tax-settings/${configId}`);
  },
};
