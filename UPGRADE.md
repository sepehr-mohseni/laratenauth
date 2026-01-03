# Upgrade Guide

## Upgrading to 1.0 from Beta

This is the first stable release. No breaking changes.

## Future Upgrades

Breaking changes and migration guides will be documented here for future releases.

### General Upgrade Steps

1. Update your `composer.json`:
```bash
composer require sepehr-mohseni/laratenauth:^1.0
```

2. Publish new configuration (if available):
```bash
php artisan vendor:publish --tag="laratenauth-config" --force
```

3. Run new migrations (if available):
```bash
php artisan migrate
```

4. Clear caches:
```bash
php artisan config:clear
php artisan cache:clear
```

5. Review changelog for breaking changes

6. Update your code according to migration notes

7. Test thoroughly in staging environment

## Version-Specific Notes

### 1.0.0
- Initial stable release
- All features tested and documented
- Production-ready
