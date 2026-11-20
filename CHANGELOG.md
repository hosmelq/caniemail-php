# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.1.1 - 2026-11-20

- Require wikimedia/css-sanitizer 6.2.2 or newer.
- Check CSS that contains long strings, such as data URIs, which used to throw a `Safe\Exceptions\PcreException`.
- Detect `display: inline-grid` as `display:grid`, like `display: inline-flex` is detected as `display:flex`, and detect the two-keyword syntax in either order, such as `display: inline grid` and `display: flex block`, even with comments between the keywords.

## v1.1.0 - 2026-11-07

- Add the `ClientFamily` and `ClientId` enums, with a case for each family and client in the bundled data, and accept their cases in `check()`.

## v1.0.0 - 2026-11-07

- Initial release.
