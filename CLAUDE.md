# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

FluentSMTP is a WordPress SMTP plugin that connects WordPress with various email service providers. It provides native integrations with 15+ email services, email routing, logging, and detailed reporting. The plugin uses a modern stack with VueJS 2 frontend and PHP backend following WordPress plugin architecture.

## Development Commands

### Frontend Development
```bash
# Install dependencies
npm install

# Development mode with watch
npm run start
# or
npx mix watch

# Production build
npm run prod
# or
npx mix --production
```

### Backend Development
```bash
# Install PHP dependencies
composer install

# Static analysis
vendor/bin/phpstan analyse
```

### Build & Release
```bash
# Build production-ready plugin zip
./build.sh
```

The build script:
1. Builds frontend assets with production optimization
2. Installs composer dependencies with `--no-dev --classmap-authoritative`
3. Creates `fluent-smtp.zip` excluding dev files
4. Restores dev dependencies

## Architecture

### PHP Backend Structure

**Namespace Convention:**
- `FluentMail\App\` → `app/` directory (application logic)
- `FluentMail\Includes\` → `includes/` directory (framework components)

**Key Directories:**

- `app/Http/` - Controllers, routes, and policies
  - `routes.php` - All AJAX endpoint definitions
  - `Controllers/` - Handle HTTP requests
  - `Policies/` - Authorization logic

- `app/Services/` - Business logic services
  - `Mailer/Providers/` - Email provider integrations (SendGrid, Mailgun, Gmail, etc.)
  - `Mailer/BaseHandler.php` - Base class for all email handlers
  - `Notification/` - Notification channels (Telegram, Slack, Discord)
  - `DB/` - Database query builders and models

- `app/Models/` - Database models (Settings, Logger)

- `app/Hooks/` - WordPress action/filter handlers

- `includes/Core/` - Framework foundation
  - `Application.php` - Main application container
  - `Container.php` - Dependency injection container

- `includes/Support/` - Helper classes and utilities

- `database/` - Database migrations
  - `FluentMailDBMigrator.php` - Migration runner
  - `migrations/` - Individual migration files

### Frontend Structure

**Location:** `resources/admin/`

**Tech Stack:**
- Vue.js 2
- Vue Router
- Element UI component library
- Chart.js for analytics

**Entry Points:**
- `boot.js` → Compiled to `assets/admin/js/boot.js`
- `start.js` → Compiled to `assets/admin/js/fluent-mail-admin-app.js`
- `routes.js` - Frontend routing configuration

**Key Directories:**
- `Modules/` - Feature modules (dashboard, settings, logs)
- `Bits/` - Reusable Vue components
- `Pieces/` - Shared UI pieces

**Build Output:** `assets/admin/`

### Plugin Loading Flow

1. `fluent-smtp.php` - Main plugin file, defines constants, overrides `wp_mail()`
2. `boot.php` - Defines constants and loads autoloader
3. `includes/Core/Application.php` - Bootstraps application, registers hooks
4. Action hook `fluentMail_loaded` fires with application instance

**Important:** The plugin forces itself to load first among all plugins to override `wp_mail()` function before other plugins.

### Email Provider Architecture

All email providers extend `BaseHandler` in `app/Services/Mailer/BaseHandler.php` and implement:
- `send()` method for sending emails
- Connection validation
- Settings schema

Providers located in: `app/Services/Mailer/Providers/`

Each provider folder contains:
- `Handler.php` - Main implementation
- `Settings.php` - Settings UI configuration

### Database Layer

Uses custom DB abstraction layer in `app/Services/DB/` instead of WordPress $wpdb for query building.

Table prefix: `fsmpt_` (defined as `FLUENT_MAIL_DB_PREFIX`)

### Constants

Defined in `boot.php`:
- `FLUENTMAIL` - Plugin slug
- `FLUENTMAIL_PLUGIN_VERSION` - Current version
- `FLUENTMAIL_UPLOAD_DIR` - Upload directory path
- `FLUENT_MAIL_DB_PREFIX` - Database table prefix
- `FLUENTMAIL_PLUGIN_URL` - Plugin URL
- `FLUENTMAIL_PLUGIN_PATH` - Plugin directory path

## Code Patterns

### Adding New Email Provider

1. Create folder in `app/Services/Mailer/Providers/[ProviderName]/`
2. Implement `Handler.php` extending `BaseHandler`
3. Implement `Settings.php` for admin UI configuration
4. Register in `app/Services/Mailer/Providers/Factory.php`
5. Add configuration in `app/Services/Mailer/Providers/config.php`

### Adding API Endpoint

1. Add route in `app/Http/routes.php`
2. Create or update controller in `app/Http/Controllers/`
3. Frontend calls via AJAX to WordPress admin-ajax.php

### Adding Vue Component

1. Create component in `resources/admin/Bits/` (reusable) or `resources/admin/Modules/` (feature-specific)
2. Import and register in parent component or routes
3. Run `npm run start` to watch changes

## Testing

Currently no automated test suite. PHPStan configured at level 0 for static analysis.

## Third-Party Libraries

**PHP:**
- Google API Client in `includes/libs/google-api-client/` (excluded from autoload)

**JavaScript:**
- Element UI for components
- Chart.js for analytics
- Day.js for date handling
- Lodash for utilities

## Important Notes

- Email routing logic allows multiple email providers with fallback support
- Email logging stores all outbound emails for debugging/resending
- OAuth2 implementation in `includes/OAuth2Provider.php` for Gmail/Outlook
- Translation strings extracted to `app/Services/TransStrings.php`
- Assets built with Laravel Mix (webpack wrapper)
