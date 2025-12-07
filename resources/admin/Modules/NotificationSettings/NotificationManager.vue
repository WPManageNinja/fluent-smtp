<template>
    <div v-if="notification_settings.telegram !== undefined">
        <template v-if="selectedChannel">
            <component
                :is="getChannelComponent(selectedChannel)"
                :notification_settings="notification_settings"
                :channel_key="selectedChannel"
                @back="goBack"
            />
        </template>
        <alert-list-table
            v-else
            :notification_settings="notification_settings"
            @edit-channel="editChannel"
            @channel-toggled="reloadSettings"
        />
    </div>
</template>

<script type="text/babel">
import AlertListTable from './_AlertListTable.vue';
import TelegramNotification from './_TelegramNotification.vue';
import SlackNotification from './_SlackNotification.vue';
import DiscordNotification from './_DiscordNotification.vue';

export default {
    name: 'NotificationManager',
    components: {
        AlertListTable,
        TelegramNotification,
        SlackNotification,
        DiscordNotification
    },
    props: {
        notification_settings: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            selectedChannel: null
        }
    },
    methods: {
        editChannel(channelKey) {
            this.selectedChannel = channelKey;
        },
        goBack() {
            this.selectedChannel = null;
        },
        getChannelComponent(channelKey) {
            const componentMap = {
                'telegram': 'telegram-notification',
                'slack': 'slack-notification',
                'discord': 'discord-notification'
            };
            return componentMap[channelKey] || null;
        },
        reloadSettings() {
            this.$emit('reload-settings');
        }
    }
}
</script>
