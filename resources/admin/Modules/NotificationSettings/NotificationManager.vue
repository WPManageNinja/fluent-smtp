<template>
    <div v-if="notification_settings.telegram_notify_status">
        <template v-if="selectedDriver == 'telegram'">
            <telegram-notification :notification_settings="notification_settings"/>
        </template>
        <template v-else-if="selectedDriver == 'slack'">
            <slack-notification :notification_settings="notification_settings"/>
        </template>
        <template v-else>
            <h3>Configure Telegram Bot</h3>
            <telegram-notification :notification_settings="notification_settings"/>
            <hr style="margin: 20px 0;" />
            <h3>Or configure Slack Bot</h3>
            <slack-notification :notification_settings="notification_settings"/>
        </template>
    </div>
</template>

<script type="text/babel">
import TelegramNotification from './_TelegramNotification.vue';
import SlackNotification from './_SlackNotification.vue';

export default {
    name: 'NotificationManager',
    components: {
        TelegramNotification,
        SlackNotification
    },
    props: {
        notification_settings: {
            type: Object,
            required: true
        }
    },
    computed: {
        selectedDriver() {
            if (this.notification_settings.telegram_notify_status == 'yes') {
                return 'telegram';
            }
            if (this.notification_settings.slack && this.notification_settings.slack.status == 'yes') {
                return 'slack';
            }

            return 'unknown';
        }
    }
}
</script>
