import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AccountCard from '@/components/Investment/AccountCard.vue';

describe('AccountCard', () => {
  const mockISAAccount = {
    id: 1,
    account_type: 'isa',
    provider: 'Vanguard',
    platform: 'Investment Account',
    platform_fee_percent: 0.15,
    current_value: 25000,
    holdings: [{ id: 1 }, { id: 2 }, { id: 3 }],
    isa_type: 'stocks_and_shares',
    isa_subscription_current_year: 5000,
  };

  const mockGIAAccount = {
    id: 2,
    account_type: 'gia',
    provider: 'Hargreaves Lansdown',
    platform: 'Fund & Share Account',
    platform_fee_percent: 0.45,
    current_value: 50000,
    holdings: [{ id: 4 }, { id: 5 }],
  };

  it('renders with ISA account props', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('Vanguard');
    expect(wrapper.text()).toContain('£25,000');
    expect(wrapper.text()).toContain('3');
  });

  it('renders with GIA account props', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockGIAAccount,
      },
    });

    expect(wrapper.exists()).toBe(true);
    expect(wrapper.text()).toContain('Hargreaves Lansdown');
    expect(wrapper.text()).toContain('£50,000');
    expect(wrapper.text()).toContain('2');
  });

  it('displays ISA badge with green color', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    const html = wrapper.html();
    expect(html).toContain('ISA');
    expect(html).toMatch(/bg-green-100.*text-green-800/);
  });

  it('displays GIA badge with blue color', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockGIAAccount,
      },
    });

    const html = wrapper.html();
    expect(html).toContain('General Investment Account');
    expect(html).toMatch(/bg-blue-100.*text-blue-800/);
  });

  it('displays SIPP badge with purple color', () => {
    const sippAccount = { ...mockGIAAccount, account_type: 'sipp' };
    const wrapper = mount(AccountCard, {
      props: {
        account: sippAccount,
      },
    });

    const html = wrapper.html();
    expect(html).toContain('SIPP');
    expect(html).toMatch(/bg-purple-100.*text-purple-800/);
  });

  it('displays platform fee when present', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    expect(wrapper.text()).toContain('0.15');
    expect(wrapper.text()).toContain('Platform Fee');
  });

  it('hides platform fee section when not present', () => {
    const accountWithoutFee = { ...mockISAAccount, platform_fee_percent: null };
    const wrapper = mount(AccountCard, {
      props: {
        account: accountWithoutFee,
      },
    });

    expect(wrapper.text()).not.toContain('Platform Fee');
  });

  it('displays ISA information box for ISA accounts', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    const html = wrapper.html();
    expect(html).toContain('ISA Account');
    expect(wrapper.text()).toContain('Tax-free wrapper');
    expect(wrapper.text()).toContain('£20,000');
  });

  it('hides ISA information box for non-ISA accounts', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockGIAAccount,
      },
    });

    expect(wrapper.text()).not.toContain('Tax-free wrapper');
  });

  it('displays holdings count correctly', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    expect(wrapper.text()).toContain('Holdings:');
    expect(wrapper.text()).toContain('3');
  });

  it('displays zero holdings count when no holdings', () => {
    const accountWithoutHoldings = { ...mockISAAccount, holdings: [] };
    const wrapper = mount(AccountCard, {
      props: {
        account: accountWithoutHoldings,
      },
    });

    expect(wrapper.text()).toContain('0');
  });

  it('formats currency values correctly', () => {
    const accountWithLargeValue = { ...mockISAAccount, current_value: 123456 };
    const wrapper = mount(AccountCard, {
      props: {
        account: accountWithLargeValue,
      },
    });

    const text = wrapper.text();
    expect(text).toMatch(/£123,456|£123456/);
  });

  it('emits edit event when edit button is clicked', async () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    const editButton = wrapper.findAll('button')[0];
    await editButton.trigger('click');

    expect(wrapper.emitted('edit')).toBeTruthy();
    expect(wrapper.emitted('edit')[0]).toEqual([mockISAAccount]);
  });

  it('emits delete event when delete button is clicked', async () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    const deleteButton = wrapper.findAll('button')[1];
    await deleteButton.trigger('click');

    expect(wrapper.emitted('delete')).toBeTruthy();
    expect(wrapper.emitted('delete')[0]).toEqual([mockISAAccount]);
  });

  it('emits view-holdings event when View Holdings button is clicked', async () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    const viewHoldingsButton = wrapper.find('button.bg-blue-50');
    await viewHoldingsButton.trigger('click');

    expect(wrapper.emitted('view-holdings')).toBeTruthy();
    expect(wrapper.emitted('view-holdings')[0]).toEqual([mockISAAccount]);
  });

  it('displays platform name when provided', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    expect(wrapper.text()).toContain('Investment Account');
  });

  it('displays fallback text when platform not provided', () => {
    const accountWithoutPlatform = { ...mockISAAccount, platform: null };
    const wrapper = mount(AccountCard, {
      props: {
        account: accountWithoutPlatform,
      },
    });

    expect(wrapper.text()).toContain('Platform not specified');
  });

  it('handles accounts with other account type', () => {
    const otherAccount = {
      ...mockGIAAccount,
      account_type: 'other',
    };
    const wrapper = mount(AccountCard, {
      props: {
        account: otherAccount,
      },
    });

    expect(wrapper.exists()).toBe(true);
    const html = wrapper.html();
    expect(html).toMatch(/bg-gray-100.*text-gray-800/);
  });

  it('handles zero account value', () => {
    const zeroValueAccount = { ...mockISAAccount, current_value: 0 };
    const wrapper = mount(AccountCard, {
      props: {
        account: zeroValueAccount,
      },
    });

    expect(wrapper.text()).toContain('£0');
  });

  it('applies hover effect class', () => {
    const wrapper = mount(AccountCard, {
      props: {
        account: mockISAAccount,
      },
    });

    const html = wrapper.html();
    expect(html).toContain('hover:shadow-md');
    expect(html).toContain('transition-shadow');
  });
});
