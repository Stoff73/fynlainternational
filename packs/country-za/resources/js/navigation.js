/**
 * ZA pack — sidebar navigation manifest.
 *
 * Returns the South Africa modules contributed to the sidebar. Unlike the
 * GB pack (whose UK sidebar is currently hardcoded in `SideMenu.vue`), the
 * ZA section is data-driven (per WS 1.2b) — `SideMenu.vue` renders one
 * `<SideMenuItem v-for="mod in zaModules">` for each entry returned here.
 *
 * Module entry shape: `{ key, label, route, icon, section }`
 *   - key: stable identifier, prefix `za-` to avoid UK name collision
 *   - label: user-facing label (British spelling; TFSA abbreviation allowed)
 *   - route: absolute SPA path under `/za/*`
 *   - icon: name from `resources/js/components/SideMenuIcon.vue` allow-list
 *   - section: section key (must exist in `SideMenu.vue` expandedSections)
 *
 * Later SA workstreams (WS 1.3c / 1.4d / 1.5b / 1.6b) append entries to
 * the `modules` array and the sidebar item appears with no further edits.
 */
export default function navigation() {
    return {
        code: 'za',
        modules: [
            {
                key: 'za-savings',
                label: 'Savings (TFSA)',
                route: '/za/savings',
                icon: 'banknotes',
                section: 'zaSection',
            },
            {
                key: 'za-investment',
                label: 'Investments',
                route: '/za/investments',
                icon: 'trending-up',
                section: 'zaSection',
            },
            {
                key: 'za-exchange-control',
                label: 'Exchange Control',
                route: '/za/exchange-control',
                icon: 'map',
                section: 'zaSection',
            },
            {
                key: 'za-retirement',
                label: 'Retirement',
                route: '/za/retirement',
                icon: 'briefcase',
                section: 'zaSection',
            },
            {
                key: 'za-protection',
                label: 'Protection',
                route: '/za/protection',
                icon: 'shield',
                section: 'zaSection',
            },
            // WS 1.6b will add za-estate here
        ],
    };
}
