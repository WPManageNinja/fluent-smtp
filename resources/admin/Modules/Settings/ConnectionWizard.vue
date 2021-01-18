<template>
    <div class="fss_connection_wizard">
        <el-form :data="connection" label-position="top">
            <el-form-item label="Connection Provider">
                <el-radio-group v-model="connection.provider">
                    <el-radio-button v-for="(provider, providerName) in providers" :key="providerName" :label="providerName">
                        <img :title="provider.title" style="width:100px;height:32px;" :src="provider.image" />
                    </el-radio-button>
                </el-radio-group>
            </el-form-item>
            <template v-if="connection.provider">
                <div class="fss_config_section">
                    <h3 class="fs_config_title">Sender Settings</h3>
                    <el-row :gutter="20">
                        <el-col :span="12">
                            <el-form-item label="From Email">
                                <error :error="errors.get('sender_email')" />
                                <el-input
                                    type="email"
                                    placeholder="From Email"
                                    v-model="connection.sender_email"
                                ></el-input>
                            </el-form-item>
                            <div v-if="connection.return_path !== 'undefinded'">
                                <el-checkbox
                                    true-label="yes"
                                    false-label="no"
                                    v-model="connection.return_path"
                                >
                                    Set the return-path to match the From Email
                                    <el-tooltip effect="dark" placement="top-start">
                                        <div slot="content">
                                            Return Path indicates where non-delivery receipts - or bounce messages -<br />
                                            are to be sent. If unchecked, bounce messages may be lost. With this enabled,<br />
                                            you’ll be emailed using "From Email" if any messages bounce as a result of issues with the recipient’s email.
                                        </div>
                                        <i class="el-icon-info"></i>
                                    </el-tooltip>
                                </el-checkbox>
                            </div>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="From Name">
                                <el-input
                                    type="text"
                                    placeholder="From Name"
                                    v-model="connection.sender_name"
                                ></el-input>
                                <error :error="errors.get('sender_name')" />
                            </el-form-item>
                            <el-checkbox
                                v-model="connection.force_from_name"
                                true-label="yes"
                                false-label="no"
                            >
                                Force Sender Name
                                <el-tooltip effect="dark" placement="top-start">
                                    <div slot="content">
                                        When checked, the From Name setting above will be used for all emails, ignoring values set by other plugins.
                                    </div>
                                    <i class="el-icon-info"></i>
                                </el-tooltip>
                            </el-checkbox>
                        </el-col>
                    </el-row>
                </div>
                <div v-if="connection.provider != 'default'" class="fss_config_section">
                    <component
                        :errors="errors"
                        :is="connection.provider"
                        :connection="connection"
                        :provider="providers[connection.provider]"
                    />
                </div>
                <p v-if="providers[connection.provider].note" style="padding: 20px 0px;" v-html="providers[connection.provider].note"></p>
                <el-button v-loading="saving" @click="saveConnectionSettings()" type="success">Save Connection Settings</el-button>
            </template>
            <p v-if="saving">Validating Data. Please wait</p>
            <el-alert style="margin-top: 20px" v-if="has_error" type="error">Credential Verification Failed. Please check your inputs</el-alert>
        </el-form>
    </div>
</template>

<script type="text/babel">
    import mailgun from './Partials/Providers/MailGun';
    import pepipost from './Partials/Providers/PepiPost';
    import sendgrid from './Partials/Providers/SendGrid';
    import sendinblue from './Partials/Providers/SendInBlue';
    import AmazonSes from './Partials/Providers/AmazonSes';
    import sparkpost from './Partials/Providers/SparkPost';
    import smtp from './Partials/Providers/Smtp';
    import Errors from '@/Bits/Errors';
    import Error from '@/Pieces/Error';

    export default {
        name: 'ConnectionWizard',
        props: ['connection', 'is_new', 'providers', 'connection_key'],
        components: {
            ses: AmazonSes,
            mailgun,
            pepipost,
            sendgrid,
            sendinblue,
            sparkpost,
            smtp,
            Error
        },
        data() {
            return {
                saving: false,
                errors: new Errors(),
                api_error: '',
                has_error: false
            }
        },
        watch: {
            'connection.provider'(value) {
                if (!value) {
                    return false;
                }

                const options = JSON.parse(
                    JSON.stringify(this.providers[value].options)
                );

                options.provider = value;
                this.connection = options;
            }
        },
        methods: {
            saveConnectionSettings() {
                this.saving = true;
                this.api_error = '';
                this.has_error = false;
                this.$post('settings', {
                    connection: this.connection,
                    connection_key: this.connection_key
                })
                    .then(response => {
                        this.$notify.success(response.data.message);
                        this.$set(this.settings, 'connections', response.data.connections);
                        this.$set(this.settings, 'mappings', response.data.mappings);
                        this.$set(this.settings, 'misc', response.data.misc);
                        this.$router.push({
                            name: 'connections'
                        });
                    })
                    .fail((error) => {
                        this.errors.record(error.responseJSON.data);
                        this.api_error = error.responseJSON.data.api_error;
                        this.has_error = true;
                    })
                    .always(() => {
                        this.saving = false;
                    });
            }
        }
    };
</script>
