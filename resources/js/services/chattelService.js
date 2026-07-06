import api from './api';

export default {
  // Chattel CRUD operations
  async getChattels() {
    const response = await api.get('/gb/chattels');
    return response.data;
  },

  async getChattel(id) {
    const response = await api.get(`/gb/chattels/${id}`);
    return response.data;
  },

  async createChattel(data) {
    const response = await api.post('/gb/chattels', data);
    return response.data;
  },

  async updateChattel(id, data) {
    const response = await api.put(`/gb/chattels/${id}`, data);
    return response.data;
  },

  async deleteChattel(id) {
    const response = await api.delete(`/gb/chattels/${id}`);
    return response.data;
  },

  // CGT calculation for chattel disposal
  async calculateCGT(id, data) {
    const response = await api.post(`/gb/chattels/${id}/calculate-cgt`, data);
    return response.data;
  },
};
