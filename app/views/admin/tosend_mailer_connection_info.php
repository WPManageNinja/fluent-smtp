<div class="fss_connection_info">
    <?php if($error): ?>
    <p style="color: red;" class="connection_info_error"><?php esc_html_e('Connection Error: ', 'fluent-smtp') ?><?php echo wp_kses_post($error); ?></p>
    <?php endif; ?>

    <table class="wp-list-table widefat striped">
        <tr>
            <th><?php esc_html_e('Connection Type', 'fluent-smtp') ?></th>
            <td>toSend</td>
        </tr>
        <?php if(isset($stats['monthly_email_limit'])): ?>
        <tr>
            <th><?php esc_html_e('Monthly Email Limit', 'fluent-smtp') ?></th>
            <td><?php echo (int) $stats['monthly_email_limit']; ?></td>
        </tr>
        <?php endif; ?>
        <?php if(isset($stats['emails_sent_last_24hrs'])): ?>
        <tr>
            <th><?php esc_html_e('Sent in last 24 hours', 'fluent-smtp') ?></th>
            <td><?php echo (int) $stats['emails_sent_last_24hrs']; ?></td>
        </tr>
        <?php endif; ?>
        <?php if(isset($stats['emails_usage_this_month'])): ?>
        <tr>
            <th><?php esc_html_e('Usage this month', 'fluent-smtp') ?></th>
            <td><?php echo (int) $stats['emails_usage_this_month']; ?></td>
        </tr>
        <?php endif; ?>

        <?php if(isset($stats['sending_left_this_month'])): ?>
            <tr>
                <th><?php esc_html_e('Sending left this month', 'fluent-smtp') ?></th>
                <td><?php echo (int) $stats['sending_left_this_month']; ?></td>
            </tr>
        <?php endif; ?>

        <tr>
            <th><?php esc_html_e('Sender Email', 'fluent-smtp') ?></th>
            <td><?php echo wp_kses_post($connection['sender_email']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Sender Name', 'fluent-smtp') ?></th>
            <td><?php echo esc_html($connection['sender_name']); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Force Sender Name', 'fluent-smtp') ?></th>
            <td><?php echo esc_html(ucfirst($connection['force_from_name'])); ?></td>
        </tr>
        <?php if(!empty($connection['valid_senders'])): ?>
        <tr>
            <th><?php esc_html_e('Valid Sending Emails', 'fluent-smtp') ?></th>
            <td>
                <ul>
                    <?php foreach($connection['valid_senders'] as $validSender): ?>
                        <li><?php echo esc_html($validSender); ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
        <?php endif; ?>
    </table>
</div>
