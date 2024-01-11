<div class="fss_connection_info">
    <table class="wp-list-table widefat striped">
        <tr>
            <th><?php _e('Connection Type', 'fluent-smtp') ?></th>
            <td><?php echo esc_html($connection['provider']); ?></td>
        </tr>
        <tr>
            <th><?php _e('Sender Email', 'fluent-smtp') ?></th>
            <td><?php echo esc_html($connection['sender_email']); ?></td>
        </tr>
        <tr>
            <th><?php _e('Sender Name', 'fluent-smtp') ?></th>
            <td><?php echo esc_html($connection['sender_name']); ?></td>
        </tr>
        <tr>
            <th><?php _e('Force Sender Name', 'fluent-smtp') ?></th>
            <td><?php echo ucfirst($connection['force_from_name']); ?></td>
        </tr>
        <?php if(isset($connection['extra_rows'])) : ?>
        <?php foreach ($connection['extra_rows'] as $row): ?>
        <tr>
            <th><?php echo esc_html($row['title']); ?></th>
            <td><?php echo wp_kses_post($row['content']); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
    </table>
</div>
