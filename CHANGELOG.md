# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [2.9.11] - 2019-06-17
### Added
- Compatibility with Base module's constructor changes
### Fixed
- Mapping of shipping method during order import

## [2.9.10] - 2019-06-04
### Fixed
- Sending of confirmation mail in case of a failed order import
- Status issues after unholding orders, which were imported with the status On Hold

## [2.9.9] - 2018-10-28
### Added
- Configuration for Shopgate payment method title

## [2.9.8] - 2018-08-01
### Added
- Validation of app-only cart rules in order import

## [2.9.7] - 2018-04-19
### Fixed
- Item quantity in shipping mapping
- Saving of the address in customer registration
### Added
- Support of asynchronous sending setting for sales emails

## [2.9.6]
### Changed
- Changed the GitHub composer naming so that it does not clash with Marketplace repo

## [2.9.5]
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

[Unreleased]: https://github.com/shopgate/cart-integration-magento2-import/compare/2.9.11...HEAD
[2.9.11]: https://github.com/shopgate/cart-integration-magento2-import/compare/2.9.10...2.9.11
[2.9.10]: https://github.com/shopgate/cart-integration-magento2-import/compare/2.9.9...2.9.10
[2.9.9]: https://github.com/shopgate/cart-integration-magento2-import/compare/2.9.8...2.9.9
[2.9.8]: https://github.com/shopgate/cart-integration-magento2-import/compare/2.9.7...2.9.8
[2.9.7]: https://github.com/shopgate/cart-integration-magento2-import/compare/2.9.6...2.9.7
[2.9.6]: https://github.com/shopgate/cart-integration-magento2-import/compare/2.9.5...2.9.6
