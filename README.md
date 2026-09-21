# Campus Lost and Found System

A PHP and MySQL prototype for recording, searching, claiming, and returning campus lost-and-found items.

## Current progress

The project foundation is in place:

- MySQL schema, constraints, indexes, and seed script
- Application configuration and a PDO connection factory
- Secure folder layout where only `public/` is intended to be web-accessible
- Shared bootstrap, escaping, redirects, and error logging helpers

The next feature is authentication and role-based access control.

## Local setup

1. Create a MySQL database by running `database/schema.sql`.
2. Copy `config/local.example.php` to `config/local.php` and enter local database credentials.
3. Run `php database/seed.php` once to create demo accounts and sample found items.
4. Configure Apache's document root to the `public/` directory.

The seed command prints the demo account passwords only once. Do not use those accounts or passwords outside local development.
