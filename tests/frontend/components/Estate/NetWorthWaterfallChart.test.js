import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import NetWorthWaterfallChart from '@/components/Estate/NetWorthWaterfallChart.vue';

describe('NetWorthWaterfallChart', () => {
  beforeEach(() => {
    if (!global.ApexCharts) {
      global.ApexCharts = class {
        constructor() {}
        render() {}
        updateOptions() {}
        updateSeries() {}
        destroy() {}
      };
    }
  });

  const mockAssets = [
    { asset_type: 'property', current_value: 500000 },
    { asset_type: 'pension', current_value: 300000 },
    { asset_type: 'investment', current_value: 150000 },
    { asset_type: 'savings', current_value: 50000 },
  ];

  const mockLiabilities = [
    { liability_type: 'mortgage', current_balance: 200000 },
    { liability_type: 'personal_loan', current_balance: 30000 },
    { liability_type: 'credit_card', current_balance: 5000 },
  ];

  it('renders with assets and liabilities props', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('calculates total assets correctly', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    expect(wrapper.vm.totalAssets).toBe(1000000);
  });

  it('calculates total liabilities correctly', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    expect(wrapper.vm.totalLiabilities).toBe(235000);
  });

  it('calculates net worth correctly', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    expect(wrapper.vm.netWorth).toBe(765000);
  });

  it('groups assets by type', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const grouped = wrapper.vm.assetsByType;
    expect(grouped.property).toBe(500000);
    expect(grouped.pension).toBe(300000);
    expect(grouped.investment).toBe(150000);
    expect(grouped.savings).toBe(50000);
  });

  it('groups liabilities by type', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const grouped = wrapper.vm.liabilitiesByType;
    expect(grouped.mortgage).toBe(200000);
    expect(grouped.personal_loan).toBe(30000);
    expect(grouped.credit_card).toBe(5000);
  });

  it('generates waterfall series data', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const series = wrapper.vm.series;
    expect(series).toHaveLength(1);
    expect(series[0].name).toBe('Net Worth Flow');
    expect(series[0].data).toBeInstanceOf(Array);
  });

  it('starts waterfall with total assets', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const categories = wrapper.vm.chartOptions.xaxis.categories;
    expect(categories[0]).toMatch(/total.*asset/i);
  });

  it('ends waterfall with net worth', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const categories = wrapper.vm.chartOptions.xaxis.categories;
    const lastCategory = categories[categories.length - 1];
    expect(lastCategory).toMatch(/net.*worth/i);
  });

  it('displays liability categories as negative values', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const seriesData = wrapper.vm.series[0].data;
    // Check that liabilities are represented (indirectly through reduction)
    const totalAssets = seriesData[0];
    const netWorth = seriesData[seriesData.length - 1];
    expect(netWorth).toBeLessThan(totalAssets);
  });

  it('formats currency values correctly', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const formatted = wrapper.vm.formatCurrency(500000);
    expect(formatted).toMatch(/£500,000|500000/);
  });

  it('handles empty assets array', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: [],
        liabilities: mockLiabilities,
      },
    });

    expect(wrapper.vm.totalAssets).toBe(0);
  });

  it('handles empty liabilities array', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: [],
      },
    });

    expect(wrapper.vm.totalLiabilities).toBe(0);
    expect(wrapper.vm.netWorth).toBe(1000000); // All assets, no liabilities
  });

  it('handles negative net worth', () => {
    const highLiabilities = [
      { liability_type: 'mortgage', current_balance: 1200000 },
    ];

    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: highLiabilities,
      },
    });

    expect(wrapper.vm.netWorth).toBe(-200000);
  });

  it('creates waterfall chart configuration', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.chart.type).toBe('bar');
  });

  it('uses correct colors for positive and negative values', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.plotOptions.bar.colors).toBeDefined();
  });

  it('displays asset category labels', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const categories = wrapper.vm.chartOptions.xaxis.categories;
    expect(categories.some(cat => cat.toLowerCase().includes('property'))).toBe(true);
    expect(categories.some(cat => cat.toLowerCase().includes('pension'))).toBe(true);
  });

  it('displays liability category labels', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const categories = wrapper.vm.chartOptions.xaxis.categories;
    expect(categories.some(cat => cat.toLowerCase().includes('mortgage'))).toBe(true);
  });

  it('handles multiple assets of same type', () => {
    const duplicateAssets = [
      { asset_type: 'property', current_value: 300000 },
      { asset_type: 'property', current_value: 200000 },
    ];

    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: duplicateAssets,
        liabilities: [],
      },
    });

    expect(wrapper.vm.assetsByType.property).toBe(500000);
  });

  it('displays chart title', () => {
    const wrapper = mount(NetWorthWaterfallChart, {
      props: {
        assets: mockAssets,
        liabilities: mockLiabilities,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/net.*worth.*waterfall|waterfall.*chart/i);
  });
});
