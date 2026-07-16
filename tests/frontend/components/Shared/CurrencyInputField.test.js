// @vitest-environment jsdom
import { describe, it, expect, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import CurrencyInputField from '@/components/Shared/CurrencyInputField.vue';
import { setLocalisation, resetLocalisation } from '@/utils/localisation';

afterEach(() => resetLocalisation());

describe('CurrencyInputField', () => {
  it('shows £ when no session localisation is set', () => {
    const wrapper = mount(CurrencyInputField, { props: { modelValue: 100 } });
    expect(wrapper.find('span').text()).toBe('£');
  });

  it('shows the session currency symbol', () => {
    setLocalisation({ currency_code: 'ZAR', currency_symbol: 'R', locale: 'en_ZA', date_format: 'd M Y' });
    const wrapper = mount(CurrencyInputField, { props: { modelValue: 100 } });
    expect(wrapper.find('span').text()).toBe('R');
  });

  it('still emits numeric update:modelValue on input', async () => {
    const wrapper = mount(CurrencyInputField, { props: { modelValue: 0 } });
    await wrapper.find('input').setValue('250');
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([250]);
  });
});
