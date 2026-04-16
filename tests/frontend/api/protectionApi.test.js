import { describe, it, expect, beforeAll } from 'vitest';
import axios from 'axios';

const API_BASE_URL = 'http://127.0.0.1:8000/api';
let authToken = null;
let testUserId = null;

describe('Protection API Integration Tests', () => {
  beforeAll(async () => {
    // Register a test user for API testing
    const timestamp = Date.now();
    const testUser = {
      name: 'API Test User',
      email: `apitest${timestamp}@test.com`,
      password: 'Password123!',
      password_confirmation: 'Password123!',
      date_of_birth: '1990-01-01',
      gender: 'male',
      marital_status: 'single',
    };

    try {
      const registerResponse = await axios.post(`${API_BASE_URL}/auth/register`, testUser);
      authToken = registerResponse.data.token;
      testUserId = registerResponse.data.user.id;
    } catch (error) {
      console.error('Setup failed:', error.response?.data || error.message);
      throw error;
    }
  });

  it('should fetch protection data with authentication', async () => {
    const response = await axios.get(`${API_BASE_URL}/protection`, {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept: 'application/json',
      },
    });

    expect(response.status).toBe(200);
    expect(response.data).toHaveProperty('success', true);
    expect(response.data).toHaveProperty('data');
  });

  it('should reject unauthenticated requests', async () => {
    try {
      await axios.get(`${API_BASE_URL}/protection`, {
        headers: {
          Accept: 'application/json',
        },
      });
      // Should not reach here
      expect(true).toBe(false);
    } catch (error) {
      expect(error.response.status).toBe(401);
    }
  });

  it('should create a protection profile', async () => {
    const profileData = {
      annual_income: 50000,
      employment_status: 'employed',
      number_of_dependents: 2,
      dependents_ages: [5, 8],
      outstanding_mortgage: 200000,
      other_debts: 10000,
      existing_savings: 20000,
    };

    const response = await axios.post(`${API_BASE_URL}/protection/profile`, profileData, {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });

    expect(response.status).toBe(201);
    expect(response.data).toHaveProperty('success', true);
    expect(response.data.data).toHaveProperty('annual_income', 50000);
  });

  it('should create a life insurance policy', async () => {
    const policyData = {
      provider: 'Test Insurance Co',
      policy_number: 'TEST123456',
      sum_assured: 500000,
      premium_amount: 50,
      premium_frequency: 'monthly',
      start_date: '2020-01-01',
      end_date: '2040-01-01',
      smoker_status: 'non-smoker',
      with_critical_illness: false,
    };

    const response = await axios.post(`${API_BASE_URL}/protection/policies/life`, policyData, {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });

    expect(response.status).toBe(201);
    expect(response.data).toHaveProperty('success', true);
    expect(response.data.data).toHaveProperty('provider', 'Test Insurance Co');
    expect(response.data.data).toHaveProperty('sum_assured', 500000);
  });

  it('should validate required fields for life insurance policy', async () => {
    const invalidPolicyData = {
      provider: 'Test Insurance Co',
      // Missing required fields
    };

    try {
      await axios.post(`${API_BASE_URL}/protection/policies/life`, invalidPolicyData, {
        headers: {
          Authorization: `Bearer ${authToken}`,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      });
      // Should not reach here
      expect(true).toBe(false);
    } catch (error) {
      expect(error.response.status).toBe(422);
      expect(error.response.data).toHaveProperty('errors');
    }
  });

  it('should create a critical illness policy', async () => {
    const policyData = {
      provider: 'Critical Care Insurance',
      policy_number: 'CRT987654',
      sum_assured: 100000,
      premium_amount: 30,
      premium_frequency: 'monthly',
      start_date: '2020-01-01',
      end_date: '2040-01-01',
      conditions_covered: ['cancer', 'heart attack', 'stroke'],
    };

    const response = await axios.post(
      `${API_BASE_URL}/protection/policies/critical-illness`,
      policyData,
      {
        headers: {
          Authorization: `Bearer ${authToken}`,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }
    );

    expect(response.status).toBe(201);
    expect(response.data).toHaveProperty('success', true);
    expect(response.data.data).toHaveProperty('provider', 'Critical Care Insurance');
  });

  it('should create an income protection policy', async () => {
    const policyData = {
      provider: 'Income Protect Ltd',
      policy_number: 'IP555444',
      benefit_amount: 3000,
      benefit_frequency: 'monthly',
      deferred_period_weeks: 13,
      benefit_period_months: 24,
      premium_amount: 25,
      premium_frequency: 'monthly',
      start_date: '2021-01-01',
      end_date: '2041-01-01',
      occupation_class: '1',
    };

    const response = await axios.post(
      `${API_BASE_URL}/protection/policies/income-protection`,
      policyData,
      {
        headers: {
          Authorization: `Bearer ${authToken}`,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }
    );

    expect(response.status).toBe(201);
    expect(response.data).toHaveProperty('success', true);
    expect(response.data.data).toHaveProperty('benefit_amount', 3000);
  });

  it('should analyze protection coverage', async () => {
    const response = await axios.post(
      `${API_BASE_URL}/protection/analyze`,
      {},
      {
        headers: {
          Authorization: `Bearer ${authToken}`,
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
      }
    );

    expect(response.status).toBe(200);
    expect(response.data).toHaveProperty('success', true);
    expect(response.data.data).toHaveProperty('adequacy_score');
    expect(response.data.data).toHaveProperty('coverage_gaps');
    expect(response.data.data).toHaveProperty('total_coverage');
  });

  it('should fetch recommendations', async () => {
    const response = await axios.get(`${API_BASE_URL}/protection/recommendations`, {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept: 'application/json',
      },
    });

    expect(response.status).toBe(200);
    expect(response.data).toHaveProperty('success', true);
    expect(Array.isArray(response.data.data)).toBe(true);
  });

  it('should run what-if scenario', async () => {
    const scenarioData = {
      scenario_type: 'death',
      coverage_adjustment: 100000,
    };

    const response = await axios.post(`${API_BASE_URL}/protection/scenarios`, scenarioData, {
      headers: {
        Authorization: `Bearer ${authToken}`,
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
    });

    expect(response.status).toBe(200);
    expect(response.data).toHaveProperty('success', true);
    expect(response.data.data).toHaveProperty('scenario_type', 'death');
  });
});
