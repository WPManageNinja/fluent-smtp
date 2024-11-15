<div class="fss_connection_info">

    <?php if($error): ?>
    <p style="color: red;" class="connection_info_error"><?php esc_html_e('Connection Error: ', 'fluent-smtp') ?><?php echo wp_kses_post($error); ?></p>
    <?php endif; ?>

    <table class="wp-list-table widefat striped">
        <tr>
            <th><?php esc_html_e('Connection Type', 'fluent-smtp') ?></th>
            <td>Amazon SES</td>
        </tr>
        <?php if(isset($stats['Max24HourSend'])): ?>
        <tr>
            <th><?php esc_html_e('Max Send in 24 hours', 'fluent-smtp') ?></th>
            <td><?php echo (int) $stats['Max24HourSend']; ?></td>
        </tr>
        <?php endif; ?>
        <?php if(isset($stats['SentLast24Hours'])): ?>
        <tr>
            <th><?php esc_html_e('Sent in last 24 hours', 'fluent-smtp') ?></th>
            <td><?php echo (int) $stats['SentLast24Hours']; ?></td>
        </tr>
        <?php endif; ?>
        <?php if(isset($stats['MaxSendRate'])): ?>
        <tr>
            <th><?php esc_html_e('Max Sending Rate', 'fluent-smtp') ?></th>
            <td><?php echo (int) $stats['MaxSendRate']; ?>/sec</td>
        </tr>
        <?php endif; ?>
        <tr>
            <th><?php esc_html_e('Sender Email', 'fluent-smtp') ?></th>
            <td><?php echo $connection['sender_email']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Sender Name', 'fluent-smtp') ?></th>
            <td><?php echo esc_html($connection['sender_name']); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Force Sender Name', 'fluent-smtp') ?></th>
            <td><?php echo esc_html(ucfirst($connection['force_from_name'])); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Valid Sending Emails', 'fluent-smtp') ?></th>
            <td>
                <ul>
                    <?php foreach ($valid_senders as $sender): ?>
                        <li><?php echo esc_html($sender); ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
    </table>

    <?php if(!$error && empty($stats['Max24HourSend'])): ?>
        <p style="color: red;" class="connection_info_error">
            <?php esc_html_e('Looks like you are in sandbox mode. Please apply to Amazon AWS to approve your account. ', 'fluent-smtp') ?><a href="https://fluentcrm.com/set-up-amazon-ses-with-fluentcrm/#4-moving-out-of-sandbox-mode" target="_blank" rel="nofollow"><?php esc_html_e('Read More here.', 'fluent-smtp') ?></a>
        </p>
    <?php endif; ?>
    <p><a href="https://aws.amazon.com/ses/extendedaccessrequest/" target="_blank" rel="nofollow"><?php esc_html_e('Increase Sending Limits', 'fluent-smtp') ?></a></p>
</div>
