<template>
    <div v-loading="loading" class="fsm_chan_wrap">
        <!--
            Rows rather than a table, the same shape the connections list uses. Four
            channels with a name, a switch and two icon buttons is not tabular data - the
            table spent three header cells saying Channel, Status and Actions above four
            rows that could not be sorted, filtered or compared.

            A channel that has never been set up has nothing to switch on, so it offers
            the one thing it can do instead: Set Up.
        -->
        <ul class="fsm_chan_list">
            <li v-for="alert in alerts" :key="alert.key" class="fsm_chan">
                <img class="fsm_chan_logo" :src="alert.logo" :alt="alert.title"/>

                <div class="fsm_chan_main">
                    <span class="fsm_chan_title">{{ alert.title }}</span>
                    <span class="fsm_chan_note">
                        {{ alert.is_configured ? $t('Connected') : $t('Not connected') }}
                    </span>
                </div>

                <el-switch
                    v-if="alert.is_configured"
                    v-model="alert.is_active"
                    active-value="yes"
                    inactive-value="no"
                    @change="toggleChannel()"
                    :disabled="toggling"
                    :aria-label="$t('Notify on failure') + ' - ' + alert.title">
                </el-switch>

                <div class="fsm_chan_actions">
                    <template v-if="alert.is_configured">
                        <el-button
                            size="small"
                            icon="FsmIconEdit"
                            @click="editChannel(alert.key)"
                            :title="$t('Edit')"
                            :aria-label="$t('Edit') + ' ' + alert.title">
                        </el-button>
                        <el-button
                            size="small"
                            icon="FsmIconDelete"
                            @click="deactivateChannel(alert.key)"
                            :title="$t('Deactivate')"
                            :aria-label="$t('Deactivate') + ' ' + alert.title">
                        </el-button>
                    </template>
                    <el-button v-else size="small" type="primary" @click="editChannel(alert.key)">
                        {{ $t('Set Up') }}
                    </el-button>
                </div>
            </li>
        </ul>

        <!--
            A note under the list rather than an alert box above it: nothing is wrong, and
            a second channel is a choice the reader has just made deliberately.
        -->
        <p v-if="activatedChannelsCount > 1" class="fsm_chan_hint">
            {{ $t('We recommend activating only one notification channel at a time.') }}
        </p>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'AlertListTable',
    props: {
        notification_settings: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            alerts: [],
            loading: false,
            toggling: false
        }
    },
    computed: {
        activatedChannelsCount() {
            return this.alerts.filter(alert => alert.is_active === 'yes').length;
        }
    },
    methods: {
        loadChannels() {
            this.loading = true;
            this.$get('settings/notification-channels')
                .then((response) => {
                    let activeChannels = this.notification_settings.active_channel || [];
                    if (typeof activeChannels !== 'object') {
                        activeChannels = [];
                    }

                    const channels = response.data.channels || {};
                    this.alerts = Object.keys(channels).map(key => {
                        const channel = channels[key];
                        const settings = this.notification_settings[key] || {};
                        // Check if channel is configured by checking if status is 'yes' and has required settings
                        // Each channel may have different required fields, so we check if status is yes and settings exist
                        const isConfigured = settings.status === 'yes' && Object.keys(settings).length > 1;

                        return {
                            key: key,
                            title: channel.title,
                            logo: channel.logo,
                            is_active: (isConfigured && (activeChannels.indexOf(key) != -1)) ? 'yes' : 'no',
                            status: channel.status || 'no',
                            is_configured: isConfigured
                        };
                    });
                })
                .catch((errors) => {
                    this.$notify.error(this.$errorMessage(errors));
                    this.$notify.error(this.$t('Failed to load notification channels'));
                })
                .always(() => {
                    this.loading = false;
                });
        },
        /*
         * The whole set of enabled channels is posted, not the one that changed, so the
         * switch the reader just moved is already the truth of it - there is nothing to
         * write back on success. On failure the row is put back the way it was.
         */
        toggleChannel() {
            const enabledChannelKeys = [];
            this.alerts.forEach(alert => {
                if (alert.is_active === 'yes' && alert.status === 'yes') {
                    enabledChannelKeys.push(alert.key);
                }
            });

            this.toggling = true;
            this.$post('settings/notification-channels/toggle', {
                channel_keys: enabledChannelKeys
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                    this.$emit('channel-toggled');
                })
                .catch((errors) => {
                    this.$notify.error(errors.responseJSON?.data?.message || this.$t('Failed to toggle channel'));
                    this.loadChannels();
                })
                .always(() => {
                    this.toggling = false;
                });
        },
        editChannel(channelKey) {
            this.$emit('edit-channel', channelKey);
        },
        deactivateChannel(channelKey) {
            this.$confirm(
                this.$t('Are you sure you want to deactivate and remove settings for this channel?'),
                this.$t('Warning'),
                {
                    confirmButtonText: this.$t('Yes, Deactivate'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning'
                }
            )
                .then(() => {
                    // Call the disconnect endpoint for the specific channel
                    this.$post(`settings/${channelKey}/disconnect`)
                        .then((response) => {
                            this.$notify.success(response.data.message);
                            // Update local state immediately
                            const alert = this.alerts.find(a => a.key === channelKey);
                            if (alert) {
                                alert.is_configured = false;
                                alert.is_active = 'no';
                                alert.status = 'no';
                            }
                            // Reload channels to get fresh data from server
                            this.loadChannels();
                            // Emit event to parent to reload settings
                            this.$emit('channel-toggled');
                        })
                        .catch((errors) => {
                            this.$notify.error(errors.responseJSON?.data?.message || this.$t('Failed to deactivate channel'));
                        });
                })
                .catch(() => {
                    // User cancelled
                });
        }
    },
    mounted() {
        this.loadChannels();
    },
    watch: {
        /*
         * Only the channel list, not the whole settings object.
         *
         * `notification_settings` is one object shared with the email summary form
         * rendered beside this on the same screen, and that form writes into it with
         * v-model. Watching it deeply meant a full notification-channels request, and
         * the loading spinner with it, on every keystroke typed into the summary's
         * recipient field. `active_channel` is what this table actually reads.
         */
        'notification_settings.active_channel': {
            handler() {
                this.loadChannels();
            },
            deep: true
        }
    }
}
</script>
