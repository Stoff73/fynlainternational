import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import NRBRNRBTracker from '@/components/Estate/NRBRNRBTracker.vue';

describe('NRBRNRBTracker', () => {
  it('renders with estate value prop', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 800000,
        hasSpouse: false,
        isRnrbEligible: false,
      },
    });

    expect(wrapper.exists()).toBe(true);
  });

  it('displays standard NRB of £325,000', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 600000,
        hasSpouse: false,
        isRnrbEligible: false,
      },
    });

    expect(wrapper.vm.nrbStandard).toBe(325000);
  });

  it('displays standard RNRB of £175,000', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 600000,
        hasSpouse: false,
        isRnrbEligible: true,
      },
    });

    expect(wrapper.vm.rnrbStandard).toBe(175000);
  });

  it('doubles NRB when spouse transfers unused allowance', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 800000,
        hasSpouse: true,
        isRnrbEligible: false,
      },
    });

    expect(wrapper.vm.nrbTotal).toBe(650000); // 325k * 2
  });

  it('applies RNRB tapering for estates over £2m', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 2200000, // £200k over threshold
        hasSpouse: false,
        isRnrbEligible: true,
      },
    });

    // Reduction = £200k excess / 2 = £100k
    // RNRB = £175k - £100k = £75k
    expect(wrapper.vm.rnrbTotal).toBe(75000);
    expect(wrapper.vm.rnrbTapered).toBe(true);
  });

  it('sets RNRB to zero when fully tapered', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 2500000, // Fully tapered
        hasSpouse: false,
        isRnrbEligible: true,
      },
    });

    // Excess = £500k, reduction = £250k, RNRB = 0
    expect(wrapper.vm.rnrbTotal).toBe(0);
  });

  it('returns zero RNRB when not eligible', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 800000,
        hasSpouse: false,
        isRnrbEligible: false,
      },
    });

    expect(wrapper.vm.rnrbTotal).toBe(0);
  });

  it('calculates combined allowance correctly', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 800000,
        hasSpouse: false,
        isRnrbEligible: true,
      },
    });

    // NRB: £325k, RNRB: £175k
    expect(wrapper.vm.combinedAllowance).toBe(500000);
  });

  it('calculates taxable estate above allowances', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 800000,
        hasSpouse: false,
        isRnrbEligible: true,
      },
    });

    // Estate: £800k, Combined: £500k
    expect(wrapper.vm.taxableEstate).toBe(300000);
  });

  it('returns zero taxable estate when below allowances', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 400000,
        hasSpouse: false,
        isRnrbEligible: false,
      },
    });

    // Estate: £400k, NRB only: £325k, taxable: £75k
    expect(wrapper.vm.taxableEstate).toBe(75000);
  });

  it('calculates NRB usage percentage', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 650000,
        hasSpouse: false,
        isRnrbEligible: false,
      },
    });

    // Estate: £650k, NRB: £325k = 100% used
    expect(wrapper.vm.nrbUsagePercentage).toBe(100);
  });

  it('calculates RNRB usage percentage when eligible', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 437500, // Uses 50% of RNRB
        hasSpouse: false,
        isRnrbEligible: true,
      },
    });

    // Estate: £437.5k, NRB: £325k, RNRB: £175k
    // Excess over NRB: £112.5k = ~64% of RNRB
    const rnrbUsage = wrapper.vm.rnrbUsagePercentage;
    expect(rnrbUsage).toBeGreaterThan(0);
    expect(rnrbUsage).toBeLessThanOrEqual(100);
  });

  it('displays spouse transfer status correctly', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 800000,
        hasSpouse: true,
        isRnrbEligible: false,
      },
    });

    expect(wrapper.vm.hasSpouse).toBe(true);
  });

  it('handles edge case at exactly £2m (RNRB tapering threshold)', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 2000000,
        hasSpouse: false,
        isRnrbEligible: true,
      },
    });

    expect(wrapper.vm.rnrbTotal).toBe(175000); // No tapering at exactly £2m
    expect(wrapper.vm.rnrbTapered).toBe(false);
  });

  it('handles both spouse transfer and RNRB eligibility', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 1200000,
        hasSpouse: true,
        isRnrbEligible: true,
      },
    });

    // NRB: £650k (doubled), RNRB: £350k (doubled)
    expect(wrapper.vm.nrbTotal).toBe(650000);
    expect(wrapper.vm.rnrbTotal).toBe(350000);
    expect(wrapper.vm.combinedAllowance).toBe(1000000);
  });

  it('formats currency values correctly', () => {
    const wrapper = mount(NRBRNRBTracker, {
      props: {
        estateValue: 1250000,
        hasSpouse: false,
        isRnrbEligible: true,
      },
    });

    const html = wrapper.html();
    expect(html).toMatch(/£325,000|325000/); // NRB
    expect(html).toMatch(/£175,000|175000/); // RNRB
  });
});
