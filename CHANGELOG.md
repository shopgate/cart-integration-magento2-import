# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]


## 2.9.5
### Changed
- Composer file details

## 2.9.4
### Fixed
- Missing coupon issue in order import
- Moved DB transaction commit after SG order save, just in case saving throws an error

## 2.9.3
### Fixed
- Added shipping mapping to add_order process

## 2.9.2
### Fixed
- Fixed missing price for configurable product that was added last to cart

## 2.9.1
### Fixed
- The shipping description will now be fetched from the Shopgate Order on import

## 2.9.0
### Added
- Implemented register_customer call

[Unreleased]: https://github.com/shopgate/cart-integration-magento2-import/compare/2.9.5...HEAD
