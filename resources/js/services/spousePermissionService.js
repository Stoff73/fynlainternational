import api from './api';

/**
 * Spouse Permission Service
 * Handles API calls for spouse data sharing permissions
 */

/**
 * Get current spouse permission status
 */
export const getPermissionStatus = async () => {
  const response = await api.get('/spouse-permission/status');
  return response.data;
};

/**
 * Request permission to view spouse's data
 */
export const requestPermission = async () => {
  const response = await api.post('/spouse-permission/request');
  return response.data;
};

/**
 * Accept a permission request from spouse
 */
export const acceptPermission = async () => {
  const response = await api.post('/spouse-permission/accept');
  return response.data;
};

/**
 * Reject a permission request from spouse
 */
export const rejectPermission = async () => {
  const response = await api.post('/spouse-permission/reject');
  return response.data;
};

/**
 * Revoke existing permission
 */
export const revokePermission = async () => {
  const response = await api.delete('/spouse-permission/revoke');
  return response.data;
};

export default {
  getPermissionStatus,
  requestPermission,
  acceptPermission,
  rejectPermission,
  revokePermission,
};
