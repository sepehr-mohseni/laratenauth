# Changelog

All notable changes to `laratenauth` will be documented in this file.

## 1.0.0 - 2026-01-03

### Added
- Initial release
- Multi-tenant authentication with support for session, Sanctum, JWT, and custom drivers
- Multiple tenant identification strategies (subdomain, domain, path, header, manual)
- Tenant-scoped authentication guards
- Tenant switching functionality for users with multi-tenant access
- Database migrations for tenants, tenant_users, and tenant_tokens tables
- Comprehensive middleware suite (IdentifyTenant, AuthenticateTenant, EnsureTenantAccess, etc.)
- TenantAuth facade for easy tenant management
- Traits: BelongsToTenant, HasTenants, TenantScoped
- Events: TenantIdentified, TenantAuthenticated, TenantSwitched, etc.
- Session isolation per tenant
- Rate limiting per tenant
- Comprehensive test suite with 90%+ coverage
- Support for Laravel 10.x, 11.x, and 12.x
- Detailed documentation and examples
