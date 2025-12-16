# Changelog - custom-css-loader

All notable changes to this plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Nothing yet

### Changed
- Nothing yet

### Fixed
- Nothing yet

## [2.0.0] - 2025-12-16

### Changed
- fixed version in plugin.php
- update PHP matrix to 8.1+ and add PHPStan job
- Add src/ and composer.json to release include
- Major architecture overhaul with security hardening
- Add support development section to README

### Fixed
- use readonly properties instead of readonly class for PHP 8.1

## [1.0.0] - 2025-12-13

### Added
- add MLM Gallery branding and fix nav overflow

### Changed
- changed color in wide-layout-staff.css
- debug: add extensive logging to trace execution flow

### Fixed
- align header, nav, subnav and content to same width
- detect context via request path instead of constants
- use output buffer callback for reliable CSS injection

## [0.1.1] - 2025-12-13

### Added
- Add wide-layout demo CSS files

### Fixed
- Use output buffering for CSS injection
- Add debug logging and fix null default value handling

## [0.1.0] - 2025-12-13

### Added
- Initial implementation of Custom CSS Loader plugin

### Changed
- Add CHANGELOG.md with Unreleased section

