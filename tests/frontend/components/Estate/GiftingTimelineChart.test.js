import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import GiftingTimelineChart from '@/components/Estate/GiftingTimelineChart.vue';

describe('GiftingTimelineChart', () => {
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

  const mockGifts = [
    {
      id: 1,
      gift_date: '2020-01-15',
      recipient: 'John',
      gift_value: 50000,
      gift_type: 'pet',
    },
    {
      id: 2,
      gift_date: '2022-06-10',
      recipient: 'Jane',
      gift_value: 30000,
      gift_type: 'pet',
    },
    {
      id: 3,
      gift_date: '2024-03-20',
      recipient: 'Bob',
      gift_value: 20000,
      gift_type: 'pet',
    },
  ];

  it('renders with gifts prop', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays empty state when no gifts provided', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: [],
      },
    });

    expect(wrapper.vm.hasGifts).toBe(false);
    const html = wrapper.html();
    expect(html).toMatch(/no.*gift|empty/i);
  });

  it('calculates years elapsed for each gift', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const timelineData = wrapper.vm.timelineData;
    expect(timelineData.length).toBe(3);
    timelineData.forEach((gift) => {
      expect(gift.yearsElapsed).toBeGreaterThanOrEqual(0);
    });
  });

  it('calculates years remaining until 7-year survival', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const timelineData = wrapper.vm.timelineData;
    timelineData.forEach((gift) => {
      expect(gift.yearsRemaining).toBeGreaterThanOrEqual(0);
      expect(gift.yearsRemaining).toBeLessThanOrEqual(7);
    });
  });

  it('identifies gifts that survived 7 years', () => {
    const oldGift = {
      id: 99,
      gift_date: '2010-01-01', // Over 7 years ago
      recipient: 'Alice',
      gift_value: 100000,
      gift_type: 'pet',
    };

    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: [oldGift],
      },
    });

    const timelineData = wrapper.vm.timelineData;
    expect(timelineData[0].survived).toBe(true);
  });

  it('calculates taper relief for gifts 3-7 years old', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    // Test taper relief calculation method
    expect(wrapper.vm.calculateTaperRelief(2.5)).toBe(0); // <3 years
    expect(wrapper.vm.calculateTaperRelief(3.5)).toBe(20); // 3-4 years
    expect(wrapper.vm.calculateTaperRelief(4.5)).toBe(40); // 4-5 years
    expect(wrapper.vm.calculateTaperRelief(5.5)).toBe(60); // 5-6 years
    expect(wrapper.vm.calculateTaperRelief(6.5)).toBe(80); // 6-7 years
    expect(wrapper.vm.calculateTaperRelief(7.5)).toBe(100); // >7 years
  });

  it('assigns correct color for gifts within 3 years', () => {
    const recentGift = {
      id: 100,
      gift_date: new Date(new Date().setFullYear(new Date().getFullYear() - 2)).toISOString().split('T')[0],
      recipient: 'Test',
      gift_value: 50000,
      gift_type: 'pet',
    };

    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: [recentGift],
      },
    });

    const timelineData = wrapper.vm.timelineData;
    expect(timelineData[0].color).toMatch(/#ef4444/i); // Red
  });

  it('assigns correct color for gifts 3-7 years old', () => {
    const midGift = {
      id: 101,
      gift_date: new Date(new Date().setFullYear(new Date().getFullYear() - 5)).toISOString().split('T')[0],
      recipient: 'Test',
      gift_value: 50000,
      gift_type: 'pet',
    };

    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: [midGift],
      },
    });

    const timelineData = wrapper.vm.timelineData;
    expect(timelineData[0].color).toMatch(/#f59e0b/i); // Amber
  });

  it('assigns correct color for gifts survived 7+ years', () => {
    const survivedGift = {
      id: 102,
      gift_date: new Date(new Date().setFullYear(new Date().getFullYear() - 8)).toISOString().split('T')[0],
      recipient: 'Test',
      gift_value: 50000,
      gift_type: 'pet',
    };

    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: [survivedGift],
      },
    });

    const timelineData = wrapper.vm.timelineData;
    expect(timelineData[0].color).toMatch(/#10b981/i); // Green
    expect(timelineData[0].survived).toBe(true);
  });

  it('sorts gifts by date (oldest first)', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const timelineData = wrapper.vm.timelineData;
    for (let i = 0; i < timelineData.length - 1; i++) {
      const date1 = new Date(timelineData[i].gift_date);
      const date2 = new Date(timelineData[i + 1].gift_date);
      expect(date1.getTime()).toBeLessThanOrEqual(date2.getTime());
    }
  });

  it('displays taper relief reference table', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/taper.*relief/i);
    expect(html).toMatch(/20%|40%|60%|80%/); // Taper relief percentages
  });

  it('formats currency values in tooltips', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const formatted = wrapper.vm.formatCurrency(50000);
    expect(formatted).toMatch(/£50,000|50000/);
  });

  it('handles exactly 3 years (taper relief starts)', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const relief = wrapper.vm.calculateTaperRelief(3.0);
    expect(relief).toBe(20); // First taper relief bracket
  });

  it('handles exactly 7 years (fully exempt)', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const relief = wrapper.vm.calculateTaperRelief(7.0);
    expect(relief).toBe(100); // Fully exempt
  });

  it('displays chart title', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/7.*year|gifting.*timeline/i);
  });

  it('creates ApexCharts configuration with correct type', () => {
    const wrapper = mount(GiftingTimelineChart, {
      props: {
        gifts: mockGifts,
      },
    });

    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.chart.type).toBe('rangeBar');
  });
});
