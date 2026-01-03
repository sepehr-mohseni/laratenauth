# Contributing to LaraTenAuth

Thank you for considering contributing to LaraTenAuth! Here are some guidelines to help you get started.

## Development Setup

1. Fork and clone the repository:
```bash
git clone https://github.com/sepehr-mohseni/laratenauth.git
cd laratenauth
```

2. Install dependencies:
```bash
composer install
```

3. Run tests:
```bash
composer test
```

## Coding Standards

This package follows PSR-12 coding standards. Format your code before submitting:

```bash
composer format
```

Run static analysis:

```bash
composer analyse
```

## Testing

- Write tests for all new features
- Maintain 90%+ code coverage
- Run the test suite before submitting PRs
- Include both unit and feature tests where appropriate

```bash
# Run all tests
composer test

# Run with coverage report
composer test-coverage
```

## Pull Request Process

1. Create a feature branch from `main`
2. Make your changes
3. Add/update tests as needed
4. Update documentation if needed
5. Ensure all tests pass
6. Submit a pull request with a clear description

## Code Review

All submissions require review. We'll provide feedback and may request changes before merging.

## Reporting Bugs

Please use GitHub issues to report bugs. Include:

- Laravel version
- PHP version
- Package version
- Steps to reproduce
- Expected vs actual behavior
- Any relevant logs or error messages

## Feature Requests

We welcome feature requests! Please open an issue describing:

- The problem you're trying to solve
- Your proposed solution
- Any alternative solutions you've considered

## Security Vulnerabilities

Please do not report security vulnerabilities through public GitHub issues. Email security@example.com instead.

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
