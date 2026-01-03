# Examples

This directory contains example implementations and use cases for the LaraTenAuth package.

> **Note:** These examples reference classes from your application (e.g., `App\Models\Tenant`, 
> `App\Models\User`, `Controller`). They are meant to be copied and adapted into your own 
> Laravel project, not executed directly within this package.

## Basic SaaS Application

See `basic-saas-example.php` for a complete example of:
- User registration and tenant creation
- Multi-tenant authentication
- Tenant switching
- Role-based access control

## API with Tenant Tokens

See `api-example.php` for:
- API token generation
- Token-based authentication
- Ability-based authorization
- Token management

## Multi-Database Architecture

See `multi-database-example.php` for:
- Separate databases per tenant
- Database connection switching
- Tenant-specific migrations

## Advanced Customization

See `custom-resolver-example.php` for:
- Custom tenant resolution strategies
- Custom authentication logic
- Event listeners
- Model scoping patterns
