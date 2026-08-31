<template>
    <!--
        Setting rows rather than a stacked form: the label and its explanation take the
        width on the left and the control is pinned right, only as wide as it needs to be.
        Element's default label column gave the widest part of each row to a 44px
        checkbox while the sentence explaining it wrapped.
    -->
    <div class="fss_general_settings">
        <div class="fsm_row">
            <div class="fsm_row_label">
                <span class="fsm_row_title">{{ $t('Log Emails') }}</span>
                <p>{{ $t('Log All Emails for Reporting') }}</p>
            </div>
            <div class="fsm_row_control">
                <el-switch v-model="settings.misc.log_emails" active-value="yes" inactive-value="no"/>
            </div>
        </div>

        <div class="fsm_row" v-if="settings.misc.log_emails == 'yes' && !!appVars.has_fluentcrm">
            <div class="fsm_row_label">
                <span class="fsm_row_title">{{ $t('FluentCRM Email Logging') }}</span>
                <p>{{ $t('Disable Logging for FluentCRM Emails') }}</p>
            </div>
            <div class="fsm_row_control">
                <el-switch v-model="settings.misc.disable_fluentcrm_logs" active-value="yes" inactive-value="no"/>
            </div>
        </div>

        <div class="fsm_row" v-if="settings.misc.log_emails == 'yes'">
            <div class="fsm_row_label">
                <span class="fsm_row_title">{{ $t('Delete Logs') }}</span>
                <p>{{ $t('How long a logged email is kept before FluentSMTP deletes it automatically.') }}</p>
            </div>
            <div class="fsm_row_control">
                <el-select v-model="settings.misc.log_saved_interval_days">
                    <el-option
                        v-for="(logLabel, logValue) in logging_days"
                        :key="logValue"
                        :value="logValue"
                        :label="logLabel"
                    ></el-option>
                </el-select>
            </div>
        </div>

        <div class="fsm_row">
            <div class="fsm_row_label">
                <span class="fsm_row_title">{{ $t('Default Connection') }}</span>
                <p>{{ $t('__default_connection_popover') }}</p>
            </div>
            <div class="fsm_row_control">
                <el-select v-model="settings.misc.default_connection">
                    <el-option
                        v-for="(connection, connectionId) in settings.connections"
                        :key="connectionId"
                        :value="connectionId"
                        :label="connection.title +' - '+ connection.provider_settings.sender_email"
                    ></el-option>
                </el-select>
            </div>
        </div>

        <div class="fsm_row">
            <div class="fsm_row_label">
                <span class="fsm_row_title">{{ $t('Fallback Connection') }}</span>
                <p>{{ $t('__fallback_connection_popover') }}</p>
            </div>
            <div class="fsm_row_control">
                <el-select clearable v-if="connectionsCount > 1" v-model="settings.misc.fallback_connection">
                    <el-option
                        v-for="(connection, connectionId) in settings.connections"
                        :key="connectionId"
                        :disabled="settings.misc.default_connection == connectionId"
                        :value="connectionId"
                        :label="connection.title +' - '+ connection.provider_settings.sender_email"
                    ></el-option>
                </el-select>
                <p v-else class="fsm_row_note">{{ $t('Please add another connection to use fallback feature') }}</p>
            </div>
        </div>

        <div class="fsm_row">
            <div class="fsm_row_label">
                <span class="fsm_row_title">{{ $t('Email Simulation') }}</span>
                <p>{{ $t('__Email_Simulation_Label') }}</p>
                <p class="fsm_row_warning" v-if="settings.misc.simulate_emails == 'yes'">
                    {{ $t('__Email_Simulation_Yes') }}
                </p>
                <p class="fsm_row_warning" v-if="appVars.is_disabled_defined">
                    {{ $t('Emails are being simulated due to the definition of ') }}
                    <b>FLUENTMAIL_SIMULATE_EMAILS</b>{{ $t(' in your PHP code.') }}
                </p>
            </div>
            <div class="fsm_row_control">
                <el-switch v-model="settings.misc.simulate_emails" active-value="yes" inactive-value="no"/>
            </div>
        </div>

        <div class="fsm_row">
            <div class="fsm_row_label">
                <span class="fsm_row_title">{{ $t('Add Multi-Part Plain Text for HTML Emails (beta)') }}</span>
                <p>{{ $t('__Email_TEXT_PART_Label') }}</p>
            </div>
            <div class="fsm_row_control">
                <el-switch v-model="settings.misc.send_as_text" active-value="yes" inactive-value="no"/>
            </div>
        </div>

        <div class="fsm_row_actions">
            <el-button v-loading="saving" @click="saveMiscSettings()" type="primary">
                {{ $t('Save Settings') }}
            </el-button>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'FluentMailGeneralSettings',
        data() {
            return {
                saving: false,
                logging_days: {
                    7: this.$t('After 7 Days'),
                    14: this.$t('After 14 Days'),
                    30: this.$t('After 30 Days'),
                    60: this.$t('After 60 Days'),
                    90: this.$t('After 90 Days'),
                    180: this.$t('After 6 Months'),
                    365: this.$t('After 1 Year'),
                    730: this.$t('After 2 Years')
                }
            }
        },
        computed: {
            connectionsCount() {
                return Object.keys(this.settings.connections).length;
            }
        },
        methods: {
            saveMiscSettings() {

                if(this.settings.misc.fallback_connection && this.settings.misc.default_connection && this.settings.misc.default_connection == this.settings.misc.fallback_connection) {
                    this.$notify.error(this.$t('__DEFAULT_CONNECTION_CONFLICT'));
                    return;
                }

                this.saving = true;
                this.$post('misc-settings', {
                    settings: this.settings.misc
                })
                    .then(response => {
                        this.$notify.success(response.data.message);
                    })
                    .fail((error) => {
                        console.log(error);
                    })
                    .always(() => {
                        this.saving = false;
                    });
            }
        }
    };
</script>

<style lang="scss">
.fss_general_settings {
    .fsm_row_note {
        font-size: 12px;
        color: var(--fsm-text-light);
        margin: 0;
    }

    /* Only shown when the setting is not what it ought to be, so it reads as a flag. */
    .fsm_row_warning {
        font-size: 12px;
        color: var(--fsm-warning-fg) !important;
        margin-top: 6px !important;
    }

    .fsm_row_actions {
        padding-top: 16px;
    }
}
</style>
