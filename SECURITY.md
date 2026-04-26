# Security policy

## Reporting a vulnerability

If you discover a security issue in this package, please **do not** open
a public GitHub issue. Disclose privately via either channel:

- **GitHub Security Advisories** —
  [Open a draft advisory](https://github.com/sandermuller/laravel-fluent-validation-rector/security/advisories/new)
  on the repository. This is the preferred channel; advisories support
  coordinated disclosure with a CVE assignment.
- **Email** — `github@scode.nl` (PGP-encrypted submissions accepted on
  request).

Please include:

- A description of the vulnerability and its impact
- Steps to reproduce (minimal proof-of-concept welcome)
- Affected version(s) of the package
- Your suggested fix or mitigation, if any

You should receive an acknowledgment within **72 hours**. Coordinated
public disclosure happens after a patch is shipped.

## Supported versions

Pre-1.0, security fixes are backported to the latest minor only. Once
1.0.0 is tagged, the support window narrows to:

| Version | Status              | Security fixes |
|---------|---------------------|----------------|
| 1.x     | Latest minor        | Yes            |
| 1.(x-1) | Previous minor      | Critical only  |
| < 1.0   | Pre-release         | Latest only    |

Consumers on older versions are encouraged to upgrade to the latest
minor. The package follows [SemVer](https://semver.org), so minor-level
upgrades within the same major are safe by policy
(see [Versioning policy](README.md#versioning-policy)).

## Scope

This policy covers vulnerabilities in:

- Source code under `src/`
- Configuration files under `config/`
- Build / release tooling that ships with the package

Out of scope:

- Vulnerabilities in upstream dependencies (`rector/rector`,
  `nikic/php-parser`, etc.) — report those upstream
- Issues in consumer-side code, even if the consumer pattern is
  recommended in this package's documentation
