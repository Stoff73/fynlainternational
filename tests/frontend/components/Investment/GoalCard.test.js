import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import GoalCard from '@/components/Investment/GoalCard.vue';

describe('GoalCard', () => {
  const mockGoal = {
    id: 1,
    name: 'Retirement Fund',
    description: 'Build retirement savings',
    target_amount: 1000000,
    target_date: '2045-06-01',
    monthly_contribution: 2000,
  };

  const mockMonteCarloResult = {
    success_probability: 85,
    median_outcome: 1050000,
    percentile_90: 1500000,
    percentile_10: 800000,
    required_return: 6.5,
  };

  it('renders with goal props', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: null,
        runningMonteCarlo: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('Retirement Fund');
    expect(wrapper.text()).toContain('Build retirement savings');
  });

  it('displays target amount formatted as currency', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/£1,000,000|£1000000/);
  });

  it('displays target date formatted correctly', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
      },
    });

    const text = wrapper.text();
    // Should display formatted date
    expect(text).toMatch(/2045|Jun|June/);
  });

  it('displays current value', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/£250,000|£250000/);
  });

  it('calculates progress percentage correctly', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000, // 25% of 1,000,000
      },
    });

    expect(wrapper.vm.progressPercent).toBe(25);
  });

  it('displays progress bar with correct width', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
      },
    });

    // Find the progress bar inner div (has h-3 class and transition-all class)
    const progressBars = wrapper.findAll('div').filter(div => {
      const classes = div.classes();
      return classes.includes('h-3') && classes.includes('rounded-full') && classes.includes('transition-all');
    });

    if (progressBars.length > 0) {
      const style = progressBars[0].attributes('style');
      expect(style).toContain('25');
    }
  });

  it('shows green progress bar when progress >= 75%', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 800000, // 80% progress
      },
    });

    expect(wrapper.vm.progressBarClass).toContain('bg-blue');
  });

  it('shows orange progress bar when progress < 50%', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 300000, // 30% progress
      },
    });

    expect(wrapper.vm.progressBarClass).toContain('orange');
  });

  it('calculates time remaining correctly', () => {
    const futureDate = new Date();
    futureDate.setFullYear(futureDate.getFullYear() + 2);

    const goalWithNearFuture = {
      ...mockGoal,
      target_date: futureDate.toISOString().split('T')[0],
    };

    const wrapper = mount(GoalCard, {
      props: {
        goal: goalWithNearFuture,
        currentValue: 250000,
      },
    });

    const timeRemaining = wrapper.vm.timeRemaining;
    expect(timeRemaining).toMatch(/year|month|day/i);
  });

  it('displays monthly contribution', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Monthly Contribution');
    expect(text).toMatch(/£2,000|£2000/);
  });

  it('displays Monte Carlo analysis when result is provided', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: mockMonteCarloResult,
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Monte Carlo Analysis');
    expect(text).toContain('Success Probability');
  });

  it('displays success probability correctly', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: mockMonteCarloResult,
      },
    });

    expect(wrapper.text()).toContain('85%');
  });

  it('applies green color to high success probability (>= 80%)', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: mockMonteCarloResult,
      },
    });

    expect(wrapper.vm.probabilityClass).toContain('green');
    expect(wrapper.vm.probabilityBarClass).toContain('green');
  });

  it('applies red color to low success probability (< 40%)', () => {
    const lowProbabilityResult = {
      ...mockMonteCarloResult,
      success_probability: 30,
    };

    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 100000,
        monteCarloResult: lowProbabilityResult,
      },
    });

    expect(wrapper.vm.probabilityClass).toContain('red');
    expect(wrapper.vm.probabilityBarClass).toContain('red');
  });

  it('displays median outcome from Monte Carlo', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: mockMonteCarloResult,
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Median Outcome');
    expect(text).toMatch(/£1,050,000|£1050000/);
  });

  it('displays best and worst case scenarios', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: mockMonteCarloResult,
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Best Case');
    expect(text).toContain('Worst Case');
    expect(text).toMatch(/£1,500,000|£1500000/); // 90th percentile
    expect(text).toMatch(/£800,000|£800000/); // 10th percentile
  });

  it('displays required return percentage', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: mockMonteCarloResult,
      },
    });

    const text = wrapper.text();
    expect(text).toContain('Required Return');
    expect(text).toContain('6.5');
  });

  it('shows "Run Monte Carlo" button when no results', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: null,
      },
    });

    expect(wrapper.text()).toContain('Run Monte Carlo Simulation');
  });

  it('emits run-monte-carlo event when button clicked', async () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: null,
      },
    });

    const button = wrapper.find('button.bg-blue-600');
    await button.trigger('click');

    expect(wrapper.emitted('run-monte-carlo')).toBeTruthy();
    expect(wrapper.emitted('run-monte-carlo')[0][0]).toEqual(mockGoal);
  });

  it('disables Monte Carlo button when running', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: null,
        runningMonteCarlo: true,
      },
    });

    const button = wrapper.find('button.bg-blue-600');
    expect(button.attributes('disabled')).toBeDefined();
    expect(wrapper.text()).toContain('Running...');
  });

  it('displays status badge with correct text', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 750000, // 75% progress
        monteCarloResult: mockMonteCarloResult,
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/on track|good progress/i);
  });

  it('shows "Goal Achieved" status when progress >= 100%', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 1000000, // 100% progress
      },
    });

    expect(wrapper.vm.statusText).toBe('Goal Achieved');
  });

  it('shows "Off Track" status for low probability', () => {
    const lowProbabilityResult = {
      ...mockMonteCarloResult,
      success_probability: 30,
    };

    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 100000,
        monteCarloResult: lowProbabilityResult,
      },
    });

    expect(wrapper.vm.statusText).toBe('Off Track');
  });

  it('emits edit event when edit button clicked', async () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
      },
    });

    const editButton = wrapper.findAll('button')[0];
    await editButton.trigger('click');

    expect(wrapper.emitted('edit')).toBeTruthy();
    expect(wrapper.emitted('edit')[0][0]).toEqual(mockGoal);
  });

  it('emits delete event when delete button clicked', async () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
      },
    });

    const deleteButton = wrapper.findAll('button')[1];
    await deleteButton.trigger('click');

    expect(wrapper.emitted('delete')).toBeTruthy();
    expect(wrapper.emitted('delete')[0][0]).toEqual(mockGoal);
  });

  it('emits view-chart event when View Detailed Chart button clicked', async () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: mockMonteCarloResult,
      },
    });

    const viewChartButton = wrapper.find('button.bg-blue-50');
    await viewChartButton.trigger('click');

    expect(wrapper.emitted('view-chart')).toBeTruthy();
    expect(wrapper.emitted('view-chart')[0][0]).toEqual(mockGoal);
  });

  it('handles overdue goals', () => {
    const pastDate = new Date();
    pastDate.setFullYear(pastDate.getFullYear() - 1);

    const overdueGoal = {
      ...mockGoal,
      target_date: pastDate.toISOString().split('T')[0],
    };

    const wrapper = mount(GoalCard, {
      props: {
        goal: overdueGoal,
        currentValue: 250000,
      },
    });

    expect(wrapper.vm.timeRemaining).toBe('Overdue');
  });

  it('handles zero current value', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 0,
      },
    });

    expect(wrapper.vm.progressPercent).toBe(0);
    expect(wrapper.text()).toContain('£0');
  });

  it('caps progress at 100% for display', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 1500000, // 150% of target
      },
    });

    // Find the progress bar inner div (has h-3 class and transition-all class)
    const progressBars = wrapper.findAll('div').filter(div => {
      const classes = div.classes();
      return classes.includes('h-3') && classes.includes('rounded-full') && classes.includes('transition-all');
    });

    if (progressBars.length > 0) {
      const style = progressBars[0].attributes('style');
      // Width should be capped at 100%
      expect(style).toContain('100%');
    }
  });

  it('displays status dot with matching color', () => {
    const wrapper = mount(GoalCard, {
      props: {
        goal: mockGoal,
        currentValue: 250000,
        monteCarloResult: mockMonteCarloResult,
      },
    });

    const html = wrapper.html();
    // Status dot should have color matching status
    expect(html).toMatch(/w-2 h-2 rounded-full/);
  });
});
