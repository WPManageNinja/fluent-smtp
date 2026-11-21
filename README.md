### FluentSMTP - WordPress SMTP & Email Service API connection Plugin

---

**[Get it on WordPress.org](https://wordpress.org/plugins/fluent-smtp/) | [Facebook Community](https://www.facebook.com/groups/fluentcrm) | [Docs](https://fluentsmtp.com/docs)**

![FluentSMTP Banner](https://ps.w.org/fluent-smtp/assets/banner-1544x500.png)

Are you having problems with your WordPress emails not sending? This is the plugin that will solve your email deliverability problem.
FluentSMTP is the ultimate WP Mail Plugin that connects with your Email Service Provider natively and makes sure your emails are delivered 💯.

Our goal is to send your WordPress emails fast, secure, and have them reach the inbox.

Fluent SMTP plugin fixes your email delivery issue by connecting WordPress Mail with your email service providers. These integrations are native so it will send the emails superfast.

Connect as many email Service Providers as you want, and FluentSMTP will route your transactional and marketing emails automatically. This is one of the unique features that FluentSMTP has to offer.

#### 🎉 Available Email Service Connections
- Amazon SES
- Gmail OAuth
- Google Workspace OAuth
- Outlook / Office 365 OAuth
- SendGrid
- Mailgun
- Cloudflare Email
- toSend
- Brevo (Sendinblue)
- Netcore (Pepipost)
- Postmark
- SparkPost
- SMTP2GO
- Elastic Email
- Zoho via SMTP
- Any SMTP email provider
- More native integrations coming soon

#### 🎉 Fluent SMTP features
Fluent SMTP is the fastest and most advanced WordPress Mail SMTP plugin on the market. We crafted this plugin for speed, reliability and scalability.

* Real-Time Email Delivery
* Email Routing to multiple email connections
* Connect with Any Email Service Providers
* Fallback Email Connection
* Email Logging
* Resend Emails to any recipient, with full resend history
* Detailed Reporting
* Daily Connection Health Monitoring
* Real-time failure notifications via Telegram, Slack, Discord and Pushover
* WP-CLI support
* Super fast UI powered by VueJS

Most importantly, this plugin is free and will always be free.
👉 <a href="https://fluentsmtp.com/articles/why-we-built-fluentsmtp-plugin/">Read Why it's 100% free (always)</a> 👈

#### Monitor delivery failures and fallback sends from an MU plugin

FluentSMTP fires the WordPress action `fluentmail_delivery_outcome` when a transport send fails or the configured backup accepts a message after the primary connection fails. It passes two arguments:

| Argument | Meaning |
| --- | --- |
| `$outcome` | `failed` when the send fails, including when a configured backup cannot be resolved. `sent_via_fallback` when the backup accepts the message. |
| `$log_id` | Integer ID of the primary failure in this site's FluentSMTP email log, or `0` when unavailable. A listener can report the event in either case. |

For example, sites with a central PHP log collector can deploy the following as `wp-content/mu-plugins/managed-mail-alerts.php` using MainWP. The collector can route the two event types to different channels. The example records the site and outcome without including message content.

```php
<?php
/**
 * Plugin Name: Managed Mail Alerts
 */
defined('ABSPATH') || exit;

add_action('fluentmail_delivery_outcome', static function ($outcome, $log_id) {
    try {
        error_log(sprintf('[managed-mail] site=%s event=%s log_id=%d', home_url('/'), $outcome, $log_id));
    } catch (\Throwable $error) {
        // Keep a collector error inside this callback so other observers can run.
        return;
    }
}, 10, 2);
```

No built-in alert channel needs to be enabled. Each send is evaluated separately, including retries. Ordinary primary successes and FluentSMTP test-mode sends are silent. The action covers sends that reach transport dispatch. A plugin that short-circuits `pre_wp_mail` owns that outcome. Backup acceptance indicates that the transport accepted the message, not that it reached the inbox.

Catch collector errors inside the callback so the next listener can run. If a listener throws a PHPMailer exception after the backup accepts a message, WordPress can fire `wp_mail_failed` and return `false`. Retrying on that signal can send a duplicate. Other uncaught exceptions can propagate to the caller. Queue slow collector requests outside the send. Mail sent by the callback does not generate another outcome notification.

#### Contribute
FluentSMTP is built with Vue 3 and Element Plus (frontend), bundled with Vite. It's backend communication is based on standard WordPress AJAX endpoints.

All endpoints can be found in `app/Http/routes.php`.

All the email connection drivers can be found in `app/Services/Mailer/Providers`.

**Thanks to our contributors**

![GitHub Contributors Image](https://contrib.rocks/image?repo=WPManageNinja/fluent-smtp)

#### Getting Started
- Clone this repository.
- Run `composer install` to install PHP dependencies.
- Run `pnpm install` to install the frontend dependencies. The lockfile is
  `pnpm-lock.yaml`, so pnpm is the package manager here; `npm install` would
  ignore it and resolve its own versions.

#### Build JavaScript source

- Run `pnpm run dev` (alias: `pnpm run start`) for development. It builds `boot.js`
  once and then watches the app bundle, which is where nearly all the code is. If you
  are editing `boot.js` or `Bits/FluentMail.js`, run `pnpm run dev:boot` alongside it
  in a second terminal.
- Run `pnpm run build` (alias: `pnpm run prod`) to build for production.
- Run `./build.sh` to produce the release zip. It installs from the lockfile
  first, so it does not depend on what is already in `node_modules`.

All VueJS code can be found in `resources/admin`.

Translation strings used by the Vue app are extracted with `npm run i18n`,
which regenerates `app/Services/TransStrings.php`. Only literal strings passed
to `$t('...')` are extracted, so a `$t(someVariable)` call will render
untranslated and the extractor will warn about it.

#### Running the tests

The suite runs locally through WP-CLI against a real development WordPress
install. There is no Docker, PHPUnit, `wp-env`, or CI service to set up.

```bash
bash tests/bin/run-all.sh              # everything
bash tests/bin/run-all.sh static       # lint and route-coverage gates
bash tests/bin/run-all.sh smoke        # admin-AJAX smoke
bash tests/bin/run-all.sh permissions  # every POST route as anonymous + subscriber
bash tests/bin/run-all.sh integration  # database, connection and domain behaviour
bash tests/bin/run-all.sh js           # Vitest over the admin request layer
```

The harness fails closed: it forces the Simulator provider, blocks outbound
HTTP, and aborts the run if the real `fsmpt_email_logs` row count changes.
Running the tests cannot send an email or touch your log.

Read `tests/AGENT.md` before adding a case, and `tests/README.md` for the
optional coverage gate, the environment-axes runner, and prefix portability.

#### Building a release

```bash
./build.sh
```

This builds the frontend, installs Composer dependencies without dev packages,
produces `fluent-smtp.zip`, restores your dev dependencies, and then verifies
that no development files ended up in the archive.

