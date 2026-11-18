# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-11-18

First release. Requires PHP 8.2+ and Laravel 12 or 13.

### Added

- Client for the Discogs API v2 covering the database, marketplace, inventory
  export/upload, users, collection, wantlist and lists endpoints.
- Personal token, consumer key/secret and OAuth 1.0a credentials, plus the
  three-legged OAuth flow.
- `Page` pagination helper with lazy iteration across pages.
- Optional retry and throttling for rate limits (429), and automatic retry of
  GET requests after connection errors or 5xx responses.
- Typed exceptions, all extending `DiscogsException`.

[Unreleased]: https://github.com/osaris-uk/discogs/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/osaris-uk/discogs/releases/tag/v1.0.0
