# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in MyChat, please report it responsibly. **Do not open a public issue.**

Instead, send a detailed report to the project maintainer through one of the following:

- **GitHub Security Advisories** -- use the [private vulnerability reporting](https://github.com/valasme/my-chat/security/advisories/new) feature on this repository
- **Email** -- contact the maintainer directly

### What to Include

- A description of the vulnerability and its potential impact
- Steps to reproduce the issue
- Any relevant logs, screenshots, or proof-of-concept code
- Your suggested fix, if you have one

### Response Timeline

- **Acknowledgment** -- within 72 hours of the initial report
- **Assessment** -- an initial severity assessment within one week
- **Resolution** -- a patch or mitigation as soon as reasonably possible, depending on severity

### What Happens Next

1. The report is acknowledged and triaged
2. A fix is developed and tested privately
3. A security release is published
4. Credit is given to the reporter (unless anonymity is preferred)

## Scope

This policy covers the MyChat application code in this repository. It does not cover third-party dependencies, though reports about vulnerable dependencies are appreciated and will be acted on.

## Supported Versions

Only the latest version on the `main` branch receives security updates.

## Best Practices for Deployers

- Keep PHP, Composer, and Node.js updated
- Set `APP_DEBUG=false` in production
- Use strong `APP_KEY` values and rotate them if compromised
- Enable HTTPS for all traffic
- Use production-grade database and session drivers
- Review rate limiting configuration for your traffic patterns
