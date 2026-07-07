/**
 * ZA pack — navigation manifest.
 *
 * Declares the ZA modules and their routes. The sidebar NEVER shows a
 * country section, header, or link — its shape is fixed and content
 * adapts (see `sidebar_architecture_pattern`): SideMenu.vue routes the
 * existing module items to these /za/* dashboards when the user is
 * ZA-only, and shows SA-only modules (Exchange Control) as ordinary flat
 * items when ZA is active. Jurisdictions activate automatically — geo at
 * registration or cross-border asset entry — never via a nav link.
 *
 * This manifest currently feeds `jurisdiction/sidebarModules` (module
 * keys) and documents the pack's route surface for WS R-12.
 *
 * Module entry shape: `{ key, label, route, icon, section }`
 *   - key: stable identifier, prefix `za-` to avoid UK name collision
 *   - label: user-facing label (British spelling; TFSA abbreviation allowed)
 *   - route: absolute SPA path under `/za/*`
 *   - icon: name from `resources/js/components/SideMenuIcon.vue` allow-list
 *   - section: existing SideMenu section the module belongs to
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
                section: 'cashManagement',
            },
            {
                key: 'za-investment',
                label: 'Investments',
                route: '/za/investments',
                icon: 'trending-up',
                section: 'finances',
            },
            {
                key: 'za-exchange-control',
                label: 'Exchange Control',
                route: '/za/exchange-control',
                icon: 'map',
                section: 'finances',
            },
            {
                key: 'za-retirement',
                label: 'Retirement',
                route: '/za/retirement',
                icon: 'briefcase',
                section: 'finances',
            },
            {
                key: 'za-protection',
                label: 'Protection',
                route: '/za/protection',
                icon: 'shield',
                section: 'family',
            },
            // WS 1.6b will add za-estate here
        ],
    };
}
