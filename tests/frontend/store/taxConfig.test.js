import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createStore } from 'vuex';

vi.mock('@/services/api', () => ({
  default: { get: vi.fn() },
}));

import api from '@/services/api';
import taxConfig from '@/store/modules/taxConfig';
import jurisdiction from '@/store/modules/jurisdiction';

function makeStore() {
  return createStore({
    modules: { taxConfig, jurisdiction },
  });
}

describe('taxConfig/fetchActive jurisdiction gating (WS3)', () => {
  let store;

  beforeEach(() => {
    vi.clearAllMocks();
    store = makeStore();
    store.dispatch('jurisdiction/reset');
  });

  it('skips the GB endpoint entirely for a ZA-only session', async () => {
    store.dispatch('jurisdiction/hydrateFromSession', {
      active_jurisdictions: ['za'],
      primary_jurisdiction: 'za',
      cross_border: false,
    });

    const result = await store.dispatch('taxConfig/fetchActive');

    expect(result).toBeNull();
    expect(api.get).not.toHaveBeenCalled();
  });

  it('still calls the GB endpoint for a cross-border GB+ZA session with ZA primary', async () => {
    api.get.mockResolvedValue({
      data: { data: { tax_year: '2026/27', effective_from: '2026-04-06', effective_to: '2027-04-05' } },
    });

    store.dispatch('jurisdiction/hydrateFromSession', {
      active_jurisdictions: ['gb', 'za'],
      primary_jurisdiction: 'za',
      cross_border: true,
    });

    const result = await store.dispatch('taxConfig/fetchActive');

    expect(api.get).toHaveBeenCalledWith('/gb/tax-year/current');
    expect(result).toBe('2026/27');
  });

  it('still calls the GB endpoint for a GB-primary session', async () => {
    api.get.mockResolvedValue({
      data: { data: { tax_year: '2026/27', effective_from: '2026-04-06', effective_to: '2027-04-05' } },
    });

    store.dispatch('jurisdiction/hydrateFromSession', {
      active_jurisdictions: ['gb'],
      primary_jurisdiction: 'gb',
      cross_border: false,
    });

    const result = await store.dispatch('taxConfig/fetchActive');

    expect(api.get).toHaveBeenCalledWith('/gb/tax-year/current');
    expect(result).toBe('2026/27');
  });

  it('still calls the GB endpoint when no jurisdiction is hydrated (fail-open)', async () => {
    api.get.mockResolvedValue({
      data: { data: { tax_year: '2026/27', effective_from: '2026-04-06', effective_to: '2027-04-05' } },
    });

    await store.dispatch('taxConfig/fetchActive');

    expect(api.get).toHaveBeenCalledWith('/gb/tax-year/current');
  });
});
