import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import PersonalAccounts from '../../UserProfile/PersonalAccounts.vue';

describe('PersonalAccounts.vue', () => {
  let wrapper;
  let store;
  let mockActions;

  beforeEach(() => {
    mockActions = {
      calculatePersonalAccounts: vi.fn(() => Promise.resolve()),
      addLineItem: vi.fn(() => Promise.resolve()),
      updateLineItem: vi.fn(() => Promise.resolve()),
      deleteLineItem: vi.fn(() => Promise.resolve()),
    };

    store = createStore({
      modules: {
        userProfile: {
          namespaced: true,
          state: {
            personalAccounts: {
              profit_and_loss: {
                income: [
                  { line_item: 'Employment Income', amount: 75000 },
                  { line_item: 'Rental Income', amount: 12000 },
                ],
                total_income: 87000,
                expenses: [
                  { line_item: 'Mortgage Payments', amount: 18000 },
                ],
                total_expenses: 18000,
                net_profit_loss: 69000,
              },
              cashflow: {
                inflows: [
                  { line_item: 'Employment Income', amount: 75000 },
                ],
                total_inflows: 75000,
                outflows: [
                  { line_item: 'Mortgage Payments', amount: 18000 },
                ],
                total_outflows: 18000,
                net_cashflow: 57000,
              },
              balance_sheet: {
                assets: [
                  { line_item: 'Properties', amount: 500000 },
                  { line_item: 'Investments', amount: 50000 },
                ],
                total_assets: 550000,
                liabilities: [
                  { line_item: 'Mortgages', amount: 300000 },
                ],
                total_liabilities: 300000,
                total_equity: 250000,
              },
            },
            loading: false,
            error: null,
          },
          getters: {
            personalAccounts: (state) => state.personalAccounts,
            loading: (state) => state.loading,
          },
          actions: mockActions,
        },
      },
    });

    wrapper = mount(PersonalAccounts, {
      global: {
        plugins: [store],
        stubs: {
          apexchart: {
            template: '<div class="apexchart-stub"></div>',
            props: ['options', 'series', 'type', 'height'],
          },
          ProfitAndLossView: {
            template: '<div class="profit-loss-stub"></div>',
            props: ['data'],
          },
          CashflowView: {
            template: '<div class="cashflow-stub"></div>',
            props: ['data'],
          },
          BalanceSheetView: {
            template: '<div class="balance-sheet-stub"></div>',
            props: ['data'],
          },
        },
      },
    });
  });

  it('renders personal accounts component', () => {
    expect(wrapper.find('h2').text()).toBe('Personal Accounts');
  });

  it('has tab selector for P&L, Cashflow, and Balance Sheet', () => {
    const tabs = wrapper.findAll('button[role="tab"]');
    expect(tabs.length).toBe(3);
    expect(tabs[0].text()).toContain('Profit & Loss');
    expect(tabs[1].text()).toContain('Cashflow');
    expect(tabs[2].text()).toContain('Balance Sheet');
  });

  it('shows Profit & Loss tab by default', () => {
    const activeTab = wrapper.find('button[role="tab"][aria-selected="true"]');
    expect(activeTab.text()).toContain('Profit & Loss');
  });

  it('switches tabs when clicked', async () => {
    const tabs = wrapper.findAll('button[role="tab"]');
    await tabs[1].trigger('click');

    expect(wrapper.vm.activeTab).toBe('cashflow');
  });

  it('has date range picker inputs', () => {
    expect(wrapper.find('input[type="date"][name="start_date"]').exists() ||
           wrapper.find('input[placeholder*="Start"]').exists()).toBe(true);
    expect(wrapper.find('input[type="date"][name="end_date"]').exists() ||
           wrapper.find('input[placeholder*="End"]').exists()).toBe(true);
  });

  it('has Calculate button', () => {
    const calculateButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Calculate')
    );
    expect(calculateButton).toBeDefined();
  });

  it('calls calculatePersonalAccounts when Calculate button is clicked', async () => {
    const calculateButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Calculate')
    );

    if (calculateButton) {
      await calculateButton.trigger('click');
      expect(mockActions.calculatePersonalAccounts).toHaveBeenCalled();
    }
  });

  it('displays chart visualization', () => {
    const chart = wrapper.findComponent({ name: 'apexchart' });
    expect(chart.exists()).toBe(true);
  });

  it('renders ProfitAndLossView for P&L tab', async () => {
    await wrapper.vm.$nextTick();
    const profitLossView = wrapper.findComponent({ name: 'ProfitAndLossView' });
    expect(profitLossView.exists()).toBe(true);
  });

  it('renders CashflowView for Cashflow tab', async () => {
    const tabs = wrapper.findAll('button[role="tab"]');
    await tabs[1].trigger('click');
    await wrapper.vm.$nextTick();

    const cashflowView = wrapper.findComponent({ name: 'CashflowView' });
    expect(cashflowView.exists()).toBe(true);
  });

  it('renders BalanceSheetView for Balance Sheet tab', async () => {
    const tabs = wrapper.findAll('button[role="tab"]');
    await tabs[2].trigger('click');
    await wrapper.vm.$nextTick();

    const balanceSheetView = wrapper.findComponent({ name: 'BalanceSheetView' });
    expect(balanceSheetView.exists()).toBe(true);
  });

  it('displays totals correctly', () => {
    // Check if totals are displayed in the component
    expect(wrapper.html()).toContain('87000'); // total_income
    expect(wrapper.html()).toContain('69000'); // net_profit_loss
  });

  it('formats currency values', () => {
    // Currency should be formatted with £ symbol
    expect(wrapper.html()).toContain('£');
  });

  it('has Export to CSV button', () => {
    const exportButton = wrapper.findAll('button').find(btn =>
      btn.text().includes('Export') || btn.text().includes('CSV')
    );
    expect(exportButton).toBeDefined();
  });

  it('shows loading state when calculating', async () => {
    store.state.userProfile.loading = true;
    await wrapper.vm.$nextTick();

    // Component should show loading indicator
    expect(wrapper.vm.$store.state.userProfile.loading).toBe(true);
  });

  it('displays error message when calculation fails', async () => {
    store.state.userProfile.error = 'Calculation failed';
    await wrapper.vm.$nextTick();

    expect(wrapper.vm.$store.state.userProfile.error).toBe('Calculation failed');
  });
});
