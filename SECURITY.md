# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

We take the security of LaraTenAuth seriously. If you discover a security vulnerability, please follow these steps:

1. **Do NOT** open a public GitHub issue
2. Email isepehrmohseni@gmail.com with:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

3. We will respond within 48 hours
4. We'll work with you to understand and resolve the issue
5. We'll credit you in the security advisory (unless you prefer to remain anonymous)

## Security Best Practices

When using this package:

1. **Always validate tenant access** - Use the `tenant.access` middleware
2. **Enable session isolation** - Set `session.isolated` to `true` in config
3. **Use token expiration** - Set appropriate token expiration times
4. **Enable rate limiting** - Configure `security.rate_limit` settings
5. **Log security events** - Enable `security.log_auth_events`
6. **Disable impersonation in production** - Set `security.allow_impersonation` to `false`
7. **Use HTTPS** - Always use SSL/TLS in production
8. **Validate input** - Sanitize all user input before tenant operations
9. **Regular updates** - Keep the package updated to the latest version
10. **Review access logs** - Monitor tenant access patterns

## Known Security Considerations

- **Session Fixation**: The package regenerates sessions on tenant switch to prevent fixation attacks
- **Cross-Tenant Access**: All queries are automatically scoped when using `TenantScoped` trait
- **Token Validation**: Tokens are hashed and validated against tenant context
- **Rate Limiting**: Login attempts are rate-limited per tenant

## Disclosure Policy

- We follow responsible disclosure practices
- Security fixes are released as soon as possible
- Security advisories are published on GitHub
- CVE identifiers are requested for significant vulnerabilities
