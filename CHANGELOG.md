# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.3] - 2025-10-16

### Fixed
- **CRITICAL**: Fixed Payment entity state not being updated when payment notifications are received
- NotifyPaymentRequestHandler now applies transitions to both PaymentRequest AND Payment entities
- Orders are now correctly marked as paid when Monetico sends successful payment notifications

### Technical Details
- Added PaymentTransitions to update the Payment entity state alongside PaymentRequest state
- Payment state transitions (complete/fail) are now applied based on Monetico notification status
- This triggers Sylius's order payment state resolution to properly update order status

## [1.1.2] - 2025-10-16

### Fixed
- **CRITICAL**: Fixed NotifyPaymentRequestHandler not being executed when Monetico sends payment notifications
- NotifyPaymentRequest command now includes notification data instead of relying on RequestStack
- MAC signature validation now happens in NotifyController before command dispatch
- Changed NotifyController to use sylius.command_bus for better integration with Sylius messenger routing
- Removed RequestStack dependency from NotifyPaymentRequestHandler for async compatibility

### Changed
- Refactored payment notification flow to pass validated data through the command
- Updated NotifyHttpResponseProvider to include proper Content-Type header and CDR response

## [1.1.1] - 2025-10-16

### Added
- Status payment request command provider to support payment status checks on "after pay" page
- StatusPaymentRequest command for handling status requests
- StatusPaymentRequestHandler for processing status checks
- StatusPaymentRequestCommandProvider for status action support
- StatusHttpResponseProvider to redirect users to thank you page after payment

### Fixed
- Fixed "No payment request command provider supported for 'status'" error when accessing after pay page
- Improved exception handling in NotifyController by using RuntimeException instead of NotFoundException for invalid data

## [1.1.0] - 2025-10-16

### Added
- Detailed payment flow documentation in CLAUDE.md
- Payment request hash now included in Monetico's `texte-libre` field for improved security and tracking

### Changed
- **BREAKING**: NotifyController now extracts hash from `texte-libre` parameter instead of URL
- Unified success and error URLs in CapturePaymentRequestHandler for consistent user experience
- Updated shop route to remove hash from notification URL
- Modified CapturePaymentRequestHandler to ensure all payment requests include a hash
- Enhanced README.md to document `texte-libre` field changes and hash handling

### Removed
- MoneticoService class as part of codebase cleanup and refactoring

### Fixed
- Improved payment notification handling by moving hash extraction to server-side parameter

## [1.0.0] - 2025-10-16

### Added
- Initial release of LakeDynamics Sylius Monetico Plugin
- Complete Monetico payment gateway integration for Sylius
- Docker-based development environment
- PHPUnit and Behat test suites
- Code quality tools (PHPStan, ECS)
- Comprehensive documentation and development guides

[1.1.3]: https://github.com/lakedynamics/sylius-monetico-plugin/compare/v1.1.2...v1.1.3
[1.1.2]: https://github.com/lakedynamics/sylius-monetico-plugin/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/lakedynamics/sylius-monetico-plugin/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/lakedynamics/sylius-monetico-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/lakedynamics/sylius-monetico-plugin/releases/tag/v1.0.0
