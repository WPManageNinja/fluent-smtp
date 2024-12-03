<template>
    <div class="fss_connection_wizard">
        <el-form :data="connection" label-position="top">
            <el-form-item :label="$t('Connection Provider')">
                <connection-provider :providers="providers" :connection="connection" />
            </el-form-item>
            <template v-if="connection.provider">
                <div class="fss_config_section">
                    <h3 class="fs_config_title">{{$t('Sender Settings')}}</h3>
                    <el-row :gutter="20">
                        <el-col :md="12" :sm="24">
                            <el-form-item :label="$t('From Email')">
                                <error :error="errors.get('sender_email')" />
                                <el-input
                                    type="email"
                                    :placeholder="$t('From Email')"
                                    v-model="connection.sender_email"
                                ></el-input>
                                <p style="color: red;" v-if="is_conflicted">{{ $t('__ANOTHER_CONNECTION_NOTICE') }}</p>
                            </el-form-item>
                            <div v-if="connection.force_from_email != undefined">
                                <el-checkbox
                                    true-label="yes"
                                    false-label="no"
                                    v-model="connection.force_from_email"
                                >
                                    {{$t('Force From Email (Recommended Settings: Enable)')}}
                                    <el-tooltip effect="dark" placement="top-start">
                                        <div slot="content">
                                            {{$t('__from_email_tooltip')}}
                                        </div>
                                        <i class="el-icon-info"></i>
                                    </el-tooltip>
                                </el-checkbox>
                            </div>
                            <div v-if="connection.return_path != undefined">
                                <el-checkbox
                                    true-label="yes"
                                    false-label="no"
                                    v-model="connection.return_path"
                                >
                                    {{$t('Set the return-path to match the From Email')}}
                                    <el-tooltip effect="dark" placement="top-start">
                                        <div slot="content" v-html="$t('__RETURN_PATH_TOOLTIP')">
                                        </div>
                                        <i class="el-icon-info"></i>
                                    </el-tooltip>
                                </el-checkbox>
                            </div>
                        </el-col>
                        <el-col :md="12" :sm="24">
                            <el-form-item :label="$t('From Name')">
                                <el-input
                                    type="text"
                                    :placeholder="$t('From Name')"
                                    v-model="connection.sender_name"
                                ></el-input>
                                <error :error="errors.get('sender_name')" />
                            </el-form-item>
                            <el-checkbox
                                v-model="connection.force_from_name"
                                true-label="yes"
                                false-label="no"
                            >
                                {{$t('Force Sender Name')}}
                                <el-tooltip effect="dark" placement="top-start">
                                    <div slot="content">
                                        {{$t('force_sender_tooltip')}}
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
                <p v-if="providers[connection.provider].note" style="padding: 5px 0px; font-size: 16px; color: #ff5722;" v-html="providers[connection.provider].note"></p>
                <el-button v-loading="saving" @click="saveConnectionSettings()" type="success">{{$t('Save Connection Settings')}}</el-button>
            </template>
            <div v-else>
                <h3 style="text-align: center;">{{$t('save_connection_error_1')}}</h3>
            </div>
            <p v-if="saving">{{ $t('Validating Data. Please wait...') }}</p>
            <el-alert style="margin-top: 20px" v-if="has_error" type="error">{{$t('save_connection_error_2')}}</el-alert>
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
    import gmail from './Partials/Providers/Gmail';
    import outlook from './Partials/Providers/Outlook';
    import postmark from './Partials/Providers/PostMark';
    import elasticmail from './Partials/Providers/ElasticMail';
    import smtp2go from './Partials/Providers/Smtp2Go';
    import Errors from '@/Bits/Errors';
    import Error from '@/Pieces/Error';
    import each from 'lodash/each';
    import ConnectionProvider from './Partials/_ConnectionSelector';

    export default {
        name: 'ConnectionWizard',
        props: ['connection', 'is_new', 'providers', 'connection_key', 'connections'],
        components: {
            ses: AmazonSes,
            mailgun,
            pepipost,
            sendgrid,
            sendinblue,
            sparkpost,
            smtp,
            gmail,
            outlook,
            postmark,
            elasticmail,
            smtp2go,
            Error,
            ConnectionProvider
        },
        data() {
            return {
                saving: false,
                errors: new Errors(),
                api_error: '',
                has_error: false
            }
        },
        computed: {
            is_conflicted() {
                if (!this.connections) {
                    return false;
                }

                let isConflicted = false;
                each(this.connections, (existingConnection, connectionKey) => {
                    if(this.connection_key != connectionKey && existingConnection.provider_settings.sender_email == this.connection.sender_email) {
                        isConflicted = true;
                    }
                });

                return isConflicted;
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

                each(options, (value, key) => {
                    this.$set(this.connection, key, value);
                });
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
