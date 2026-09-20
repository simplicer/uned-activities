# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.12] - 2026-09-20

### Security

- Fail closed on magic-link single-use consumption and require explicit user confirmation for ?token= deep links (prevented replayed-token authentication and forced login).
- Enforce http(s)-only allowlists on scraped URLs: crawl URLs restricted to the UNED host (stored-URL SSRF) and surfaced catalog links/images restricted to http(s) (stored XSS via enrollment link), enforced at harvest parsers and again at SPA render time.
- Escape harvested title and URL in activity-update HTML emails (stored HTML injection).
- Bound rate-limit buckets: always-on IP bucket plus hashed bearer key (prevented bearer-rotation bypass of the public throttle).
- Send the Gemini embedding API key via the x-goog-api-key header instead of the URL query string.
- Exclude every environment/secret file from the image build context.

### Fixed

- Harvest command now fails (with retries and visible logs) when discovery returns zero activities, instead of silently succeeding and freezing the catalog.
- Catalog persistence updates the existing uned_id row (keeping its id, adopting the new URL) when UNED moves an activity, instead of updating zero rows and permanently skipping refresh (doc/todo-fixes.md backlog item, closed with unit and pgsql integration tests).
- Digest dedupe compares against the notification type actually written, and the digest now runs after each successful harvest (it was never scheduled, so opt-in digests were never sent).
- Operations runbook troubleshooting now matches the harvest-loop scheduling reality.

### Changed

- Version artifacts reconciled with the derived history count (1.1.3) and propagated to the 1.1.12 release across VERSION, README, OpenAPI, compose image tags and Traefik API version header.
