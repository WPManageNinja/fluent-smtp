<template>
    <div>
        <h3 class="fs_config_title">{{ $t('Cloudflare Email API Settings') }}</h3>

        <el-alert type="info" :closable="false" style="margin-bottom: 15px;">
            <div style="line-height: 1.6;">
                <strong>{{ $t('How to create the API token:') }}</strong>
                <ol style="margin: 6px 0 0 18px; padding: 0;">
                    <li>
                        {{ $t('Open') }}
                        <a target="_blank" rel="noopener" :href="tokenDashUrl">
                            {{ $t('Cloudflare Account API Tokens') }}
                        </a>
                        {{ $t('and click') }} <em>{{ $t('Create Token') }}</em>.
                        <span v-if="!connection.account_id" style="color:var(--fsm-text-light);">
                            ({{ $t("you'll be prompted to pick the account") }})
                        </span>
                    </li>
                    <li>{{ $t('Set Permission policies to') }} <strong>Custom</strong>.</li>
                    <li>
                        {{ $t('Add a policy with scope') }} <strong>Entire Account</strong>,
                        {{ $t('then pick') }} <strong>Email &amp; Messaging → Email Sending</strong>
                        {{ $t('and check both') }} <strong>Read</strong> {{ $t('and') }} <strong>Edit</strong>.
                    </li>
                    <li>{{ $t('Save and copy the token, then paste it below along with your Account ID.') }}</li>
                </ol>
                <p style="margin: 8px 0 0;">
                    {{ $t('The sender domain must also be added to this Cloudflare account with Email Sending enabled (SPF/DKIM/DMARC records published).') }}
                </p>
            </div>
        </el-alert>

        <el-radio-group size="small" v-model="connection.key_store">
            <el-radio-button value="db">{{ $t('Store in Database') }}</el-radio-button>
            <el-radio-button value="wp_config">{{ $t('Store in wp-config.php') }}</el-radio-button>
        </el-radio-group>

        <template v-if="connection.key_store == 'db'">
            <el-form-item>
                <label for="cloudflare-key">
                    {{ $t('API Token') }}
                </label>

                <InputPassword
                    id="cloudflare-key"
                    v-model="connection.api_key"
                    :disable_help="connection.disable_encryption === 'yes'"
                />
                <error :error="errors.get('api_key')"/>
            </el-form-item>
            <el-form-item>
                <el-checkbox true-value="yes" false-value="no" v-model="connection.disable_encryption">
                    {{ $t('Disable Encryption for API Token (Not Recommended)') }}
                </el-checkbox>
                <p style="color: var(--fsm-danger-fg); margin-top: 0;" v-if="connection.disable_encryption === 'yes'">
                    {{
                        $t('Your API token will be stored as readable text in the database. Only turn this on if a security plugin on this site rotates the WordPress SALT keys, which would otherwise invalidate the encrypted value.')
                    }}
                </p>
            </el-form-item>
        </template>

        <div class="fss_condesnippet_wrapper" v-else-if="connection.key_store == 'wp_config'">
            <el-form-item>
                <label>{{ $t('__WP_CONFIG_INSTRUCTION') }}</label>
                <div class="code_snippet">
                    <textarea readonly style="width: 100%;">define( 'FLUENTMAIL_CLOUDFLARE_API_KEY', '********************' );
define( 'FLUENTMAIL_CLOUDFLARE_ACCOUNT_ID', 'your-cloudflare-account-id' );</textarea>
                </div>
                <error :error="errors.get('api_key')"/>
            </el-form-item>
        </div>

        <el-form-item :label="$t('Cloudflare Account ID')">
            <el-input
                type="text"
                :placeholder="$t('Your Cloudflare Account ID')"
                v-model="connection.account_id"
            ></el-input>
            <error :error="errors.get('account_id')"/>
        </el-form-item>

    </div>
</template>

<script>
import InputPassword from '@/Pieces/InputPassword';
import Error from '@/Pieces/Error';

export default {
    name: 'Cloudflare',
    props: ['connection', 'errors'],
    components: {
        InputPassword,
        Error
    },
    data() {
        return {};
    },
    watch: {
        'connection.key_store'(value) {
            if (value === 'wp_config') {
                this.connection.api_key = '';
            }
        }
    },
    computed: {
        tokenDashUrl() {
            const account = this.connection.account_id || ':account';
            return 'https://dash.cloudflare.com/?to=/' + account + '/api-tokens';
        }
    }
};
</script>
