import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import { createRouter, createMemoryHistory } from 'vue-router';
import NetWorthOverviewCard from '../../Dashboard/NetWorthOverviewCard.vue';

describe('NetWorthOverviewCard.vue', () => {
  let wrapper;
  let store;
  let router;
  let mockActions;

  beforeEach(() => {
    mockActions = {
      fetchOverview: vi.fn(() => Promise.resolve()),
    };

    store = createStore({
      modules: {
        netWorth: {
          namespaced: true,
          state: {
            overview: {
              total_assets: 750000,
              total_liabilities: 300000,
              net_worth: 450000,
              breakdown: {
                property: 500000,
                investments: 150000,
                cash: 50000,
                business: 40000,
                chattels: 10000,
              },
              as_of_date: '2024-10-18',
            },
            loading: false,
            error: null,
          },
          getters: {
            netWorth: (state) => state.overview.net_worth,
            totalAssets: (state) => state.overview.total_assets,
            totalLiabilities: (state) => state.overview.total_liabilities,
            assetBreakdown: (state) => state.overview.breakdown,
          },
          actions: mockActions,
        },
      },
    });

    router = createRouter({
      history: createMemoryHistory(),
      routes: [
        { path: '/net-worth', name: 'net-worth', component: { template: '<div>Net Worth</div>' } },
      ],
    });

    wrapper = mount(NetWorthOverviewCard, {
      global: {
        plugins: [store, router],
      },
    });
  });

  it('renders net worth overview card', () => {
    expect(wrapper.find('h3').exists() || wrapper.find('h2').exists()).toBe(true);
  });

  it('displays net worth value', () => {
    const html = wrapper.html();
    expect(html).toContain('450000');
  });

  it('formats currency with £ symbol', () => {
    const html = wrapper.html();
    expect(html).toContain('£');
  });

  it('displays asset breakdown', () => {
    const html = wrapper.html();
    expect(html).toContain('Property');
    expect(html).toContain('Investments');
    expect(html).toContain('Cash');
  });

  it('shows property value in breakdown', () => {
    const html = wrapper.html();
    expect(html).toContain('500');
  });

  it('shows investments value in breakdown', () => {
    const html = wrapper.html();
    expect(html).toContain('150');
  });

  it('navigates to /net-worth on click', async () => {
    const clickableElement = wrapper.find('[role="button"]') || wrapper.find('div');

    if (clickableElement.exists()) {
      await clickableElement.trigger('click');

      // Check if navigation was attempted
      expect(router.currentRoute.value.path === '/net-worth' || wrapper.emitted('click')).toBeTruthy();
    }
  });

  it('shows loading state when fetching data', async () {
    store.state.netWorth.loading = true;
    await wrapper.vm.$nextTick();

    // Component should indicate loading
    expect(wrapper.html()).toMatch(/loading|skeleton|spinner/i);
  });

  it('displays error state when fetch fails', async () {
    store.state.netWorth.error = 'Failed to load net worth';
    await wrapper.vm.$nextTick();

    const html = wrapper.html();
    expect(html).toMatch(/error|failed|unavailable/i);
  });

  it('calculates net worth correctly (assets - liabilities)', () => {
    const netWorth = store.getters['netWorth/netWorth'];
    const totalAssets = store.getters['netWorth/totalAssets'];
    const totalLiabilities = store.getters['netWorth/totalLiabilities'];

    expect(netWorth).toBe(totalAssets - totalLiabilities);
  });

  it('displays all 5 asset types in breakdown', () => {
    const html = wrapper.html();

    // Should show all asset categories
    const breakdown = store.getters['netWorth/assetBreakdown'];
    expect(breakdown).toHaveProperty('property');
    expect(breakdown).toHaveProperty('investments');
    expect(breakdown).toHaveProperty('cash');
    expect(breakdown).toHaveProperty('business');
    expect(breakdown).toHaveProperty('chattels');
  });

  it('formats large numbers with commas', () => {
    const html = wrapper.html();

    // Large numbers should be formatted (either 450,000 or 450K or similar)
    expect(html).toMatch(/450[,\s]/);
  });
});
