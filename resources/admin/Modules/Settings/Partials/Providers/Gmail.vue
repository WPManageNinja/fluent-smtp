<template>
    <div>
        <div v-if="connection_key && !connection.version" v-html="$t('__GCP_INTRO')" class="ff_smtp_warn">
        </div>
        <h3 class="fs_config_title">{{ $t('Gmail / Google Workspace API Settings') }}</h3>
        <p v-html="$t('__GCP_API_INST')"></p>
        
        <el-radio-group size="small" v-model="connection.key_store">
            <el-radio-button value="db">{{ $t('Store Application Keys in DB') }}</el-radio-button>
            <el-radio-button value="wp_config">{{ $t('Application Keys in Config File') }}</el-radio-button>
        </el-radio-group>

        <el-row :gutter="20" v-if="connection.key_store == 'db'">
            <el-col :md="12" :sm="24">
                <el-form-item>
                    <label for="client_id">
                        {{ $t('Application Client ID') }}
                    </label>

                    <InputPassword
                        id="client_id"
                        v-model="connection.client_id"
                        :disable_help="connection.disable_encryption === 'yes'"
                    />

                    <error :error="errors.get('client_id')" />
                </el-form-item>
            </el-col>

            <el-col  :md="12" :sm="24">
                <el-form-item>
                    <label for="client_secret">
                        {{ $t('Application Client Secret') }}
                    </label>

                    <InputPassword
                        id="client_secret"
                        v-model="connection.client_secret"
                        :disable_help="connection.disable_encryption === 'yes'"
                    />
                    <error :error="errors.get('client_secret')" />
                </el-form-item>
            </el-col>

            <el-col :span="24">
                <el-form-item>
                    <el-checkbox true-value="yes" false-value="no" v-model="connection.disable_encryption">
                        {{ $t('Disable Encryption for Application Client Secret Key (Not Recommended)') }}
                    </el-checkbox>
                    <p style="color: var(--fsm-danger-fg); margin-top: 0;" v-if="connection.disable_encryption === 'yes'">
                        {{
                            $t('By disabling encryption, your Application Client Secret will be stored in plain text in the database. This is not recommended for security reasons. Enable only if your security plugin rotate WP SALTS frequently.')
                        }}
                    </p>
                </el-form-item>
            </el-col>

        </el-row>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('__GMAIL_CODE_INSTRUCTION') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_GMAIL_CLIENT_ID', '********************' );
define( 'FLUENTMAIL_GMAIL_CLIENT_SECRET', '********************' );</textarea>
                </div>
                <error :error="errors.get('client_id')" />
                <error :error="errors.get('client_secret')" />
            </el-form-item>
        </div>
        <el-form-item :label="$t('Authorized Redirect URI')">
            <el-input :readonly="true" v-model="AuthorizedRedirectURI" />
            <p>{{ $t('*** It is very important to put ') }}<b>https://fluentsmtp.com/gapi/</b>{{ $t(' in the ') }} <b>{{ $t('Authorized Redirect URIs') }}</b>{{ $t(' option in the Google Cloud Project.') }}</p>
        </el-form-item>

        <!--
            The step that finishes this form, so it is the primary action. It was
            `type="danger"`, which is the colour this admin uses for deleting a
            connection - a red button under a centred heading, for the one thing the
            screen is asking you to do.
        -->
        <div v-if="!connection.access_token">
            <div class="fsm_provider_auth">
                <p>{{ $t('Please authenticate with Google to get ') }}<b>{{ $t('Access Token') }}</b></p>
                <el-button v-loading="gettingRedirect" @click="redirectToGoogle()" type="primary">{{
                        $t('Authenticate with Google & Get Access Token') }}</el-button>

            </div>
            <el-row v-if="redirectUrl" :gutter="20">
                <el-col :span="12">
                    <el-form-item>
                        <label for="application_token">
                            {{ $t('Access Token') }}
                        </label>
                        <InputPassword
                            id="application_token"
                            v-model="connection.auth_token"
                        />
                        <error :error="errors.get('auth_token')" />
                        <p>{{ $t('Please send test email to confirm if the connection is working or not.') }}</p>
                    </el-form-item>
                </el-col>
            </el-row>
        </div>
        <div style="text-align: center;" v-else>
            <p class="fsm_provider_connected">{{ $t('__GMAIL_SUCCESS') }} <a @click.prevent="connection.access_token = ''" href="#">{{ $t('click here') }}</a></p>
        </div>

    </div>
</template>

<script type="text/babel">
    import InputPassword from '@/Pieces/InputPassword';
    import Error from '@/Pieces/Error';

    export default {
        name: 'Gmail',
        props: ['connection', 'errors'],
        components: {
            InputPassword,
            Error
        },
        data() {
            return {
                AuthorizedRedirectURI: 'https://fluentsmtp.com/gapi/',
                app_ready: false,
                gettingRedirect: false,
                redirectUrl: '',
                connection_key: this.$route.query.connection_key
            };
        },
        watch: {
            'connection.key_store'(value) {
                if (value === 'wp_config') {
                    this.connection.client_id = '';
                    this.connection.client_secret = '';
                }
            }
        },
        methods: {
            redirectToGoogle() {
                this.gettingRedirect = true;
                this.$post('settings/gmail_auth_url', {
                    connection: this.connection
                })
                    .then(response => {
                        this.redirectUrl = response.data.auth_url;
                        window.open(response.data.auth_url, '_blank');
                    })
                    .catch(errors => {
                        /*
                         * A 403 or an HTML error page carries no field list, so
                         * recording it would attach nothing and the button would go
                         * quiet with no explanation of why the redirect never came.
                         */
                        const payload = errors && errors.responseJSON && errors.responseJSON.data;

                        if (payload && !this.$isAuthError(errors)) {
                            this.errors.record(payload);
                            return;
                        }

                        this.$notify.error(this.$errorMessage(errors));
                    })
                    .always(() => {
                        this.gettingRedirect = false;
                    });
            }
        },
        mounted() {
            if (!this.connection.key_store) {
                this.connection.key_store = 'db';
            }
        }
    };
</script>
