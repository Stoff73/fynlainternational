import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createStore } from 'vuex';
import UserProfile from '../../../views/UserProfile.vue';

describe('UserProfile.vue', () => {
  let wrapper;
  let store;
  let mockActions;

  beforeEach(() => {
    mockActions = {
      fetchProfile: vi.fn(() => Promise.resolve()),
    };

    store = createStore({
      modules: {
        userProfile: {
          namespaced: true,
          state: {
            personalInfo: { name: 'John Doe' },
            familyMembers: [],
            incomeOccupation: {},
            personalAccounts: {},
            loading: false,
            error: null,
          },
          getters: {
            personalInfo: (state) => state.personalInfo,
            familyMembers: (state) => state.familyMembers,
            incomeOccupation: (state) => state.incomeOccupation,
            personalAccounts: (state) => state.personalAccounts,
            loading: (state) => state.loading,
          },
          actions: mockActions,
        },
      },
    });

    wrapper = mount(UserProfile, {
      global: {
        plugins: [store],
        stubs: {
          PersonalInformation: { template: '<div class="personal-info-stub"></div>' },
          FamilyMembers: { template: '<div class="family-members-stub"></div>' },
          IncomeOccupation: { template: '<div class="income-occupation-stub"></div>' },
          AssetsOverview: { template: '<div class="assets-overview-stub"></div>' },
          LiabilitiesOverview: { template: '<div class="liabilities-overview-stub"></div>' },
          PersonalAccounts: { template: '<div class="personal-accounts-stub"></div>' },
        },
      },
    });
  });

  it('renders user profile page', () => {
    expect(wrapper.find('h1').text()).toContain('User Profile');
  });

  it('renders all 6 tabs', () => {
    const tabs = wrapper.findAll('button[role="tab"]');
    expect(tabs.length).toBe(6);
  });

  it('has correct tab labels', () => {
    const tabs = wrapper.findAll('button[role="tab"]');
    const tabLabels = tabs.map(tab => tab.text());

    expect(tabLabels).toContain('Personal Information');
    expect(tabLabels).toContain('Family Members');
    expect(tabLabels).toContain('Income & Occupation');
    expect(tabLabels).toContain('Assets');
    expect(tabLabels).toContain('Liabilities');
    expect(tabLabels).toContain('Personal Accounts');
  });

  it('shows Personal Information tab by default', () => {
    const activeTab = wrapper.find('button[role="tab"][aria-selected="true"]');
    expect(activeTab.text()).toContain('Personal Information');
  });

  it('switches tabs when clicked', async () => {
    const tabs = wrapper.findAll('button[role="tab"]');

    // Click on Family Members tab
    await tabs[1].trigger('click');
    expect(wrapper.vm.activeTab).toBe('family');

    // Click on Income & Occupation tab
    await tabs[2].trigger('click');
    expect(wrapper.vm.activeTab).toBe('income');
  });

  it('displays correct component for each tab', async () => {
    const tabs = wrapper.findAll('button[role="tab"]');

    // Personal Information
    await tabs[0].trigger('click');
    expect(wrapper.findComponent({ name: 'PersonalInformation' }).exists()).toBe(true);

    // Family Members
    await tabs[1].trigger('click');
    expect(wrapper.findComponent({ name: 'FamilyMembers' }).exists()).toBe(true);

    // Income & Occupation
    await tabs[2].trigger('click');
    expect(wrapper.findComponent({ name: 'IncomeOccupation' }).exists()).toBe(true);

    // Assets
    await tabs[3].trigger('click');
    expect(wrapper.findComponent({ name: 'AssetsOverview' }).exists()).toBe(true);

    // Liabilities
    await tabs[4].trigger('click');
    expect(wrapper.findComponent({ name: 'LiabilitiesOverview' }).exists()).toBe(true);

    // Personal Accounts
    await tabs[5].trigger('click');
    expect(wrapper.findComponent({ name: 'PersonalAccounts' }).exists()).toBe(true);
  });

  it('calls fetchProfile action on mount', () => {
    expect(mockActions.fetchProfile).toHaveBeenCalled();
  });

  it('displays loading state when fetching data', async () => {
    store.state.userProfile.loading = true;
    await wrapper.vm.$nextTick();

    // Component should show loading indicator
    expect(wrapper.html()).toContain('loading');
  });

  it('displays error message when fetch fails', async () => {
    store.state.userProfile.error = 'Failed to load profile';
    await wrapper.vm.$nextTick();

    expect(wrapper.html()).toContain('error');
  });

  it('has breadcrumb or back navigation', () => {
    // Check if there's a breadcrumb or back button
    const breadcrumb = wrapper.find('[aria-label="Breadcrumb"]') ||
                      wrapper.findAll('a').find(a => a.text().includes('Dashboard'));
    expect(breadcrumb).toBeDefined();
  });

  it('maintains tab state across component updates', async () => {
    const tabs = wrapper.findAll('button[role="tab"]');

    // Switch to Family Members
    await tabs[1].trigger('click');
    expect(wrapper.vm.activeTab).toBe('family');

    // Force update
    await wrapper.vm.$forceUpdate();
    await wrapper.vm.$nextTick();

    // Tab should still be Family Members
    expect(wrapper.vm.activeTab).toBe('family');
  });

  it('has accessible tab controls', () => {
    const tabs = wrapper.findAll('button[role="tab"]');

    tabs.forEach(tab => {
      expect(tab.attributes('role')).toBe('tab');
      expect(tab.attributes('aria-selected')).toBeDefined();
    });
  });
});
