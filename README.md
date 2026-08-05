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

#### Contribute
FluentSMTP is built with VueJS and ElementUI (frontend). It's backend communication is based on standard WordPress AJAX endpoints.

All endpoints can be found in `app/Http/routes.php`.

All the email connection drivers can be found in `app/Services/Mailer/Providers`.

**Thanks to our contributors**

![GitHub Contributors Image](https://contrib.rocks/image?repo=WPManageNinja/fluent-smtp)

#### Getting Started
- Clone this repository.
- Run `composer install` to install PHP dependencies.
- Run `npm install` to install the frontend dependencies.

#### Build JavaScript source

- Run `npm run start` (or `npx mix watch`) for development.
- Run `npm run prod` (or `npx mix --production`) to build for production.

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

