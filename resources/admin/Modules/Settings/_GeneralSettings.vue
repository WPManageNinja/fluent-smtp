<template>
    <div class="fss_general_settings">
        <el-form class="fss_compact_form" :data="settings.misc" label-position="top">
            
            <el-form-item :label="$t('Log Emails')">
                <el-checkbox
                    v-model="settings.misc.log_emails"
                    true-label="yes"
                    false-label="no"
                >{{$t('Log All Emails for Reporting')}}</el-checkbox>
            </el-form-item>

            <el-form-item v-if="settings.misc.log_emails == 'yes' && !!appVars.has_fluentcrm" :label="$t('FluentCRM Email Logging')">
                <el-checkbox v-model="settings.misc.disable_fluentcrm_logs" true-label="yes" false-label="no">{{$t('Disable Logging for FluentCRM Emails')}}</el-checkbox>
            </el-form-item>
            
            <el-form-item v-if="settings.misc.log_emails == 'yes'">
                <label slot="label">
                    {{$t('Delete Logs')}}
                    <el-popover
                        width="400"
                        trigger="hover">
                        <p>{{$t('delete_logs_info')}}</p>
                        <i slot="reference" class="el-icon el-icon-info"></i>
                    </el-popover>
                </label>
                <el-select v-model="settings.misc.log_saved_interval_days">
                    <el-option
                        v-for="(logLabel, logValue) in logging_days"
                        :key="logValue"
                        :value="logValue"
                        :label="logLabel"
                    ></el-option>
                </el-select>
            </el-form-item>

            <el-form-item>
                <label slot="label">
                    {{$t('Default Connection')}}
                    <el-popover
                        width="400"
                        trigger="hover">
                        <p>{{$t('__default_connection_popover')}}</p>
                        <i slot="reference" class="el-icon el-icon-info"></i>
                    </el-popover>
                </label>
                <el-select v-model="settings.misc.default_connection">
                    <el-option
                        v-for="(connection, connectionId) in settings.connections"
                        :key="connectionId"
                        :value="connectionId"
                        :label="connection.title +' - '+ connection.provider_settings.sender_email"
                    ></el-option>
                </el-select>
            </el-form-item>

            <el-form-item>
                <label slot="label">
                    {{ $t('Fallback Connection') }}
                    <el-popover
                        width="400"
                        trigger="hover">
                        <p>{{$t('__fallback_connection_popover')}}</p>
                        <i slot="reference" class="el-icon el-icon-info"></i>
                    </el-popover>
                </label>
                <el-select clearable v-if="connectionsCount > 1" v-model="settings.misc.fallback_connection">
                    <el-option
                        v-for="(connection, connectionId) in settings.connections"
                        :key="connectionId"
                        :disabled="settings.misc.default_connection == connectionId"
                        :value="connectionId"
                        :label="connection.title +' - '+ connection.provider_settings.sender_email"
                    ></el-option>
                </el-select>
                <p v-else style="color: #6d6b6b;margin: 0;">{{$t('Please add another connection to use fallback feature')}}</p>
            </el-form-item>

            <el-form-item :label="$t('Email Simulation')">
                <el-checkbox
                    v-model="settings.misc.simulate_emails"
                    true-label="yes"
                    false-label="no"
                >{{$t('__Email_Simulation_Label')}}</el-checkbox>
                <p style="color: red;" v-if="settings.misc.simulate_emails == 'yes'">{{$t('__Email_Simulation_Yes')}}</p>
                <p v-if="appVars.is_disabled_defined" style="color: red;">{{ ('Emails are being simulated due to the definition of ') }} <b>FLUENTMAIL_SIMULATE_EMAILS</b>{{ (' in your PHP code.') }}</p>
            </el-form-item>

            <el-form-item :label="$t('Add Multi-Part Plain Text for HTML Emails (beta)')">
                <el-checkbox
                    v-model="settings.misc.send_as_text"
                    true-label="yes"
                    false-label="no"
                >{{$t('__Email_TEXT_PART_Label')}}</el-checkbox>
            </el-form-item>

            <el-button
                v-loading="saving"
                @click="saveMiscSettings()"
                type="success"
            >{{$t('Save Settings')}}</el-button>
        </el-form>
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
