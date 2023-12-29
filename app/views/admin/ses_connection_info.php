<div class="fss_connection_info">

    <?php if($error): ?>
    <p style="color: red;" class="connection_info_error">Connection Error: <?php echo wp_kses_post($error); ?></p>
    <?php endif; ?>

    <table class="wp-list-table widefat striped">
        <tr>
            <th>Connection Type</th>
            <td>Amazon SES</td>
        </tr>
        <?php if(isset($stats['Max24HourSend'])): ?>
        <tr>
            <th>Max Send in 24 hours</th>
            <td><?php echo (int) $stats['Max24HourSend']; ?></td>
        </tr>
        <?php endif; ?>
        <?php if(isset($stats['SentLast24Hours'])): ?>
        <tr>
            <th>Sent in last 24 hours</th>
            <td><?php echo (int) $stats['SentLast24Hours']; ?></td>
        </tr>
        <?php endif; ?>
        <?php if(isset($stats['MaxSendRate'])): ?>
        <tr>
            <th>Max Sending Rate</th>
            <td><?php echo (int) $stats['MaxSendRate']; ?>/sec</td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>Sender Email</th>
            <td><?php echo $connection['sender_email']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
        </tr>
        <tr>
            <th>Sender Name</th>
            <td><?php echo esc_html($connection['sender_name']); ?></td>
        </tr>
        <tr>
            <th>Force Sender Name</th>
            <td><?php echo ucfirst($connection['force_from_name']); ?></td>
        </tr>
        <tr>
            <th>Valid Sending Emails</th>
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
            Looks like you are in sandbox mode. Please apply to Amazon AWS to approve your account. <a href="https://fluentcrm.com/set-up-amazon-ses-with-fluentcrm/#4-moving-out-of-sandbox-mode" target="_blank" rel="nofollow">Read More here.</a>
        </p>
    <?php endif; ?>
    <p><a href="https://aws.amazon.com/ses/extendedaccessrequest/" target="_blank" rel="nofollow">Increase Sending Limits</a></p>
</div>
