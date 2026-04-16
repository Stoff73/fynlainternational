import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PensionCard from '@/components/Retirement/PensionCard.vue';

describe('PensionCard', () => {
  const dcPension = {
    id: 1,
    scheme_name: 'Workplace Pension',
    scheme_type: 'workplace',
    provider: 'Aviva',
    current_fund_value: 50000,
    employee_contribution_percent: 5,
    employer_contribution_percent: 3,
  };

  const dbPension = {
    id: 2,
    scheme_name: 'NHS Pension',
    scheme_type: 'public_sector',
    accrued_annual_pension: 15000,
    pensionable_service_years: 20,
    normal_retirement_age: 67,
  };

  it('renders DC pension card', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('renders DB pension card', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dbPension,
        type: 'db',
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays scheme name', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    expect(wrapper.text()).toContain('Workplace Pension');
  });

  it('displays provider for DC pension', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    expect(wrapper.text()).toContain('Aviva');
  });

  it('displays current fund value for DC pension', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/50,?000|£50,?000/);
  });

  it('displays accrued annual pension for DB pension', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dbPension,
        type: 'db',
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/15,?000|£15,?000/);
  });

  it('expands on click', async () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    // Initially should be collapsed
    expect(wrapper.vm.isExpanded).toBe(false);

    // Click to expand
    await wrapper.find('.pension-card').trigger('click');

    // Should be expanded
    expect(wrapper.vm.isExpanded).toBe(true);
  });

  it('collapses on second click', async () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    // Expand
    await wrapper.find('.pension-card').trigger('click');
    expect(wrapper.vm.isExpanded).toBe(true);

    // Collapse
    await wrapper.find('.pension-card').trigger('click');
    expect(wrapper.vm.isExpanded).toBe(false);
  });

  it('shows edit button for DC/DB pensions', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    // Expand to see buttons
    wrapper.vm.isExpanded = true;
    wrapper.vm.$nextTick();

    const html = wrapper.html();
    expect(html).toMatch(/edit/i);
  });

  it('shows delete button for DC/DB pensions', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    // Expand to see buttons
    wrapper.vm.isExpanded = true;
    wrapper.vm.$nextTick();

    const html = wrapper.html();
    expect(html).toMatch(/delete/i);
  });

  it('displays DC badge for DC pensions', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dcPension,
        type: 'dc',
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/DC|defined contribution/i);
  });

  it('displays DB badge for DB pensions', () => {
    const wrapper = mount(PensionCard, {
      props: {
        pension: dbPension,
        type: 'db',
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/DB|defined benefit/i);
  });

  it('shows projected value at retirement for DC', () => {
    const dcWithProjection = {
      ...dcPension,
      projected_value_at_retirement: 150000,
    };

    const wrapper = mount(PensionCard, {
      props: {
        pension: dcWithProjection,
        type: 'dc',
      },
    });

    // Expand to see details
    wrapper.vm.isExpanded = true;
    wrapper.vm.$nextTick();

    const html = wrapper.html();
    // Just check if the value is present when expanded
    // The component may or may not show the label "projected"
    expect(html).toMatch(/150,?000/);
  });
});
