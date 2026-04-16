import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import ISAAllowanceTracker from '@/components/Savings/ISAAllowanceTracker.vue';

// Helper function to create mock Vuex store
const createMockStore = () => {
  return createStore({
    modules: {
      savings: {
        namespaced: true,
        state: {
          isaAllowance: {
            cash_isa_used: 0,
            stocks_shares_isa_used: 0,
            total_allowance: 20000,
          },
        },
      },
    },
  });
};

describe('ISAAllowanceTracker', () => {
  it('renders with default props', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 0,
        stocksISAUsed: 0,
        taxYear: '2024/25',
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays total ISA allowance (£20,000)', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 5000,
        stocksISAUsed: 3000,
        taxYear: '2024/25',
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/20,000|20000/);
  });

  it('displays cash ISA usage correctly', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 8000,
        stocksISAUsed: 4000,
        taxYear: '2024/25',
      },
    });

    const text = wrapper.text();
    expect(text).toContain('8,000');
    expect(text).toMatch(/cash.*ISA/i);
  });

  it('displays stocks & shares ISA usage correctly', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 5000,
        stocksISAUsed: 7500,
        taxYear: '2024/25',
      },
    });

    const text = wrapper.text();
    expect(text).toContain('7,500');
    expect(text).toMatch(/stock|shares/i);
  });

  it('calculates remaining allowance correctly', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 8000,
        stocksISAUsed: 5000,
        taxYear: '2024/25',
      },
    });

    // 20,000 - 8,000 - 5,000 = 7,000 remaining
    const text = wrapper.text();
    expect(text).toContain('7,000');
    expect(text).toMatch(/remaining|left/i);
  });

  it('calculates usage percentage correctly', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 10000,
        stocksISAUsed: 5000,
        taxYear: '2024/25',
      },
    });

    // 15,000 / 20,000 = 75% used
    const usagePercent = wrapper.vm.usagePercent;
    expect(usagePercent).toBe(75);
  });

  it('displays 0% usage when no ISAs', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 0,
        stocksISAUsed: 0,
        taxYear: '2024/25',
      },
    });

    const usagePercent = wrapper.vm.usagePercent;
    expect(usagePercent).toBe(0);
  });

  it('displays 100% usage when fully used', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 12000,
        stocksISAUsed: 8000,
        taxYear: '2024/25',
      },
    });

    // 20,000 / 20,000 = 100%
    const usagePercent = wrapper.vm.usagePercent;
    expect(usagePercent).toBe(100);
  });

  it('displays tax year correctly', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 5000,
        stocksISAUsed: 3000,
        taxYear: '2024/25',
      },
    });

    const text = wrapper.text();
    expect(text).toContain('2024/25');
  });

  it('shows progress bar', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 8000,
        stocksISAUsed: 4000,
        taxYear: '2024/25',
      },
    });

    // Should have a progress bar or visual indicator
    const html = wrapper.html();
    expect(html).toMatch(/progress|bar|width.*%/i);
  });

  it('warns when allowance is nearly exhausted (>90%)', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 15000,
        stocksISAUsed: 4000,
        taxYear: '2024/25',
      },
    });

    // 19,000 / 20,000 = 95% - should show warning
    const html = wrapper.html();
    // Might show red/warning color
    expect(html).toMatch(/text-red|text-orange|bg-red|bg-orange|warn/i);
  });

  it('shows green when plenty of allowance left (<50%)', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 3000,
        stocksISAUsed: 2000,
        taxYear: '2024/25',
      },
    });

    // 5,000 / 20,000 = 25% - should show green/positive
    const html = wrapper.html();
    expect(html).toMatch(/text-green|bg-green/i);
  });

  it('handles edge case of exceeding allowance', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 15000,
        stocksISAUsed: 10000,
        taxYear: '2024/25',
      },
    });

    // 25,000 exceeds 20,000 allowance
    const usagePercent = wrapper.vm.usagePercent;
    // Might cap at 100% or show > 100%
    expect(usagePercent).toBeGreaterThanOrEqual(100);
  });

  it('displays breakdown of Cash vs Stocks ISA', () => {
    const store = createMockStore();
    const wrapper = mount(ISAAllowanceTracker, {
      global: {
        plugins: [store],
      },
      props: {
        cashISAUsed: 12000,
        stocksISAUsed: 6000,
        taxYear: '2024/25',
      },
    });

    const html = wrapper.html();
    // Should show both types in the breakdown
    expect(html).toMatch(/cash/i);
    expect(html).toMatch(/stock|shares/i);
  });
});
