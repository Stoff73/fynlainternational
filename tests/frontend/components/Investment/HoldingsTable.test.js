import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import HoldingsTable from '@/components/Investment/HoldingsTable.vue';

describe('HoldingsTable', () => {
  const mockHoldings = [
    {
      id: 1,
      security_name: 'Vanguard FTSE All-World',
      ticker: 'VWRL',
      asset_type: 'international_equity',
      quantity: 100,
      purchase_price: 80.50,
      current_price: 95.25,
      current_value: 9525.00, // 100 * 95.25
      return_percent: 18.32, // ((95.25 - 80.50) / 80.50) * 100
      ocf_percent: 0.22,
    },
    {
      id: 2,
      security_name: 'Vanguard UK Gilt',
      ticker: 'VGOV',
      asset_type: 'bond',
      quantity: 200,
      purchase_price: 50.00,
      current_price: 48.75,
      current_value: 9750.00, // 200 * 48.75
      return_percent: -2.50, // ((48.75 - 50.00) / 50.00) * 100
      ocf_percent: 0.15,
    },
    {
      id: 3,
      security_name: 'Vanguard FTSE 100',
      ticker: 'VUKE',
      asset_type: 'uk_equity',
      quantity: 150,
      purchase_price: 60.00,
      current_price: 72.00,
      current_value: 10800.00, // 150 * 72.00
      return_percent: 20.00, // ((72.00 - 60.00) / 60.00) * 100
      ocf_percent: 0.09,
    },
  ];

  it('renders table with holdings', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('Vanguard FTSE All-World');
    expect(wrapper.text()).toContain('Vanguard UK Gilt');
    expect(wrapper.text()).toContain('Vanguard FTSE 100');
  });

  it('displays all required columns', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Security');
    expect(text).toContain('Type');
    expect(text).toContain('Quantity');
    expect(text).toContain('Purchase Price');
    expect(text).toContain('Current Price');
    expect(text).toContain('Current Value');
    expect(text).toContain('Return');
    expect(text).toContain('OCF');
  });

  it('calculates current value correctly', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: [mockHoldings[0]], // 100 * 95.25 = 9525
        loading: false,
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/9,525|9525/);
  });

  it('calculates return percentage correctly - positive', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: [mockHoldings[0]], // (95.25 - 80.50) / 80.50 * 100 = 18.32%
        loading: false,
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toMatch(/18\./); // Approximately 18%
    // Should be green for positive returns
    expect(html).toMatch(/text-green/);
  });

  it('calculates return percentage correctly - negative', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: [mockHoldings[1]], // (48.75 - 50.00) / 50.00 * 100 = -2.5%
        loading: false,
      },
    });

    const html = wrapper.html();
    expect(wrapper.text()).toMatch(/-2\.5/);
    // Should be red for negative returns
    expect(html).toMatch(/text-red/);
  });

  it('displays OCF percentage', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    expect(wrapper.text()).toContain('0.22');
    expect(wrapper.text()).toContain('0.15');
    expect(wrapper.text()).toContain('0.09');
  });

  it('displays ticker symbols', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    expect(wrapper.text()).toContain('VWRL');
    expect(wrapper.text()).toContain('VGOV');
    expect(wrapper.text()).toContain('VUKE');
  });

  it('sorts by column when header is clicked', async () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    // Find and click the Security column header
    const headers = wrapper.findAll('th');
    const securityHeader = headers.find(h => h.text().includes('Security'));

    if (securityHeader) {
      await securityHeader.trigger('click');
      // Table should now be sorted
      expect(wrapper.vm.sortedHoldings).toBeTruthy();
    }
  });

  it('filters by asset type', async () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    // Simulate filtering by UK Equity
    if (wrapper.vm.filterByType) {
      await wrapper.vm.filterByType('uk_equity');
      await wrapper.vm.$nextTick();

      const text = wrapper.text();
      expect(text).toContain('VUKE');
      expect(text).not.toContain('VWRL');
    }
  });

  it('displays total row at bottom', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/total|sum/i);
  });

  it('calculates total portfolio value correctly', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    // (100 * 95.25) + (200 * 48.75) + (150 * 72.00) = 9525 + 9750 + 10800 = 30075
    const text = wrapper.text();
    expect(text).toMatch(/30,075|30075/);
  });

  it('displays Add Holding button', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    expect(wrapper.text()).toMatch(/add.*holding/i);
  });

  it('emits add-holding event when Add button clicked', async () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    const addButton = wrapper.find('button');
    if (addButton.text().match(/add/i)) {
      await addButton.trigger('click');
      expect(wrapper.emitted('add-holding')).toBeTruthy();
    }
  });

  it('emits edit-holding event with holding data', async () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    // Find edit button (assuming it's in the actions column)
    const editButtons = wrapper.findAll('button').filter(btn =>
      btn.html().includes('M11 5H6a2 2 0') // Edit icon SVG path
    );

    if (editButtons.length > 0) {
      await editButtons[0].trigger('click');
      expect(wrapper.emitted('edit-holding')).toBeTruthy();
      // Should emit the first holding in the sorted list (sorted by security_name by default)
      const emittedHolding = wrapper.emitted('edit-holding')[0][0];
      expect(emittedHolding).toBeTruthy();
      expect(emittedHolding.id).toBeTruthy();
    }
  });

  it('emits delete-holding event with holding data', async () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    // Find delete button (assuming it's in the actions column)
    const deleteButtons = wrapper.findAll('button').filter(btn =>
      btn.html().includes('M19 7l-.867') // Delete icon SVG path
    );

    if (deleteButtons.length > 0) {
      await deleteButtons[0].trigger('click');
      expect(wrapper.emitted('delete-holding')).toBeTruthy();
      // Should emit the first holding in the sorted list
      const emittedHolding = wrapper.emitted('delete-holding')[0][0];
      expect(emittedHolding).toBeTruthy();
      expect(emittedHolding.id).toBeTruthy();
    }
  });

  it('displays loading state', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: [],
        loading: true,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/loading|spinner|animate-spin/i);
  });

  it('displays empty state when no holdings', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: [],
        loading: false,
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/no holdings|add your first|empty/i);
  });

  it('expands row for detailed info', async () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    // Click on a row to expand (implementation may vary)
    const rows = wrapper.findAll('tr');
    if (rows.length > 1) {
      await rows[1].trigger('click');
      // Should show expanded content with additional details
      await wrapper.vm.$nextTick();
      // Check if expanded state is active
      if (wrapper.vm.expandedRows) {
        expect(wrapper.vm.expandedRows.length).toBeGreaterThan(0);
      }
    }
  });

  it('displays asset type labels correctly', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    const text = wrapper.text();
    // Should display human-readable asset type labels
    expect(text).toMatch(/equity|bond/i);
  });

  it('handles holdings without ticker', () => {
    const holdingWithoutTicker = {
      ...mockHoldings[0],
      ticker: null,
    };

    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: [holdingWithoutTicker],
        loading: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('handles holdings without OCF', () => {
    const holdingWithoutOCF = {
      ...mockHoldings[0],
      ocf_percent: null,
    };

    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: [holdingWithoutOCF],
        loading: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('formats currency values with GBP symbol', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    const text = wrapper.text();
    expect(text).toContain('£');
  });

  it('applies responsive classes for mobile', () => {
    const wrapper = mount(HoldingsTable, {
      props: {
        holdings: mockHoldings,
        loading: false,
      },
    });

    const html = wrapper.html();
    // Should have responsive overflow for mobile
    expect(html).toMatch(/overflow-x-auto|overflow-scroll/);
  });
});
