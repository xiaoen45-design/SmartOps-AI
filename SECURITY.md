# Security and Demo Data

This repository is an academic portfolio project and is intended for local demonstration and code review.

- Secrets and local credentials must be stored in `config/local.php` or `smartops_fastapi/.env`; both are excluded from Git.
- Only example configuration files are committed.
- The bundled SQL snapshot contains synthetic/demo operational records. Guest messaging identifiers have been removed from the public version.
- Do not deploy the project to a public production server without a full security review, production authentication, HTTPS, secret management, CSRF coverage, rate limiting, and environment-specific database permissions.
- If you discover a credential or personal identifier in a public fork, remove it from Git history rather than only deleting it in a later commit.
