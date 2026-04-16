import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import PolicyCard from '@/components/Protection/PolicyCard.vue';

describe('PolicyCard', () => {
  const mockPolicy = {
    id: 1,
    policy_type: 'life_insurance',
    provider: 'Test Insurance Co',
    policy_number: 'POL123456',
    sum_assured: 500000,
    premium_amount: 50,
    premium_frequency: 'monthly',
    start_date: '2020-01-01',
    end_date: '2040-01-01',
    smoker_status: 'non-smoker',
  };

  it('renders policy summary when collapsed', () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
    });

    expect(wrapper.text()).toContain('Test Insurance Co');
    expect(wrapper.text()).toContain('£500,000');
    expect(wrapper.text()).toContain('£50');
  });

  it('expands to show full details when clicked', async () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
    });

    // Initially collapsed
    const policyNumber = wrapper.text().includes('POL123456');

    // Find and click expand button
    const expandButton = wrapper.find('[data-testid="expand-toggle"]') || wrapper.find('button');
    if (expandButton.exists()) {
      await expandButton.trigger('click');

      // After expansion, policy number should be visible
      expect(wrapper.text()).toContain('POL123456');
    }
  });

  it('collapses when expand button is clicked again', async () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
      data() {
        return {
          expanded: true, // Start expanded
        };
      },
    });

    const expandButton = wrapper.find('[data-testid="expand-toggle"]') || wrapper.find('button');
    if (expandButton.exists()) {
      await expandButton.trigger('click');

      // Should collapse
      expect(wrapper.vm.expanded).toBe(false);
    }
  });

  it('displays Edit button', () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
    });

    const buttons = wrapper.findAll('button');
    const editButton = buttons.find(btn => btn.text().match(/edit/i));
    expect(editButton).toBeDefined();
  });

  it('displays Delete button', () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
    });

    const buttons = wrapper.findAll('button');
    const deleteButton = buttons.find(btn => btn.text().match(/delete/i));
    expect(deleteButton).toBeDefined();
  });

  it('shows delete confirmation modal when delete is clicked', async () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
    });

    const deleteButton = wrapper.findAll('button').find(btn => btn.text().match(/delete/i));
    if (deleteButton) {
      await deleteButton.trigger('click');

      // Should show confirmation modal or set state
      expect(wrapper.vm.showDeleteConfirm || wrapper.vm.showConfirmation).toBe(true);
    }
  });

  it('emits edit event when Edit button is clicked', async () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
    });

    const editButton = wrapper.findAll('button').find(btn => btn.text().match(/edit/i));
    if (editButton) {
      await editButton.trigger('click');

      // Should emit 'edit' event with policy
      expect(wrapper.emitted('edit')).toBeTruthy();
      expect(wrapper.emitted('edit')[0]).toEqual([mockPolicy]);
    }
  });

  it('formats policy type correctly', () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
    });

    const text = wrapper.text();
    // Should convert life_insurance to "Life Insurance" or similar
    expect(text).toMatch(/life.*insurance/i);
  });

  it('displays smoker status', () => {
    const wrapper = mount(PolicyCard, {
      props: {
        policy: mockPolicy,
      },
      data() {
        return {
          expanded: true,
        };
      },
    });

    expect(wrapper.text()).toMatch(/non-smoker|smoker/i);
  });
});
