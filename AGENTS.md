# Panda Seasons repository instructions

## Scope

This repository contains the standalone Panda Seasons WordPress plugin in `panda-seasons/`.

## Product invariants

- Web calendars remain usable without ContactPanda.
- Email-calendar subscriptions and delivery are available only while the free ContactPanda plugin is active.
- A calendar subscription never grants consent for unrelated newsletters or marketing.
- Calendar-only, all-calendar, and all-mailing unsubscribe scopes must remain distinct and auditable.
- Multiple calendars may run in parallel without duplicate delivery of the same door.
- Never assume the default `wp_` table prefix.
- Destructive operations require capabilities, nonces, and explicit confirmation.

## Internationalization and presentation

- Source strings are English and use the `panda-seasons` text domain.
- Update POT and German PO/MO files when user-facing strings change.
- Keep styles and scripts in the existing asset files.
- Use accessible WordPress controls and progressive enhancement.

## Verification

Before handing off a change, run `./tools/check.sh`. Build release ZIPs only for an explicitly requested release.
