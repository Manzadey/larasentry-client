# Larasentry Client

A lightweight exception tracker client for Laravel. 
Designed to be used with the **Larasentry Server** (a Go-based microservice) to collect exceptions and send aggregated reports to Telegram.

## Features
- Lightweight and failsafe (2s timeout, 1s connect timeout)
- Optional background delivery via Laravel Queue (e.g. `default`, `sentry`)
- Automatically collects runtime & environment context:
  - `php_version` (e.g. `8.2.18` via `PHP_VERSION`)
  - `environment` (e.g. `production` / `staging`)
  - `release` (application version or Git commit tag)
  - `tags` with `laravel_version` and `php_os`
- Automatically ignores 4xx HTTP errors, Validation and Authentication exceptions
- Hooks directly into Laravel's Exception Handler

## Installation

You can install the package via composer:

```bash
composer require manzadey/larasentry-client
```

## Configuration

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=larasentry-config
```

Add the following variables to your `.env` file:

```env
# The URL of your Go-based Larasentry Server
LARASENTRY_URL=http://your-server.com

# Optional: project identifier (defaults to APP_NAME)
LARASENTRY_PROJECT=my-app

# Optional: API key (only if your server has API key auth configured)
# LARASENTRY_API_KEY=your-project-api-key

# Optional: queue name for background processing (e.g. default, sentry)
# If omitted or empty, exceptions are sent synchronously
LARASENTRY_QUEUE=default

# Optional: queue connection (e.g. redis, database)
LARASENTRY_QUEUE_CONNECTION=redis

# Optional: send Laravel Context (Context::add) and Exception::context() with each event.
# Hidden context (Context::addHidden) is never sent. Keys from scrub_fields are masked.
LARASENTRY_COLLECT_CONTEXT=true

# Optional: max size of each context block in bytes; larger blocks are replaced with a marker
LARASENTRY_MAX_CONTEXT_BYTES=16384

# Optional: application version / release tag
APP_VERSION=1.0.0
```

### Server address

The address is read from `larasentry.url` (`LARASENTRY_URL`). `larasentry.server_url` (`LARASENTRY_SERVER_URL`) is an alias kept for backward compatibility: `url` wins when both are set to different values. If the address is empty, nothing is sent.

## Usage

Once installed and configured, the package automatically captures unhandled `500+` exceptions and sends them to your tracker. No manual code changes are required.

To test if it works, send a test incident:
```bash
php artisan larasentry:test
php artisan larasentry:test --message="Hello from production"
```

The command sends synchronously (ignoring `LARASENTRY_QUEUE`) and exits with a non-zero code if the server URL is missing, the server is unreachable, or it responds with an error status.

## License

The MIT License (MIT).
