import api from './api';

const whatIfService = {
  async getScenarios() {
    return (await api.get('/gb/what-if-scenarios')).data;
  },

  async getScenarioComparison(scenarioId) {
    return (await api.get(`/gb/what-if-scenarios/${scenarioId}`)).data;
  },

  async createScenario(data) {
    return (await api.post('/gb/what-if-scenarios', data)).data;
  },

  async deleteScenario(scenarioId) {
    return (await api.delete(`/gb/what-if-scenarios/${scenarioId}`)).data;
  },
};

export default whatIfService;
