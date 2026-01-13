# Publishing to Packagist

This document provides information about the `fairpm/did-manager` package on [Packagist.org](https://packagist.org).

## Package Information

- **Package name**: `fairpm/did-manager`
- **Packagist URL**: https://packagist.org/packages/fairpm/did-manager
- **Repository**: https://github.com/fairpm/did-manager
- **License**: MIT

## Auto-Update Configuration

The package is configured to automatically update on Packagist when new releases are pushed to GitHub.

- **GitHub Webhook**: Configured and active
- **Composer Authorization**: Added to the `fairpm` organization for auto-updates
- **Packagist Credentials**: Stored in 1Password

## Vendor Permissions

- Our Packagist account has manager permissions for the `fairpm` vendor name
- Vendor name ownership originated with @joedolson's Packagist account

## Manual Update

If you need to manually trigger an update:

1. Visit https://packagist.org/packages/fairpm/did-manager
2. Log in with credentials from 1Password
3. Click the **Update** button

## Installation

Users can install the package via Composer:

```bash
composer require fairpm/did-manager
```

## Troubleshooting

### Package Not Updating

If a new release doesn't appear on Packagist:

1. Verify the GitHub release exists: https://github.com/fairpm/did-manager/releases
2. Check the GitHub webhook status in [repository settings](https://github.com/fairpm/did-manager/settings/hooks)
3. Manually trigger an update using the steps above
4. Verify [composer.json](../composer.json) is valid:
   ```bash
   composer validate --strict
   ```

### Getting Help

- Review the [Packagist documentation](https://packagist.org/about)
- Open an issue on [GitHub](https://github.com/fairpm/did-manager/issues) if problems persist
