# Changelog

All notable changes to this project are documented here.

## [0.21.0] - 2026-06-30

### Fixed
- **Scheduler stability**: replaced a 5-minute blocking `sleep()` in the
  weather-provider retry loop with a short exponential backoff. The old
  delay could stall the daily refresh for hours across the full park
  registry and blow past PHP's execution time limit.
- **Invalid `drainage` enum default**: `'unknown'` was being used as the
  `DEFAULT` for a column whose `ENUM` didn't include that value, causing
  MySQL to silently store an empty string instead.
- **Undefined array key warnings**: visitor suggestion form and admin
  park form no longer fatal/warn on PHP 8 when optional fields are absent
  (e.g. the "new park" fields when an existing park is selected, or an
  unchecked checkbox).
- **Possible fatal error**: suggestion email notification no longer errors
  if the linked park has since been deleted.

### Security
- Sanitized the `action` GET parameter in the admin suggestions screen.
- Marked two unprepared (but non-exploitable) `$wpdb` queries with
  `phpcs:ignore` and an explanation, rather than leaving them looking like
  an oversight for the next person editing nearby code.

### Changed
- Version bumped to 0.21.0 across the plugin header, `DOGPARK_VERSION`,
  and `package.json`.

### Added
- `HANDOVER.md` — session handover notes, going forward this is updated
  at the end of every working session.
