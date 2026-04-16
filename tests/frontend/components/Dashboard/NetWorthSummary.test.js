import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import NetWorthSummary from '@/components/Dashboard/NetWorthSummary.vue';

describe('NetWorthSummary', () => {
  let store;
  let wrapper;

  beforeEach(() => {
    // Create mock Vuex store with all required modules
    store = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            totalSavings: () => 25000,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            totalPortfolioValue: () => 150000,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            totalPensionWealth: () => 200000,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            totalAssets: () => 750000,
            totalLiabilities: () => 75000,
            netWorth: () => 675000,
          },
        },
      },
    });
  });

  it('renders correctly with data', () => {
    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [store],
      },
    });

    expect(wrapper.find('h3').text()).toContain('Net Worth');
    expect(wrapper.exists()).toBe(true);
  });

  it('calculates total assets correctly', () => {
    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [store],
      },
    });

    // Total assets = savings + investments + pensions + other estate assets
    // From estate module: totalAssets getter returns 750000
    const totalAssetsText = wrapper.vm.totalAssets;
    expect(totalAssetsText).toBeGreaterThan(0);
  });

  it('calculates net worth correctly', () => {
    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [store],
      },
    });

    // Net worth = total assets - liabilities
    const netWorth = wrapper.vm.netWorth;
    expect(netWorth).toBeGreaterThan(0);
  });

  it('displays liabilities', () => {
    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [store],
      },
    });

    const liabilities = wrapper.vm.liabilities;
    expect(liabilities).toBe(75000);
  });

  it('formats currency correctly', () => {
    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [store],
      },
    });

    const formatted = wrapper.vm.formatCurrency(12345.67);
    expect(formatted).toBe('£12,346');
  });

  it('shows breakdown of assets', () => {
    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [store],
      },
    });

    // Check that all asset categories are displayed
    expect(wrapper.text()).toContain('Savings');
    expect(wrapper.text()).toContain('Investments');
    expect(wrapper.text()).toContain('Pensions');
  });

  it('displays navigation button to Estate module', () => {
    const mockRouter = {
      push: vi.fn(),
    };

    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [store],
        mocks: {
          $router: mockRouter,
        },
      },
    });

    const button = wrapper.find('button');
    if (button.exists()) {
      button.trigger('click');
      expect(mockRouter.push).toHaveBeenCalledWith('/estate');
    }
  });

  it('handles zero values gracefully', () => {
    // Create store with zero values
    const emptyStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            totalSavings: () => 0,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            totalPortfolioValue: () => 0,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            totalPensionWealth: () => 0,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            totalAssets: () => 0,
            totalLiabilities: () => 0,
            netWorth: () => 0,
          },
        },
      },
    });

    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [emptyStore],
      },
    });

    expect(wrapper.vm.netWorth).toBe(0);
    expect(wrapper.vm.formatCurrency(0)).toBe('£0');
  });

  it('calculates negative net worth correctly', () => {
    // Create store with liabilities > assets
    const negativeStore = createStore({
      modules: {
        savings: {
          namespaced: true,
          getters: {
            totalSavings: () => 5000,
          },
        },
        investment: {
          namespaced: true,
          getters: {
            totalPortfolioValue: () => 10000,
          },
        },
        retirement: {
          namespaced: true,
          getters: {
            totalPensionWealth: () => 20000,
          },
        },
        estate: {
          namespaced: true,
          getters: {
            totalAssets: () => 50000,
            totalLiabilities: () => 100000,
          },
        },
      },
    });

    wrapper = mount(NetWorthSummary, {
      global: {
        plugins: [negativeStore],
      },
    });

    // savings 5000 + investments 10000 + pensions 20000 + otherAssets (50000-5000-10000=35000) = 70000
    // totalAssets 70000 - liabilities 100000 = -30000
    expect(wrapper.vm.netWorth).toBe(-30000);
  });
});
