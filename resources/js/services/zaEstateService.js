import api from './api';

const zaEstateService = {
  async getEstateSummary(payload) {
    const { data } = await api.post('/za/estate/summary', payload);
    return data;
  },
  async getExemptions() {
    const { data } = await api.get('/za/estate/exemptions');
    return data;
  },
  async calculateCgtOnDeath(payload) {
    const { data } = await api.post('/za/estate/cgt-on-death', payload);
    return data;
  },
  async listDonations() {
    const { data } = await api.get('/za/estate/donations');
    return data;
  },
  async addDonation(payload) {
    const { data } = await api.post('/za/estate/donations', payload);
    return data;
  },
  async deleteDonation(id) {
    const { data } = await api.delete(`/za/estate/donations/${id}`);
    return data;
  },
  async getDonationsTax() {
    const { data } = await api.get('/za/estate/donations-tax');
    return data;
  },
};

export default zaEstateService;
