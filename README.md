# Larasentry Client

A lightweight exception tracker client for Laravel. 
Designed to be used with the **Larasentry Server** (a Go-based microservice) to collect exceptions and send aggregated reports to Telegram.

## Features
- Fully asynchronous (does not slow down your app)
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

# The unique API key generated for this project
LARASENTRY_API_KEY=your-project-api-key
```

## Usage

Once installed and configured, the package automatically captures unhandled `500+` exceptions and sends them to your tracker. No manual code changes are required.

To test if it works, you can run:
```bash
php artisan tinker --execute="app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->report(new Exception('Test Exception'));"
```

## License

The MIT License (MIT).
