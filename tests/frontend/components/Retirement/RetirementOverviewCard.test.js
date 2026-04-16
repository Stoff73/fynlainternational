import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import RetirementOverviewCard from '@/components/Retirement/RetirementOverviewCard.vue';

describe('RetirementOverviewCard', () => {
  const defaultProps = {
    totalPensionValue: 250000,
    projectedIncome: 25000,
    yearsToRetirement: 20,
  };

  it('renders with all props', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays total pension value', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/£250,000|250,000/);
    expect(html).toMatch(/Total Pension Value/i);
  });

  it('displays projected income formatted', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/£25,000|25,000/);
    expect(html).toMatch(/Projected Income/i);
  });

  it('displays years to retirement', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    const html = wrapper.html();
    expect(html).toContain('20');
    expect(html).toMatch(/years/i);
  });

  it('handles zero pension value', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: {
        totalPensionValue: 0,
        projectedIncome: 0,
        yearsToRetirement: 25,
      },
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    expect(wrapper.vm.totalPensionValue).toBe(0);
    const html = wrapper.html();
    expect(html).toMatch(/£0|0/);
  });

  it('handles large pension values', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: {
        totalPensionValue: 1500000,
        projectedIncome: 60000,
        yearsToRetirement: 10,
      },
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/1,500,000/);
    expect(html).toMatch(/60,000/);
  });

  it('displays retirement planning card title', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/Retirement Planning/i);
  });

  it('has primary metric section with pension value', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/bg-indigo-50/);
    expect(html).toMatch(/text-3xl/);
  });

  it('is clickable and has cursor-pointer class', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/cursor-pointer/);
  });

  it('shows View Full Analysis link', () => {
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: () => {},
          },
        },
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/View Full Analysis/i);
  });

  it('navigates to retirement page when clicked', async () => {
    const mockPush = vi.fn();
    const wrapper = mount(RetirementOverviewCard, {
      props: defaultProps,
      global: {
        mocks: {
          $router: {
            push: mockPush,
          },
        },
      },
    });

    await wrapper.trigger('click');
    expect(mockPush).toHaveBeenCalledWith('/retirement');
  });
});
