=== FluentSMTP – WP SMTP Plugin with Amazon SES, SendGrid, Mailgun, Postmark, Cloudflare, toSend, Gmail and Any SMTP ===
Contributors: techjewel, wpmanageninja, heera, adreastrian
Tags: smtp, wordpress mail smtp, amazon ses, sendgrid, mailgun
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 2.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Free WP Mail SMTP plugin - fix WordPress email deliverability with Gmail, Amazon SES, SendGrid, Mailgun, Cloudflare, Postmark, Brevo and any SMTP.

== Description ==

###  WordPress Mail SMTP Plugin For Any Email Service Provider
Are your WordPress emails not sending, landing in spam, or failing silently? FluentSMTP is a **WP Mail SMTP plugin** that fixes deliverability by routing `wp_mail()` through the email service you choose — **Gmail, Amazon SES, SendGrid, Mailgun, Cloudflare Email, toSend, Postmark, Brevo (Sendinblue), SparkPost, Outlook / Office 365, Zoho**, or any SMTP host.

FluentSMTP talks to each provider's own API rather than only SMTP, so your transactional and marketing email goes out quickly and lands in the inbox. Set your sender, turn on logging, add failure alerts, and route different senders to different providers — all from one screen.

Connect as many email services as you want, and FluentSMTP routes each email to the right one based on its From address.

[youtube https://www.youtube.com/watch?v=qnrTdQMNcuA]

== 💚 100% Free Forever — No Pro Version, No Upsells, No Paywalls 💚 ==
**FluentSMTP is 100% free and open source — and it always will be.** There is no "pro" version, no premium add-ons, no locked features, no email capture wall, no nagging upgrade prompts, no feature limits, and no paid tier. Every integration listed above — Amazon SES, Gmail, Outlook, SendGrid, Mailgun, Cloudflare, toSend, Postmark, Brevo, SparkPost, and the rest — is fully available at no cost.

You will never have to pay a cent to use any feature of FluentSMTP. We have pledged this as part of our "Five for the Future" participation, an initiative started by the WordPress Foundation.

Our parent company <a title="WP Manage Ninja" href="https://wpmanageninja.com">WPManageNinja LLC</a> builds commercial products for WordPress businesses and runs a stable, profitable business on those — which means FluentSMTP is our way of giving back to the WordPress community, not a funnel. 👉 <a href="https://fluentsmtp.com/articles/why-we-built-fluentsmtp-plugin/">Read why it's 100% free (always)</a> 👈

== 🎉 Available Email Service Connections ==
* Amazon SES
* Gmail OAuth
* Google Workspace OAuth
* Outlook / Office 365 OAuth
* SendGrid
* Mailgun
* Cloudflare Email (new)
* toSend
* Brevo (Sendinblue)
* Netcore (Pepipost)
* Postmark
* SparkPost
* SMTP2GO
* Elastic Email
* Zoho via SMTP
* Any SMTP email provider
* More native integrations coming soon

== 🎉 FluentSMTP features ==
FluentSMTP is built for speed, reliability and scale.

* Real-Time Email Delivery
* Email Routing to multiple email connections
* Connect with Any Email Service Providers
* Fallback Email Connection
* Email Logging
* Resend Emails to any recipient
* Detailed Reporting
* Daily Connection Health Monitoring
* WP-CLI Support
* A fast Vue-based admin UI

Most importantly, this plugin is free and will always be free.
👉 <a href="https://fluentsmtp.com/articles/why-we-built-fluentsmtp-plugin/">Read why it's 100% free (always)</a> 👈

[youtube https://www.youtube.com/watch?v=GwmkX6zImWw]

== How does FluentSMTP work? ==
FluentSMTP intercepts <code>wp_mail</code> calls and hands them to the email service you connected, using that service's own API where one exists, and the host, port and credentials you gave it for a plain SMTP connection.

== Email Logging and Debugging ==
FluentSMTP can log the email your site sends, so you can check at any time what went out and what did not. Failed emails can be retried, and any logged email can be resent.

== 🎉 Amazon SES (Native API Connection) ==
The SES connection gives you Amazon's low-cost, high-deliverability infrastructure, set up in a couple of minutes. It uses Amazon's current SES API.

The SES client reuses its cURL connection across sends, so a burst of emails does not pay for a new handshake each time.

== 🎉 Gmail or Google Workspace (Native API Connection) ==
Connect your Gmail or Google Workspace account and send over Google's API rather than SMTP.
[youtube https://www.youtube.com/watch?v=_d78bscNaX8]

== 🎉 SendGrid API Connection ==
SendGrid runs a globally distributed sending platform, and the API connection takes about a minute to set up.

Read about <a href="https://fluentsmtp.com/docs/set-up-the-sendgrid-driver-in-fluent-smtp/">SendGrid connection documentation here</a>

== 🎉 Mailgun Email API Connection ==
Mailgun is another leading email sending service provider and trusted by 225,000+ businesses. You can rely on their globally distributed, cloud-based architecture for sending your WordPress Emails.

Get your message to the right person at the right time with global infrastructure and industry expertise you can rely on.

The Mailgun connection takes about a minute to set up, and it uses their API rather than SMTP.

Read about <a href="https://fluentsmtp.com/docs/configure-mailgun-in-fluent-smtp-to-send-emails/">Mailgun connection documentation here</a>

== 🎉 Brevo (formerly Sendinblue) API Connection ==
Brevo is a platform for growing businesses and it has a great transactional email service. They serve more than 80,000 companies around the world and send millions of emails every day.

If you use Brevo, FluentSMTP connects to its API and sends your WordPress email through it.

Read about <a href="https://fluentsmtp.com/docs/setting-up-sendinblue-mailer-in-fluent-smtp/">Brevo connection documentation here</a>

== 🎉 Netcore (formerly Pepipost) Email API Connection ==
Netcore is a complete sending partner with a user-friendly dashboard and many extensive functions such as statistics and real-time information.

The Netcore connection takes about a minute to set up, and it uses their API rather than SMTP.

Read about <a href="https://fluentsmtp.com/docs/set-up-the-pepipost-mailer-in-fluent-smtp/">Netcore API connection documentation here</a>

== 🎉 SparkPost Email API Connection ==
SparkPost is a great email sending service with lots of analytics features.
The SparkPost connection takes about a minute to set up.

Read about <a href="https://fluentsmtp.com/docs/configure-sparkpost-in-fluent-smtp-to-send-emails/">SparkPost connection documentation here</a>

== 🎉 Postmark API Connection ==
Postmark is a highly reliable transactional email service, known for fast delivery and top-tier inbox placement. FluentSMTP connects to Postmark's Server API directly — paste your Server Token, pick a Message Stream, and your transactional email goes through Postmark, with attachments, CC/BCC, Reply-To and custom headers all carried across.

Read about <a href="https://fluentsmtp.com/docs/configure-postmark-in-fluent-smtp-to-send-emails/">Postmark connection documentation here</a>

== 🎉 Elastic Email API Connection ==
Elastic Email handles both transactional and marketing mail, with per-message statistics in its dashboard. FluentSMTP uses their official API.

== 🎉 Outlook or Office365 API Connection ==
Connect your Outlook or Office 365 account and send over Microsoft's API. The connection uses OAuth2, so no password is stored on your site.

Read the documentation for <a href="https://fluentsmtp.com/docs/setup-outlook-with-fluentsmtp/">connecting Office 365 Email with WordPress</a>

== 🎉 SMTP2GO Email API Connection ==
SMTP2GO handles both transactional and marketing mail, with per-message statistics in its dashboard. FluentSMTP uses their official API.

== 🎉 Cloudflare Email API Connection ==
Send WordPress emails through **Cloudflare Email Sending** using their native REST API. If your domain is already on Cloudflare, you can send transactional email directly from Cloudflare's edge network — fast, reliable, and with built-in SPF/DKIM/DMARC. FluentSMTP handles the full request shape, including attachments, CC/BCC, Reply-To, and custom headers. The API token is checked when you save it and again on the connection screen, so a bad token is reported before an email needs it.

== 🎉 toSend Email Sending Provider ==
**toSend** is a modern transactional email service with a simple API, per-domain analytics, and fast delivery. FluentSMTP ships a native toSend integration — paste your API key, set a verified From address, and you're sending in under a minute. Failed sends are logged with full payloads so you can resend or debug without leaving WordPress.
Read about <a href="https://tosend.com/docs/guide/wordpress/">toSend WordPress setup guide here</a>


== 🎉 Other SMTP ==
FluentSMTP works with any service that offers an SMTP connection, including Gmail, Yahoo, Microsoft Live, Zoho Mail and Yandex Mail.

You can set the following options:

* Specify an SMTP Host.
* Specify an SMTP Port.
* Choose the Encryption option.
* Choose to use SMTP authentication or not.
* Specify the SMTP username and password.
* That's it 💯

Read about <a href="https://fluentsmtp.com/docs/set-up-fluent-smtp-with-any-host-or-mailer/">SMTP connection documentation here</a>

== 🚀 A fast, modern admin 🚀 ==

* Built with VueJS as a Single-page Application.
* A lean interface that needs no learning curve.
* A dashboard with charts and stats showing how your email is doing.

== 🚀 Automatic Email Routing 🚀 ==
Add as many email connections as you want. FluentSMTP routes each email to the right one based on its <b>From address</b>.

Now, you can route your transactional emails with one connection and marketing emails with another connection.

== 🚀 Email Logs and Reporting 🚀 ==
Want to know how much mail your site is sending, and what it is? The log lists every email, with charts of the daily totals, and you can resend any of them at any time — useful for keeping a record, auditing what goes out, and debugging while you build.

You can turn logging off, in which case only failed emails are recorded. Logs live in their own database table, so your WordPress tables stay as they are.

== 🚀 Connection Health Monitoring 🚀 ==
An expired OAuth token or a removed API key stays invisible until the first email that needed it fails. FluentSMTP checks every connection once a day, shows failures on the dashboard, and notifies you over your configured notification channels the moment a connection starts failing. Only newly broken connections notify, so a known issue will not nag you every day.

== 🚀 WP-CLI Support 🚀 ==
Manage FluentSMTP from the terminal, which matters most when the emails you need are the ones getting you into the admin:

* <code>wp fluent-smtp test</code> - send a test email through any connection
* <code>wp fluent-smtp health</code> - check every connection
* <code>wp fluent-smtp stats</code> - sent and failed counts
* <code>wp fluent-smtp prune-logs</code> - clean up old email logs

== 🚀 Real-time Notifications on Email Failures via Telegram, Slack, Discord and Pushover 🚀 ==
Connect Telegram, Slack, Discord or Pushover — as many as you want at once — and FluentSMTP messages you there the moment an email fails to send, so you find out before your customers do.

== 🚀 Security 🚀 ==
FluentSMTP is built with security and scale in mind, and gives you several ways to keep your credentials and your sending safe.

* Ability to store your SMTP / API credentials in wp-config.php.
* Ability to auto-delete old email logs.
* FluentSMTP connects your email service providers directly via an API.

= 🚀Plain-Text Support with HTML Email on the fly 🚀=
FluentSMTP can convert your HTML email to plain text as it sends, and deliver both parts as a multipart message. This helps deliverability and your spam score. Turn it on in the settings.

== 👉 Credits 👈 ==
FluentSMTP is built by <a href="https://wpmanageninja.com">WPManageNinja LLC</a>, the team behind <a href="https://wordpress.org/plugins/fluentform">Fluent Forms</a>, <a href="https://wordpress.org/plugins/fluent-crm">FluentCRM</a> and <a href="https://wordpress.org/plugins/ninja-tables/">Ninja Tables</a>.

FluentSMTP is free and open source, and we will never release a pro version. That is not a feature gap: everything the plugin does is in the free version. We wrote <a href="https://fluentsmtp.com/articles/why-we-built-fluentsmtp-plugin/">an article about why we made it</a> and where we are taking it.

The full source code is on GitHub, and contributions are welcome.
👉 <a href="https://github.com/WPManageNinja/fluent-smtp">View on GitHub</a> 👈

= Compatible With.. =
* [Fluent Forms - The Fastest Form Builder Plugin](https://wordpress.org/plugins/fluentform/)
* [FluentCRM - Email Marketing Automation, Email Newsletter and CRM Plugin for WordPress](https://wordpress.org/plugins/fluent-crm/)
* [WooCommerce](https://wordpress.org/plugins/woocommerce/)
* [Elementor Forms](https://elementor.com/features/form-widget/)
* [Contact Form 7](https://wordpress.org/plugins/contact-form-7/)
* [Gravity Forms](http://www.gravityforms.com)
* [Contact Form by WPForms](https://wordpress.org/plugins/wpforms-lite/)
* [Forminator – Contact Form](https://wordpress.org/plugins/forminator/)
* [Ninja Forms Contact Form](https://wordpress.org/plugins/ninja-forms/)
* [Form Maker by 10Web](https://wordpress.org/plugins/form-maker/)
* [Formidable Form Builder](https://wordpress.org/plugins/formidable/)
* [GiveWP – Donation Plugin](https://wordpress.org/plugins/give/)
* [Fast Secure Contact Form](https://wordpress.org/plugins/si-contact-form/)
* [Visual Forms Builder](https://wordpress.org/plugins/visual-form-builder/)
* [Contact Form Builder](https://wordpress.org/plugins/contact-form-builder/)
* [PlanSo Forms](https://wordpress.org/plugins/planso-forms/)
* [FluentCRM](https://wordpress.org/plugins/fluent-crm)
* [SendPress Newsletters](https://wordpress.org/plugins/sendpress/)
* [WP HTML Mail](https://wordpress.org/plugins/wp-html-mail/)
* [WPForms Lite](https://wordpress.org/plugins/wpforms-lite/)
* [WP Forms Pro](https://wordpress.org/plugins/wpforms-lite/)
* [Email Templates](https://wordpress.org/plugins/email-templates/)
* .. and every other plugin that uses the WordPress API [wp_mail](https://codex.wordpress.org/Function_Reference/wp_mail) to send mail!

== Easy Migration from WP Mail SMTP by WPForms ==
Moving from <b>WP Mail SMTP by WPForms</b> takes a few seconds.

* Just install FluentSMTP plugin to your site.
* Go to Settings -> FluentSMTP.
* It will automatically show previous configuration from "WP Mail SMTP by WPForms".
* Click "Import From WP Mail SMTP" button and that's it.
* Disable "WP Mail SMTP by WPForms" and enjoy FluentSMTP.

== One Click Migration from Easy WP SMTP ==
Moving from <b>Easy WP SMTP</b> takes a few seconds.

* Just install FluentSMTP plugin to your site.
* Go to Settings -> FluentSMTP.
* It will automatically show previous configuration from "Easy WP SMTP".
* Click "Import From Easy WP SMTP" button and that's it.
* Disable "Easy WP SMTP" and enjoy FluentSMTP.

== What's Next ==
If you like this plugin, then consider checking out our other plugins:

<ul>
    <li><a href="https://wordpress.org/plugins/fluent-crm/" target="_blank">FluentCRM – Email Marketing Automation and CRM Plugin for WordPress</a></li>
	<li><a href="https://wordpress.org/plugins/fluentform/" target="_blank">Fluent Forms – Fastest WordPress Form Builder Plugin</a></li>
	<li><a href="https://wordpress.org/plugins/fluent-support/" target="_blank">WordPress Helpdesk and Customer Support Ticket Plugin</a></li>
	<li><a href="https://wordpress.org/plugins/ninja-tables/" target="_blank">Ninja Tables – Best WP DataTables Plugin for WordPress</a></li>
	<li><a href="https://wordpress.org/plugins/ninja-charts/" target="_blank">Ninja Charts – Best WP Charts Plugin for WordPress</a></li>
	<li><a href="https://wordpress.org/plugins/wp-payment-form/" target="_blank">Paymattic - Simple Payment Donations & Subscriptions Plugin</a></li>
</ul>


== Installation ==

1. Install FluentSMTP either via the WordPress.org plugin repository or by uploading the files to your server.
2. Activate FluentSMTP.
3. Navigate to the Settings area of FluentSMTP in the WordPress admin.
4. Choose your SMTP option (Mailgun SMTP, SendGrid SMTP, Amazon SES, or Other SMTP) and follow the instructions to set it up.
5. Need more help? Get support with <a href="https://wpmanageninja.com/support-tickets/" title="WPManageNinja">WPManageNinja Support</a>.

== Frequently Asked Questions ==
= Can I send email via SMTP from my WordPress site using this plugin? =

Yes. That is what the plugin is for: it takes over wp_mail() and sends through the service you connect.

 = Can I connect Amazon SES API with FluentSMTP? =

Yes. Add your SES access key and secret key, and FluentSMTP sends through the SES API rather than SMTP.

 = Can I store my Email Service Credentials to wp-config.php file? =

Yes. When you create a connection you choose where its credentials are kept: in the database, or in your wp-config.php file, which is what we recommend.

 = Can I send WordPress mails with SendGrid? =

 Yes. FluentSMTP connects to SendGrid over their API, which is faster than their SMTP endpoint. If you would rather use SMTP, you can set that up instead.

 = Can I send WordPress mails with Mailgun? =

 Yes. FluentSMTP connects to Mailgun with an API key, which is faster than their SMTP endpoint. If you would rather use SMTP, you can set that up instead.

 = Can I send WordPress mails with Sendinblue? =

 Yes. FluentSMTP connects to Brevo (formerly Sendinblue) with an API key. If you would rather use SMTP, you can set that up instead.

 = Can I send WordPress mails with SparkPost? =

 Yes. FluentSMTP connects to SparkPost with an API key.

= Can I send WordPress mails with Pepipost? =

Yes. FluentSMTP connects to Netcore (formerly Pepipost) with an API key.

= Can I send WordPress mails with Cloudflare Email? =

Yes. FluentSMTP ships a native Cloudflare Email Sending integration. Add your Cloudflare Account ID and an API token with Email Sending permissions, and the plugin will send through Cloudflare's REST API (including attachments, CC/BCC, Reply-To, and custom headers). The sending domain must be on Cloudflare with Email Sending enabled (SPF/DKIM/DMARC published).

= Can I send WordPress mails with toSend? =

Yes. FluentSMTP has a native toSend integration. Create an API key in the toSend dashboard, add your verified sending domain, paste the key into FluentSMTP, and your WordPress emails will be delivered through toSend.

= How do I know if one of my connections has stopped working? =

FluentSMTP checks every connection once a day. If one starts failing - an expired OAuth token, a revoked API key - it is flagged on the dashboard and sent to your configured notification channels (Telegram, Slack, Discord or Pushover). You can also run <code>wp fluent-smtp health</code> from the command line at any time.

= Can I use FluentSMTP from WP-CLI? =

Yes. <code>wp fluent-smtp test</code> sends a test email, <code>wp fluent-smtp health</code> checks your connections, <code>wp fluent-smtp stats</code> shows sent and failed counts, and <code>wp fluent-smtp prune-logs</code> deletes old logs.

= I am a developer, Where I can contribute to this project? =

Please check <a href="https://github.com/WPManageNinja/fluent-smtp">our GitHub repository</a>. Pull requests are welcome.

= I found a bug, where I can report? =

Please <a href="https://wpmanageninja.com/support-tickets/">submit an issue in our support portal</a>. If you are a developer please <a href="https://github.com/WPManageNinja/fluent-smtp">create a github issue</a>.

= I found a security issue, where can I report it? =
We use Patchstack to manage our security report. <a href="https://patchstack.com/database/vdp/fluent-smtp">Please report in the patchstack page</a>.

== Screenshots ==
1. FluentSMTP Dashboard
2. Setting up a connection
3. Settings Overview
4. Sending a test email
5. Email Logs
6. View Email from Log


== Changelog ==

= 2.4.0 (Date: Aug 31, 2026) =
- Requires WordPress 6.5 or newer, up from 5.5. The rebuilt admin needs the jQuery 3 that
  WordPress 5.5 did not ship, and the releases in between are no longer tested against
- Redesigned the whole admin on the shared Fluent design system, so moving between
  FluentSMTP, FluentCart and FluentAuth no longer feels like changing product
- Added a dark theme. The toggle is the same one FluentCart uses, so choosing dark in
  either plugin chooses it in both, and the screen paints in the right theme on first
  frame rather than flashing light first
- Reorganised the navigation around the five places you actually go: Dashboard, Settings,
  Email Logs, Alerts and About. Settings is the connections screen itself, one click from
  anywhere, rather than a sidebar of screens to pick from - and Send Test Email keeps its
  button in the bar
- Rebuilt the Settings screen around the question it is opened to answer. Each connection
  is a row showing its provider's logo, what it sends as, and whether it is the Default
  or the Fallback - and Default and Fallback are set from the row itself rather than from
  two dropdowns further down the page. A connection that needs attention says so in
  words. Edit is a button on the row, and General Settings is beside the list rather than
  below it
- Rebuilt the Alerts screen: Telegram, Slack, Discord and Pushover are rows showing
  whether each is connected and whether failures go to it, setting one up opens in place
  where there is room for it, and the weekly summary email sits beside them
- Added Alerts & Notifications to the dashboard as well, next to the failed count that is
  the reason to set them up, showing which channels are on
- Every existing link into the admin still works. The addresses in Slack, Telegram and
  Discord alerts, in the admin bar and in Slack's sign-in flow are unchanged
- Improved the dashboard: it opens by greeting you and naming the site, and sent, failed,
  connections and senders are four figures across the top of the page
- Fixed the failed count on the dashboard opening the email log with no filter on it, so
  it now shows the failed emails it was counting
- Fixed Prev and Next in the log viewer walking every email rather than the filtered list
  you opened one from
- Added a Recent Activity panel to the dashboard, showing the last emails sent with a
  filter for today, yesterday and this week
- Improved the email log: a failed email is marked with a word rather than by painting the
  whole row, which leaves the rest of the row free to say something
- Rebuilt the admin on Vue 3, Element Plus and Vite. Faster to load and no longer built on
  a framework that stopped receiving updates
- Improved the email test screen: it says what it is like every other screen, and the
  form is a column rather than a page-wide row
- Improved the email log rows: Resend, View and Delete are quiet buttons rather than
  three solid blocks of colour per row, so the only colour left in the table is the one
  that means something - a failed status
- Added a way back to Settings from the add and edit connection screens
- Improved the subscribe card on the dashboard: the two fields have their labels above
  them and take the full width of the column, instead of spending a quarter of a 380px
  card on right-aligned labels
- Improved the email log's toolbar: the statuses sit on the left and the date range,
  search and refresh on the right, all in the table's own header. Search and refresh used
  to be up in the page heading, a card away from the other controls that narrow the same
  list
- Improved the controls in a page's heading row: bulk action, search, refresh and Add
  Another Connection are all one standard height now, where the search box used to stand
  taller than the three controls beside it
- Fixed filtering the email log while on a later page asking for that page of the new,
  shorter result - an empty table for a filter that matches plenty
- Fixed the bulk action dropdown on the email log rendering about 60px wide, showing
  "B." where "Bulk Action" should be
- Improved the email log table: a long subject or recipient ends in an ellipsis with the
  rest a hover away, so every row is one line tall instead of four, and Subject is the
  only column that takes the leftover width - To, Status, Date-Time and Actions are all
  narrower than they were
- Improved the email log on a phone: Subject has a minimum width, so the table scrolls
  sideways instead of squeezing it to 80px and wrapping a subject one word per line
- Fixed the pagination on the email log running off the edge of the screen on a phone,
  where the page buttons were clipped rather than reachable
- Fixed buttons that carry an icon beside their label - Send Test Message, Disconnect,
  Try Again, Back to Alerts, Prev and Next - printing the icon hard against the first
  letter, with no space between them
- Improved the text fields on the alert setup forms and in the PostMark and toSend
  connection settings, which stood a row shorter than every other field in the admin
- Fixed every delete confirmation opening on hover and not at all on click. Element UI's
  popover opened on click and Element Plus's opens on hover, so Delete All Logs and the
  delete on each row, connection and sender armed themselves when the pointer crossed
  them and did nothing when pressed
- Fixed API key and password fields on every connection form neither showing a saved
  value nor keeping a typed one, from a component still using Vue 2's v-model contract
- Fixed the API key field's label sitting out in the margin beside it rather than above
  it, on toSend, SendGrid, Brevo, SparkPost, Netcore, Postmark, ElasticEmail, SMTP2GO and
  Cloudflare
- Fixed the "keys in the config file" snippet on all fourteen providers that offer it:
  the box had collapsed to a couple of hundred pixels beside its own instruction, showing
  three words of the define() at a time. It is a full-width code block now
- Rebuilt the add and edit connection screen. The heading runs the full width and carries
  what the connection is - its title on the left, the provider's logo and the button to
  change it on the right - and the form below is a centred column rather than a page.
  Two fields side by side at 1400px read as unrelated, and a port number does not want
  eight hundred pixels to sit in
- Improved the connection wizard: the provider you picked shows as its logo on a plate
  with the button to change it beside, instead of a logo, a stray black tick and a button
  stacked down three lines
- Improved the Amazon SES and SMTP forms, which were the two of fifteen with no heading
  over their settings
- Improved the Gmail and Office365 forms: authenticating is the step that finishes them,
  so its button is the primary one rather than the red used for deleting a connection
- Improved a provider's caveat note, which was set larger than the form's own labels
- Fixed the About cards drawing rounded corners on the top of their body, which put two
  notches in the seam under the header
- Fixed the delete-logs help text showing "delete_logs_info" instead of an explanation
- Fixed the keyboard focus ring being left behind on buttons and links after a click
- Fixed text fields and checkboxes picking up WordPress 7.1's own form styling, which drew
  a second bordered box inside every input and left the field standing a row too tall

**For developers**

`window.FluentMail.Vue` was the Vue 2 constructor. Vue 3 has no constructor to extend - an
app is created by `createApp()` - so it cannot be preserved as one, and there is no shim
that could honestly pretend otherwise. It is now the Vue 3 module namespace
(`{ createApp, ref, computed, h, ... }`), and `window.FluentMail.Router` is vue-router's.
The `applyFilters`, `addFilter`, `addAction`, `doAction`, `registerTopMenu()`,
`registerBlock()`, `$get`, `$post` and `appVars` members are all still there, as are the
`fluent_mail_top_menus`, `fluent_mail_global_routes` and `fluent_mail_loading_app` hooks.
Three things about them did change, and an add-on that renders its own screen is likely
to need updating:

- `window.FluentMail.Vue` and `.Router` are published by the app bundle in the footer,
  which runs *after* `fluent_mail_loading_app`. Code hooked there cannot read them yet.
- Element components are no longer registered globally, so a component supplied at
  runtime through `registerTopMenu()` has to import the ones it uses itself.
- The `fluentmail-chartjs` and `fluentmail-vue-chartjs` script handles are gone, along
  with the `window.Chart` and `window.VueChartJs` globals they defined. The dashboard
  charts are Apache ECharts now, bundled privately.

`$get` and `$post` also reject rather than resolve when the nonce has expired or the
capability check fails - those responses now carry HTTP 403 instead of 200, so handle
them in `.fail()` rather than in `.then()`.

An add-on that reads `window.FluentMail.Vue` as a constructor, or renders Element
components it never imported, will need updating. One that only uses the filters, or
registers a screen through `registerTopMenu()` with components it imports itself, will not.

= 2.3.1 (Date: Aug 13, 2026) =
- Added an optional Directory (tenant) ID for Outlook / Office 365, for single-tenant Entra app registrations that cannot accept personal Microsoft accounts
- Fixed "Could not instantiate mail function." on the PHP mail() connection when a host or plugin selects its own transport from the <code>phpmailer_init</code> hook
- Fixed mail routed outside FluentSMTP being sent through the site's own SMTP relay when a bulk sending session held a connection open

= 2.3.0 (Date: Aug 05, 2026) =
- Added Cloudflare Email Sending Provider
- Added Recipient Picker and Resend History for Email Logs (props @faisalahammad)
- Added Daily Connection Health Check with dashboard alerts and failure notifications
- Added WP-CLI commands for test email, connection health, stats and log pruning
- Added Amazon SES EU Sovereign Cloud region (Germany, Brandenburg)
- Added send time tracking on every email log
- Added <code>fluent_mail/manage_capability</code> filter to change the required admin capability
- Added inline image embedding for SMTP connections via the <code>wp_mail_embed_args</code> filter
- Added inline setup guides for Cloudflare and toSend
- Improved sending speed with connection reuse for Amazon SES, toSend and FluentCRM bulk sending
- Improved Email Log performance with a new database index and batched pruning
- Updated the <code>wp_mail()</code> replacement to match WordPress 7.0 core
- Fix: List-Unsubscribe one-click headers were encoded and ignored by mailbox providers on Amazon SES, Gmail and Outlook (props @rogerjudd)
- Fix: Ampersands in the site title showing as HTML entities in the From Name, subject and email logs (props @ikamal7)
- Fix: Outlook failures reporting a generic "Unauthorized" instead of the reason Microsoft gave (props @reikjarloekl)
- Fix: From Name losing its last character when written without a space before the angle bracket
- Fix: Weekly and monthly reports merging data across different years
- Fix: toSend Reply-To formatting and sender validation messages
- Fix: Nested array sanitization in email logs
- Security hardening across the plugin, plus UI and translation improvements

= 2.2.95 (Date: Dec 28, 2025) =
- Added Multiple Notification Channels for Email Failure Notification
- Added Pushover Notification Support
- Added toSend Email Sending Provider
- Added Option to disable API Keys Encryption
- Fixed PHP 8.4 Compatibility Issues

= 2.2.92 (Date: Aug 27, 2025) =
- Fixed attachment handling issue with Elastic Email.
- Resolved import statement issue for SMTP2GO.
- Added PHP 8.4 support for FluentMail\App\Services\Mailer\Manager.
- Added new Amazon SES region: ap-northeast-3 (Asia Pacific – Osaka).
- Improved error handling in BaseHandler.
- Updated fallback email handling to return true on success.
- General bug fixes and performance improvements.
- Fix: Logger Resend Email respects Content-Type for HTML emails
- Styling Improvements
- Fix: Prevent redundant navigation error in Logs screen when refreshing
- Fix: Ensure Content-Type header is always logged for accurate email resends

= 2.2.90 (Date: Feb 07, 2025) =
- Added SMTP2GO Provider
- Improved Translations
- Added name attribute to attachment files
- Security: Updated Google SDK Library to the latest version & updated JS DomPurify Library
- Fixed: Email Failed Notification Issue with Slack
- Styling Improvements

= 2.2.83 (Date: Nov 22, 2024) =
- Fix unserialize parameter issue

= 2.2.82 (Date: Nov 22, 2024) =
- Security: Data Un-serialization issue fixed
- Sparkpost Recipient Issue fixed

= 2.2.81 (Date: Oct 20, 2024) =
* Security: Nonce Verification fixed for slack REQUEST (props to patchstack)
* Fixed WooCommerce Emailing Issue fixed when enabled text mode
* Fixed Translation issues
* Custom Header support for Postmark

= 2.2.80 (Date: July 02, 2024) =
* Added Plain Text Support: Convert HTML Emails to Plain Text and send as multi-part email
* Improved Translations
* Improved Internal Code Base

= 2.2.73 (Date: Apr 25, 2024) =
* Compatibility with PHP 8.X
* Added Day of the time sending chart

= 2.2.72 (Date: Mar 16, 2024) =
* Compatibility with PHP 8.4
* Fix Slack Notification Issue

= 2.2.71 (Date: Jan 01, 2024 =
* Hot Fix: Fixing the issue with Input Fields

= 2.2.7 (Date: Jan 01, 2024) =
* Added RealTime Email Failure Notification via Telegram / Slack / Discord
* Added Option to add additional email addresses for Amazon SES
* UI Improvements

= 2.2.6 (Date: Oct 01, 2023) =
* Enable Encryption for All SMTP Connections Keys
* Migrate SendInBlue API to Brevo API
* Improved Plugin Conflict Detection and auto fix
* Fixed UI conflict with Other Plugins

= 2.2.5 (Date: Jul 06, 2023) =
* (Security Fix) Email subject is now sanitized and escaped when preview
* Showing Server Response by default on log
* Fix http_build_query issue for latest version of PHP
* Improved UI & UX for email preview

= 2.2.4 (Date: Feb 04, 2023) =
* Email preview is now sanitized
* you can now define `FLUENTMAIL_SIMULATE_EMAILS` to simulate emails programmatically
* Fixed outlook API connection issues
* Fixed inline documentation links
* UX improvements

= 2.2.2 (Date: Nov 11, 2022) =
* Fix vendor Conflict for Google/Gmail Connection
* UI Improvement on Connection Wizard

= 2.2.1 (Date: Nov 08, 2022) =
* Refactored Google API integration
* Fix encoding issues for Outlook API connection
* ElasticEmail Attachment issues fixed
* Fixed digest email esc_* issues
* Added contributors to the plugin's about page.
* UI&UX Improvements

= 2.2.0 (Date: Aug 21, 2022) =
* Added Elastic Mail API
* PHP 8.0 & 8.1 compatibility
* UI Improvements

= 2.1.2 (Date: July 05, 2022) =
* Google/Gmail API Upgrade
* UI Improvements

= 2.1.1 (Date: March 12, 2022) =
* Improved Email Logging Screen
* Improved UI and Settings
* Fixed auto-delete old email logs

= 2.1.0 (Date: October 24, 2021) =
* Fix Cron Issues
* PHP 8.0 Compatibility issue fixed
* Multiple Connection UX improvement
* Ability to remove from email and name hook via filter

= 2.0.2 (Date: September 21, 2021) =
* Fixed Scheduled Database Cleanup
* Improvement on wp_mail loading and sending emails
* Pepipost Driver Improvement
* SendGrid Driver Improvement
* SendinBlue Drive Improvement

= 2.0.1 (Date: July 28, 2021) =
* Added Postmark API Connection
* Fix Dashboard Stat Number
* Fix Sanitization Issue

= 2.0.0 (Date: July 27, 2021) =
* Added Outlook / Office 365 API Connection
* Improvements of Amazon SES Connection
* Ability to disable force From Email for supported connections
* Added Fallback Connection feature
* Added One-Click migration from WP Mail SMTP Plugin
* Added One-Click migration from WP Easy SMTP Plugin
* UI Improvements
* Added nonce and sanitization for connection inputs

= 1.2.0 (Date: May 26, 2021) =
* Added Gmail and Google Workspace API Connection
* Added Built-in Docs
* UI Improvements
* PHP 8 compatibility issue fixed
* Bulk Send Emails from logs
* Added Email Simulator
* Amazon API Fix

= 1.1.1 (Date: April 26, 2021) =
* Database Warning Issue Fixed

= 1.1.0 (Date: April 25, 2021) =
* Fix Error Handling Issues
* DataBase Query Optimizations
* Amazon SES Connection Optimization
* UI Improvement
* VueJS loading improvements

= 1.0.1 (Date: January 24, 2021) =
* Fix UTF-8 issues
* Sendinblue wp-config constant issue fixed
* Fallback from name issue fixed
* Search for Email Logs has been fixed

= 1.0.0 (Date: January 18, 2021) =
* Initial Launch
* 349 git commits so far
* 698 cup of coffee (Just kidding, We lost count)
* Work of 3 Months
* Let's Make Email Sending Easier!

== Upgrade Notice ==
The latest Version is compatible with previous version, So nothing to worry
