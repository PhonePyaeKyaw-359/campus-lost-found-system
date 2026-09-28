# Campus Lost and Found System

A PHP and MySQL prototype for recording, searching, claiming, and returning campus lost-and-found items.

## Current progress

The project foundation is in place:

- MySQL schema, constraints, indexes, and seed script
- Application configuration and a PDO connection factory
- Secure folder layout where only `public/` is intended to be web-accessible
- Secure login/logout, CSRF protection, session timeout, account lockout, role-aware dashboard, and activity logging
- Student lost-item reports with controlled-list validation, ownership checks, and private photo delivery
- Student found-item reports, limited to their author and staff/admin review; staff can close an unreceived report with an audit-log reason

The next feature is official found-item registration by staff.

## Local setup

1. Create a MySQL database by running `database/schema.sql`.
2. Run `database/grants.example.sql` as a MySQL administrator after replacing its placeholder password. Copy `config/local.example.php` to `config/local.php` and use the same password.
3. Run `php database/seed.php` once to create demo accounts and sample found items.
4. Configure Apache's document root to the `public/` directory.

The seed command prints the demo account passwords only once. Do not use those accounts or passwords outside local development.
