<template>
    <div class="fss_general_settings">
        <el-form class="fss_compact_form" :data="settings.misc" label-position="top">
            
            <el-form-item label="Log Emails">
                <el-checkbox
                    v-model="settings.misc.log_emails"
                    true-label="yes"
                    false-label="no"
                >Log All Emails for Reporting</el-checkbox>
            </el-form-item>

            <el-form-item v-if="settings.misc.log_emails == 'yes' && !!appVars.has_fluentcrm" label="FluentCRM Email Logging">
                <el-checkbox v-model="settings.misc.disable_fluentcrm_logs" true-label="yes" false-label="no">Disable Logging for FluentCRM Emails (Recommeneded)</el-checkbox>
            </el-form-item>
            
            <el-form-item v-if="settings.misc.log_emails == 'yes'" label="Delete Logs">
                <el-select v-model="settings.misc.log_saved_interval_days">
                    <el-option
                        v-for="(logLabel, logValue) in logging_days"
                        :key="logValue"
                        :value="logValue"
                        :label="logLabel"
                    ></el-option>
                </el-select>
            </el-form-item>

            <el-form-item label="Default Connection">
                <el-select v-model="settings.misc.default_connection">
                    <el-option
                        v-for="(connection, connectionId) in settings.connections"
                        :key="connectionId"
                        :value="connectionId"
                        :label="connection.title +' - '+ connection.provider_settings.sender_email"
                    ></el-option>
                </el-select>
            </el-form-item>

            <el-button
                v-loading="saving"
                @click="saveMiscSettings()"
                type="success"
            >Save Settings</el-button>
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
                    7: 'After 7 Days',
                    14: 'After 14 Days',
                    30: 'After 30 Days',
                    60: 'After 60 Days',
                    90: 'After 90 Days',
                    180: 'After 6 Months',
                    365: 'After 1 Year',
                    730: 'After 2 Years'
                }
            }
        },
        methods: {
            saveMiscSettings() {
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
