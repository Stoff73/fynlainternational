import { describe, it, expect, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import DrawdownSimulator from '@/components/Retirement/DrawdownSimulator.vue';

describe('DrawdownSimulator', () => {
  beforeEach(() => {
    // Mock ApexCharts if not already mocked globally
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

  const defaultProps = {
    initialPot: 500000,
    retirementAge: 67,
    lifeExpectancy: 90,
  };

  it('renders with required props', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays initial pot value', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    const html = wrapper.html();
    expect(html).toMatch(/500,?000|£500,?000/);
  });

  it('has withdrawal rate slider', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    const slider = wrapper.find('input[type="range"]');
    expect(slider.exists()).toBe(true);
  });

  it('defaults to 4% withdrawal rate (4% rule)', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    expect(wrapper.vm.simulatorData.withdrawalRate).toBe(4);
  });

  it('updates withdrawal rate when slider changes', async () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    const slider = wrapper.find('input[type="range"]');
    await slider.setValue(5);

    expect(wrapper.vm.simulatorData.withdrawalRate).toBe(5);
  });

  it('has growth rate slider', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    const sliders = wrapper.findAll('input[type="range"]');
    expect(sliders.length).toBeGreaterThanOrEqual(2);
  });

  it('has inflation rate slider', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    const sliders = wrapper.findAll('input[type="range"]');
    expect(sliders.length).toBeGreaterThanOrEqual(3);
  });

  it('runs simulation on mount', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    // Should have simulation results
    expect(wrapper.vm.simulationResults).toBeDefined();
  });

  it('calculates portfolio depletion correctly', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: {
        ...defaultProps,
        initialPot: 100000,
      },
    });

    // Set high withdrawal rate
    wrapper.vm.simulatorData.withdrawalRate = 10; // 10% withdrawal
    wrapper.vm.simulatorData.growthRate = 2; // Low growth
    wrapper.vm.runSimulation();

    // Portfolio should deplete
    expect(wrapper.vm.simulationResults.depletes).toBe(true);
    expect(wrapper.vm.simulationResults.depletionAge).toBeGreaterThan(0);
  });

  it('shows portfolio survives with sustainable rate', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: {
        ...defaultProps,
        initialPot: 500000,
      },
    });

    // Set sustainable withdrawal rate
    wrapper.vm.simulatorData.withdrawalRate = 3; // 3% withdrawal
    wrapper.vm.simulatorData.growthRate = 5; // Reasonable growth
    wrapper.vm.runSimulation();

    // Portfolio should survive
    expect(wrapper.vm.simulationResults.depletes).toBe(false);
  });

  it('displays portfolio survives message', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    // Default 4% rule with 5% growth should survive
    wrapper.vm.simulatorData.withdrawalRate = 4;
    wrapper.vm.simulatorData.growthRate = 5;
    wrapper.vm.runSimulation();

    const html = wrapper.html();
    expect(html).toMatch(/survives|sustainable|sufficient/i);
  });

  it('displays depletion age when portfolio depletes', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: {
        ...defaultProps,
        initialPot: 100000,
      },
    });

    // High withdrawal, low growth
    wrapper.vm.simulatorData.withdrawalRate = 8;
    wrapper.vm.simulatorData.growthRate = 2;
    wrapper.vm.runSimulation();

    if (wrapper.vm.simulationResults.depletes) {
      const html = wrapper.html();
      expect(html).toMatch(/depleted|runs out|age \d+/i);
    }
  });

  it('applies green color for sustainable portfolio', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    wrapper.vm.simulatorData.withdrawalRate = 3;
    wrapper.vm.simulatorData.growthRate = 5;
    wrapper.vm.runSimulation();

    // Chart color should be green
    const chartOptions = wrapper.vm.chartOptions;
    expect(chartOptions.colors[0]).toMatch(/#10b981|#22c55e|green/i);
  });

  it('applies red color for depleting portfolio', () => {
    const wrapper = mount(DrawdownSimulator, {
      props: {
        ...defaultProps,
        initialPot: 100000,
      },
    });

    wrapper.vm.simulatorData.withdrawalRate = 10;
    wrapper.vm.simulatorData.growthRate = 2;
    wrapper.vm.runSimulation();

    if (wrapper.vm.simulationResults.depletes) {
      // Chart color should be red
      const chartOptions = wrapper.vm.chartOptions;
      expect(chartOptions.colors[0]).toMatch(/#ef4444|#dc2626|red/i);
    }
  });

  it('reruns simulation when withdrawal rate changes', async () => {
    const wrapper = mount(DrawdownSimulator, {
      props: defaultProps,
    });

    const initialResults = { ...wrapper.vm.simulationResults };

    // Change withdrawal rate
    wrapper.vm.simulatorData.withdrawalRate = 6;
    await wrapper.vm.$nextTick();

    // Results should have changed
    expect(wrapper.vm.simulationResults).not.toEqual(initialResults);
  });
});
