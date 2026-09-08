<template>
    <!--
        The same shapes as the General Settings sidebar on the Settings screen: a switch
        beside the name it belongs to, and stacked label-over-control fields under it.
        The el-form this replaced put its labels in a column of their own, which in a
        sidebar leaves a 40px checkbox holding the widest part of every row.
    -->
    <div class="fsm_alert_summary">
        <div class="fsm_toggle">
            <div class="fsm_toggle_main">
                <el-switch v-model="notification_settings.enabled"
                           :aria-label="$t('Enable Email Summary')"
                           active-value="yes" inactive-value="no"/>
                <span class="fsm_toggle_title" @click="toggleEnabled">
                    {{ $t('Enable Email Summary') }}
                </span>
            </div>
        </div>

        <template v-if="notification_settings.enabled == 'yes'">
            <div class="fsm_row fsm_row_stacked">
                <div class="fsm_row_label">
                    <span class="fsm_row_title">{{ $t('Send To') }}</span>
                    <p>{{ $t('Separate multiple addresses with commas. Use {site_admin} for the site admin address.') }}</p>
                </div>
                <div class="fsm_row_control">
                    <el-input v-model="notification_settings.notify_email"
                              :aria-label="$t('Send To')"
                              :placeholder="$t('Email Address')"/>
                </div>
            </div>

            <!--
                Two columns of days rather than one. Seven stacked checkboxes is a column
                of empty space to the right of every one of them, and the set reads as a
                list to work through rather than as one control with seven states.
            -->
            <div class="fsm_row fsm_row_stacked">
                <div class="fsm_row_label">
                    <span class="fsm_row_title">{{ $t('Send On') }}</span>
                </div>
                <div class="fsm_row_control">
                    <el-checkbox-group v-model="notification_settings.notify_days" class="fsm_day_grid">
                        <el-checkbox v-for="(dayLabel, day) in sending_days" :key="day" :value="day">
                            {{ dayLabel }}
                        </el-checkbox>
                    </el-checkbox-group>
                </div>
            </div>
        </template>

        <div class="fsm_row_actions">
            <el-button :loading="saving" @click="saveSettings" type="primary">
                {{ $t('Save Settings') }}
            </el-button>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'NotificationSettings',
    props: {
        notification_settings: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            sending_days: {
                Mon: this.$t('Monday'),
                Tue: this.$t('Tuesday'),
                Wed: this.$t('Wednesday'),
                Thu: this.$t('Thursday'),
                Fri: this.$t('Friday'),
                Sat: this.$t('Saturday'),
                Sun: this.$t('Sunday')
            },
            saving: false,
        }
    },
    methods: {
        /*
         * The name beside the switch flips it. Element Plus handles the click on the
         * switch itself and the change on the hidden checkbox inside it, so a <label for>
         * would fire both and land back where it started.
         */
        toggleEnabled() {
            this.notification_settings.enabled = this.notification_settings.enabled === 'yes' ? 'no' : 'yes';
        },
        saveSettings() {
            this.saving = true;
            this.$post('settings/notification-settings', {
                settings: this.notification_settings
            })
                .then((response) => {
                    this.$notify.success(response.data.message);
                })
                .catch((errors) => {
                    this.$notify.error(this.$errorMessage(errors));
                })
                .always(() => {
                    this.saving = false;
                });
        }
    }
}
</script>

<style lang="scss">
.fsm_alert_summary {
    /* Two columns, so the set reads as one control rather than a list of seven. */
    .fsm_day_grid {
        @apply grid;

        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 2px 8px;

        .el-checkbox {
            margin-right: 0;
        }
    }

    .fsm_row_actions {
        padding-top: 16px;
        padding-bottom: 4px;

        .el-button {
            width: 100%;
        }
    }
}
</style>
