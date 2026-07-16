/**
 * Session localisation singleton (WS3).
 *
 * Holds the authenticated user's localisation config from the
 * /api/auth/user `localisation` block, set once per session by
 * jurisdiction/hydrateFromSession and cleared by jurisdiction/reset.
 * currency.js, dateFormatter.js, and currencyMixin read it internally so
 * all app-wide formatting adapts without touching call sites — the same
 * pattern as dateFormatter's _activeTaxYearFromBackend.
 *
 * Null means "no session localisation" and every consumer falls back to
 * GB behaviour (fail-open, mirroring WS1).
 */

let _config = null;

/**
 * Store the session localisation block. Accepts the raw snake_case
 * payload from /api/auth/user; anything non-object clears the config.
 *
 * The POSIX locale from the backend contract (e.g. "en_ZA") is
 * normalised to BCP-47 ("en-ZA") HERE, once — Intl.NumberFormat and
 * toLocaleString throw RangeError on underscore locales.
 */
export function setLocalisation(config) {
  if (!config || typeof config !== 'object') {
    _config = null;
    return;
  }

  _config = {
    currencyCode: config.currency_code || null,
    currencySymbol: config.currency_symbol || null,
    locale: typeof config.locale === 'string' ? config.locale.replace(/_/g, '-') : null,
    dateFormat: config.date_format || null,
  };
}

export function getLocalisation() {
  return _config;
}

export function resetLocalisation() {
  _config = null;
}
