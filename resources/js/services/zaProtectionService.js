import api from './api';

const zaProtectionService = {
  async getDashboard() {
    const { data } = await api.get('/za/protection/dashboard');
    return data;
  },
  async listPolicies() {
    const { data } = await api.get('/za/protection/policies');
    return data;
  },
  async getPolicy(id) {
    const { data } = await api.get(`/za/protection/policies/${id}`);
    return data;
  },
  async createPolicy(payload) {
    const { data } = await api.post('/za/protection/policies', payload);
    return data;
  },
  async updatePolicy(id, payload) {
    const { data } = await api.put(`/za/protection/policies/${id}`, payload);
    return data;
  },
  async deletePolicy(id) {
    const { data } = await api.delete(`/za/protection/policies/${id}`);
    return data;
  },
  async getPolicyTypes() {
    const { data } = await api.get('/za/protection/policy-types');
    return data;
  },
  async getTaxTreatment(type) {
    const { data } = await api.get(`/za/protection/tax-treatment/${type}`);
    return data;
  },
  async getCoverageGap() {
    const { data } = await api.get('/za/protection/coverage-gap');
    return data;
  },
  async listBeneficiaries(policyId) {
    const { data } = await api.get(`/za/protection/beneficiaries/${policyId}`);
    return data;
  },
  async saveBeneficiaries(policyId, beneficiaries) {
    const { data } = await api.post(`/za/protection/beneficiaries/${policyId}`, { beneficiaries });
    return data;
  },
};

export default zaProtectionService;
