<template>
    <div>
        <div v-if="connection_key && !connection.version" v-html="$t('__GCP_INTRO')" class="ff_smtp_warn">
        </div>
        <h3>{{ $t('Gmail / Google Workspace API Settings') }}</h3>
        <p v-html="$t('__GCP_API_INST')"></p>
        
        <el-radio-group size="mini" v-model="connection.key_store">
            <el-radio-button value="db" label="db">{{ $t('Store Application Keys in DB') }}</el-radio-button>
            <el-radio-button value="wp_config" label="wp_config">{{ $t('Application Keys in Config File') }}</el-radio-button>
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
                    />
                    <error :error="errors.get('client_secret')" />
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

        <div v-if="!connection.access_token">
            <div style="text-align: center;">
                <h3>{{ $t('Please authenticate with Google to get ') }}<b>{{ $t('Access Token') }}</b></h3>
                <el-button v-loading="gettingRedirect" @click="redirectToGoogle()" type="danger">{{
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
            <h3>{{ $t('__GMAIL_SUCCESS') }} <a @click.prevent="connection.access_token = ''" href="#">{{ $t('click here') }}</a></h3>
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
                        this.errors.record(errors.responseJSON.data);
                    })
                    .always(() => {
                        this.gettingRedirect = false;
                    });
            }
        },
        mounted() {
            if (!this.connection.key_store) {
                this.$set(this.connection, 'key_store', 'db');
            }
        }
    };
</script>
