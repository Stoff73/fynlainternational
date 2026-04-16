import api from './api';

export default {
    /**
     * Get all family members for the authenticated user
     */
    async getFamilyMembers() {
        const response = await api.get('/user/family-members');
        return response.data;
    },

    /**
     * Create a new family member
     */
    async createFamilyMember(data) {
        const response = await api.post('/user/family-members', data);
        return response.data;
    },

    /**
     * Update a family member
     */
    async updateFamilyMember(id, data) {
        const response = await api.put(`/user/family-members/${id}`, data);
        return response.data;
    },

    /**
     * Delete a family member
     */
    async deleteFamilyMember(id) {
        const response = await api.delete(`/user/family-members/${id}`);
        return response.data;
    },
};
