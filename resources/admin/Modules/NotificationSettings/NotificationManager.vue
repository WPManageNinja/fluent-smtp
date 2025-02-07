<template>
    <div v-if="notification_settings.telegram">
        <template v-if="selectedDriver == 'telegram'">
            <telegram-notification :notification_settings="notification_settings"/>
        </template>
        <template v-else-if="selectedDriver == 'slack'">
            <slack-notification :notification_settings="notification_settings"/>
        </template>
        <template v-else-if="selectedDriver == 'discord'">
            <discord-notification :notification_settings="notification_settings"/>
        </template>
        <div style="text-align: center;" v-else>
            <h3>{{ $t('Real-Time Email Failure Notifications') }}</h3>
            <p>{{ $t('__REAL_NOTIFCATION_DESC') }}</p>

            <div class="fss_notification_channels">
                <div v-for="(channel, channelKey) in channels" :key="channelKey" class="fss_notification_channel">
                    <div @click="configureChannel(channelKey)" class="fss_notification_item">
                        <img :src="`${appVars.images_url}${channel.logo_name}`"/>
                        <span>{{ channel.name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import TelegramNotification from './_TelegramNotification.vue';
import SlackNotification from './_SlackNotification.vue';
import DiscordNotification from './_DiscordNotification.vue';

export default {
    name: 'NotificationManager',
    components: {
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
    computed: {
        selectedDriver() {
            if(this.selectedChannel) {
                return this.selectedChannel;
            }
            if (this.notification_settings.telegram && this.notification_settings.telegram.status == 'yes') {
                return 'telegram';
            }
            if (this.notification_settings.slack && this.notification_settings.slack.status == 'yes') {
                return 'slack';
            }

            if (this.notification_settings.discord && this.notification_settings.discord.status == 'yes') {
                return 'discord';
            }

            return 'unknown';
        }
    },
    data() {
        return {
            channels: {
                telegram: {
                    name: "Telegram",
                    logo_name: "tele.svg"
                },
                slack: {
                    name: "Slack",
                    logo_name: "slack.svg"
                },
                discord: {
                    name: "Discord",
                    logo_name: "disc.svg"
                }
            },
            selectedChannel: null
        }
    },
    methods: {
        configureChannel(channel) {
            this.selectedChannel = channel;
        }
    }
}
</script>
