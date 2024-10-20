<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width">
    <title><?php esc_html_e('FluentSMTP Email Health Report', 'fluent-smtp') ?></title>
    <style type="text/css">@media only screen and (max-width: 599px) {
            table.body .container {
                width: 95% !important;
            }

            .header {
                padding: 15px 15px 12px 15px !important;
            }

            .header img {
                width: 200px !important;
                height: auto !important;
            }

            .content, .aside {
                padding: 30px 40px 20px 40px !important;
            }
        }</style>

    <style type="text/css">
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 14px;
            background: #eeeeee;
        }

        /* GENERAL STYLE RESETS */
        body, #bodyTable {
            height: 100% !important;
            width: 100% !important;
            margin: 0;
            padding: 0;
        }

        img, a img {
            border: 0;
            outline: none;
            text-decoration: none;
        }

        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
            border-collapse: collapse;
        }

        img {
            -ms-interpolation-mode: bicubic;
        }

        body, table, td, p, a, li, blockquote {
            -ms-text-size-adjust: 100%;
            -webkit-text-size-adjust: 100%;
        }
    </style>

</head>
<body
    style="height: 100% !important; width: 100% !important; min-width: 100%; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; -webkit-font-smoothing: antialiased !important; -moz-osx-font-smoothing: grayscale !important; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; background-color: #f1f1f1; text-align: center;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%" class="body"
       style="border-collapse: collapse; border-spacing: 0; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; height: 100% !important; width: 100% !important; min-width: 100%; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; -webkit-font-smoothing: antialiased !important; -moz-osx-font-smoothing: grayscale !important; background-color: #f1f1f1; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; text-align: left; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%;">
    <tr style="padding: 0; vertical-align: top; text-align: left;">
        <td align="center" valign="top" class="body-inner fluent-smtp"
            style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; padding: 0; margin: 0; Margin: 0; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; text-align: center;">
            <!-- Container -->
            <table border="0" cellpadding="0" cellspacing="0" class="container"
                   style="border-collapse: collapse; border-spacing: 0; padding: 0; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; width: 600px; margin: 0 auto 30px auto; Margin: 0 auto 30px auto; text-align: inherit;">
                <!-- Header -->
                <tr style="padding: 0; vertical-align: top; text-align: left;">
                    <td valign="middle" class="header"
                        style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: white; border: 1px solid #7D3492; background: #7D3492; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; margin: 0; Margin: 0; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; text-align: left; padding: 10px 20px 10px 20px;">
                        <table width="100%">
                            <tr>
                                <td>
                                    <h3 style="margin: 5px 0; color: white;"><?php esc_html_e('Email Sending Health', 'fluent-smtp') ?></h3>
                                    <p style="margin: 0;color: white;font-size: 12px;"><?php echo esc_html($date_range); ?></p>
                                </td>
                                <td style="text-align: right;">
                                    <img src="<?php echo esc_url(fluentMailMix('images/fluentsmtp-white.png')); ?>"
                                         width="164px" alt="Fluent SMTP Logo"
                                         style="outline: none; text-decoration: none; max-width: 100%; clear: both; -ms-interpolation-mode: bicubic; display: inline-block !important; width: 164px;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <!-- Content -->
                <tr style="padding: 0; vertical-align: top; text-align: left;">
                    <td align="left" valign="top" class="content"
                        style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; margin: 0; Margin: 0; text-align: left; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; background-color: #ffffff; padding: 20px 20px 30px 20px; border-right: 1px solid #ddd; border-left: 1px solid #ddd;">
                        <table width="100%">
                            <tr>
                                <td>
                                    <h3 style="font-size: 18px; font-weight: normal; margin: 0;"><?php echo wp_kses_post($sent['title']); ?></h3>
                                    <?php if ($sent['subject_items']): ?>
                                        <p style="margin: 4px 0 0 0;font-size: 12px;"><?php echo wp_kses_post($sent['subtitle']); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px 0">
                                    <?php if ($sent['subject_items']): ?>
                                        <table width="100%"
                                               style="border: 1px solid #ccd0d4; border-collapse: collapse;">
                                            <tr style="background-color: #f9f9f9;">
                                                <th style="padding: 8px 10px;"
                                                    align="left"><?php esc_html_e('Subject', 'fluent-smtp'); ?></th>
                                                <th style="width:85px; padding: 8px 10px;"><?php esc_html_e('Emails Sent', 'fluent-smtp'); ?></th>
                                            </tr>
                                            <?php foreach ($sent['subject_items'] as $index => $item): ?>
                                                <tr <?php if($index % 2 == 1) { echo 'style="background-color: #f9f9f9;"'; }?>>
                                                    <td style="padding: 8px 10px;"><?php echo wp_kses_post($item['subject']); ?></td>
                                                    <td style="padding: 8px 10px;"
                                                        align="center"><?php echo esc_html(number_format_i18n($item['emails_sent'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    <?php else: ?>
                                        <table width="100%" cellpadding="20">
                                            <tr>
                                                <td bgcolor="#eeeeee"
                                                    style="background-color: #eeeeee;border-radius: 5px;"
                                                    align="center">
                                                    <h3 style="font-size: 24px; line-height: 30px; font-weight: normal;"><?php esc_html_e('Looks like no email has been sent to the time period', 'fluent-smtp'); ?></h3>
                                                    <p>
                                                        <?php esc_html_e('If this is unusual you should probably check if your site is broken.', 'fluent-smtp');
                                                        ?>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Fails -->
                <tr style="padding: 0; vertical-align: top; text-align: left;">
                    <td align="left" valign="top" class="content"
                        style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: #444; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; margin: 0; Margin: 0; text-align: left; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; background-color: #ffffff; padding: 20px 20px 30px 20px; border-right: 1px solid #ddd; border-bottom: 1px solid #ddd; border-left: 1px solid #ddd;">
                        <table width="100%">
                            <tr>
                                <td>
                                    <h3 style="font-size: 18px; font-weight: normal; margin: 0;"><?php echo wp_kses_post($fail['title']); ?></h3>
                                    <?php if ($fail['subject_items']): ?>
                                        <p style="margin: 4px 0 0 0;"><?php echo wp_kses_post($fail['subtitle']); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 10px 0">
                                    <?php if ($fail['subject_items']): ?>
                                        <table width="100%" style="border: 1px solid #ccd0d4; border-collapse: collapse;">
                                            <tr style="background-color: #f9f9f9;">
                                                <th style="padding: 8px 10px;" align="left"><?php esc_html_e('Subject', 'fluent-smtp'); ?></th>
                                                <th style="width:85px; padding: 8px 10px;"><?php esc_html_e('Failed Count', 'fluent-smtp'); ?></th>
                                            </tr>
                                            <?php foreach ($fail['subject_items'] as $index => $item): ?>
                                                <tr <?php if($index % 2 == 1) { echo 'style="background-color: #f9f9f9;"'; }?>>
                                                    <td style="padding: 8px 10px;"><?php echo wp_kses_post($item['subject']); ?></td>
                                                    <td style="padding: 8px 10px;"
                                                        align="center"><?php echo esc_html(number_format_i18n($item['emails_sent'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    <?php else: ?>
                                        <table width="100%" cellpadding="20">
                                            <tr>
                                                <td bgcolor="#eeeeee"
                                                    style="background-color: #eeeeee;border-radius: 5px;" align="center">
                                                    <h3 style="font-size: 24px; line-height: 30px; font-weight: normal;"><?php esc_html_e('Awesome! no failures! 🎉', 'fluent-smtp'); ?></h3>
                                                    <p>
                                                        <?php esc_html_e('Your email sending health is perfect', 'fluent-smtp');
                                                        ?>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <!-- Footer -->
                <tr style="padding: 0; vertical-align: top; text-align: left;">
                    <td valign="middle" class="header" style="word-wrap: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; mso-table-lspace: 0pt; mso-table-rspace: 0pt; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; color: white; border: 1px solid #7D3492; background: #7D3492; font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif; font-weight: normal; margin: 0; Margin: 0; font-size: 14px; mso-line-height-rule: exactly; line-height: 140%; text-align: left; padding: 10px 20px 10px 20px;">
                        <table width="100%">
                            <tr>
                                <td>
                                    <p style="font-size: 10px; line-height: 12px; color: white;"><?php esc_html_e('You received this email because the Email Sending Health Report is enabled in your FluentSMTP settings. Simply turn it off to stop these emails at ', 'fluent-smtp') ?><?php echo esc_html($domain_name); ?>.</p>
                                </td>
                                <td style="text-align: right;width: 100px; padding-left: 15px;">
                                    <img src="<?php echo esc_url(fluentMailMix('images/fluentsmtp-white.png')); ?>"
                                         width="164px" alt="Fluent SMTP Logo"
                                         style="outline: none; text-decoration: none; max-width: 100%; clear: both; -ms-interpolation-mode: bicubic; display: inline-block !important; width: 164px;">
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
