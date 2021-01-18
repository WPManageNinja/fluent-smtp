<div class="fss_connection_info">
    <table class="wp-list-table widefat striped">
        <tr>
            <th>Connection Type</th>
            <td>Amazon SES</td>
        </tr>
        <tr>
            <th>Max Send in 24 hours</th>
            <td><?php echo intval($stats['Max24HourSend']); ?></td>
        </tr>
        <tr>
            <th>Sent in last 24 hours</th>
            <td><?php echo intval($stats['SentLast24Hours']); ?></td>
        </tr>
        <tr>
            <th>Max Sending Rate</th>
            <td><?php echo intval($stats['MaxSendRate']); ?>/sec</td>
        </tr>
        <tr>
            <th>Sender Email</th>
            <td><?php echo ucfirst($connection['sender_email']); ?></td>
        </tr>
        <tr>
            <th>Sender Name</th>
            <td><?php echo ucfirst($connection['sender_name']); ?></td>
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
                    <li><?php echo $sender; ?></li>
                    <?php endforeach; ?>
                </ul>
            </td>
        </tr>
    </table>
    <p><a href="https://aws.amazon.com/ses/extendedaccessrequest/" target="_blank" rel="nofollow">Increase Sending Limits</a></p>
</div>