# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the package uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Client for the Discogs API v2 covering the database, marketplace, inventory
  export/upload, users, collection, wantlist and lists endpoints.
- Personal token, consumer key/secret and OAuth 1.0a credentials, plus the
  three-legged OAuth flow.
- `Page` pagination helper with lazy iteration across pages.
- Optional retry and throttling for rate limits (429), and automatic retry of
  GET requests after connection errors or 5xx responses.
- Typed exceptions, all extending `DiscogsException`.
