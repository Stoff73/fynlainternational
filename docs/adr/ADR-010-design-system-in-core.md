# ADR-010: Design System in Core

**Status:** Accepted
**Date:** 2026-04-15

## Context

Fynla has a comprehensive design system (fynlaDesignGuide.md v1.2.0) with defined colour tokens (Raspberry, Horizon, Spring, Violet, Savannah, Eggshell), typography (Segoe UI primary, Inter fallback), component patterns (buttons, cards, forms, modals), and data visualisation standards.

Brand consistency across jurisdictions is essential. A user with UK and SA accounts should experience one coherent application, not two visually distinct products stitched together. Allowing packs to define their own colours, fonts, or spacing would fragment the user experience and multiply design maintenance.

## Decision

The design system stays in core. Packs consume tokens from `@fynla/core-ui`; they do not define colours, fonts, or spacing.

**Structure:**

```
frontend/packages/core-ui/
  tokens/
    colours.js       # Raspberry, Horizon, Spring, Violet, Savannah, Eggshell
    typography.js     # Font families, weights, sizes
    spacing.js        # Spacing scale
  components/
    BaseButton.vue    # Shared button component
    BaseCard.vue      # Shared card component
    BaseModal.vue     # Shared modal component
    FormField.vue     # Shared form field wrapper
  designSystem.js     # Exported constants for charts and data visualisation
```

**Rules:**

1. Packs import visual tokens and shared components from `@fynla/core-ui` only.
2. An architecture test forbids packs from declaring their own colour or font tokens (scans for `#hex`, `rgb()`, `hsl()`, `font-family:` declarations in pack `<style>` blocks).
3. Packs may create country-specific components (e.g., a UK National Insurance calculator widget, an SA tax bracket visualiser) but must use core tokens for all styling.
4. Tailwind configuration is centralised in the root `tailwind.config.js`. Packs do not extend the colour palette.
5. Chart colours are imported from `designSystem.js`, not hardcoded in pack components.

## Consequences

- **Positive:** Visual consistency across all jurisdictions. One brand, one experience.
- **Positive:** Single source of truth for design decisions. Updates propagate to all countries automatically.
- **Positive:** Packs focus on domain logic, not visual identity. Developers building a new country pack do not need to make design decisions.
- **Negative:** Packs cannot customise the visual identity for local market preferences (e.g., a colour that resonates better in SA). This is intentional -- brand consistency is prioritised over local customisation.
- **Negative:** Core design system becomes a dependency for all packs. Breaking changes to core-ui affect every jurisdiction and must be managed carefully.
- **Negative:** Country-specific data visualisations (e.g., SA tax bracket thresholds displayed in a chart) must work within the existing colour palette, which may sometimes feel constraining.
