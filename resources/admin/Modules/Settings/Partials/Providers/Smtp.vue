<template>
    <div>
        <div style="margin-bottom: 20px;">
            <el-radio-group size="mini" v-model="connection.key_store">
                <el-radio-button value="db" label="db">{{ $t('Store Settings in DB') }}</el-radio-button>
                <el-radio-button value="wp_config" label="wp_config">{{ $t('Settings in Config File') }}</el-radio-button>
            </el-radio-group>
        </div>

        <template v-if="connection.key_store == 'wp_config'">
            <div class="fss_condesnippet_wrapper">
                <el-form-item>
                    <label style="font-weight: 600; font-size: 14px; margin-bottom: 8px; display: block;">
                        {{ $t('Copy & paste the following snippet into your wp-config.php file:') }}
                    </label>
                    <div class="code_snippet_container" style="background: #1e1e1e; border-radius: 8px; padding: 16px; margin-bottom: 20px; font-family: monospace; color: #abb2bf;">
                        <textarea readonly style="width: 100%; height: 180px; background: transparent; border: none; color: #5cb85c; font-family: 'Courier New', Courier, monospace; font-size: 13px; outline: none; resize: none; line-height: 1.5;" @focus="$event.target.select()">// Required settings
define( 'FLUENTMAIL_SMTP_HOST_{{ connectionId }}', '{{ connection.host || "mail.example.com" }}' );
define( 'FLUENTMAIL_SMTP_USERNAME_{{ connectionId }}', '{{ connection.username || "user@example.com" }}' );
define( 'FLUENTMAIL_SMTP_PASSWORD_{{ connectionId }}', '{{ connection.password || "your-smtp-password" }}' );

// Optional settings (defaults shown)
define( 'FLUENTMAIL_SMTP_PORT_{{ connectionId }}', {{ connection.port || 587 }} );
define( 'FLUENTMAIL_SMTP_ENCRYPTION_{{ connectionId }}', '{{ connection.encryption || "tls" }}' );
define( 'FLUENTMAIL_SMTP_AUTH_{{ connectionId }}', '{{ connection.auth || "yes" }}' );
define( 'FLUENTMAIL_SMTP_SENDER_NAME_{{ connectionId }}', '{{ connection.sender_name || "My Site" }}' );

// Required only if username is not an email
define( 'FLUENTMAIL_SMTP_SENDER_EMAIL_{{ connectionId }}', '{{ connection.sender_email || "sender@example.com" }}' );</textarea>
                    </div>

                    <!-- Live Status Panel -->
                    <div class="live_status_panel" style="background: #f7f9fa; border: 1px solid #e1e4e6; border-radius: 8px; padding: 16px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 12px 0; font-size: 14px; color: #2c3e50;">{{ $t('Live Status (Resolved from wp-config.php)') }}</h4>
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px; color: #555;">
                            <tbody>
                                <tr style="border-bottom: 1px solid #eaecef; height: 32px;">
                                    <td style="font-weight: 600; width: 40%;">Host</td>
                                    <td>
                                        <el-tag size="mini" :type="connection.host ? 'success' : 'danger'">
                                            {{ connection.host ? connection.host : 'Missing (Required)' }}
                                        </el-tag>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid #eaecef; height: 32px;">
                                    <td style="font-weight: 600;">Port</td>
                                    <td>
                                        <el-tag size="mini" type="info">{{ connection.port || 587 }}</el-tag>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid #eaecef; height: 32px;">
                                    <td style="font-weight: 600;">Encryption</td>
                                    <td>
                                        <el-tag size="mini" type="info">{{ connection.encryption || 'tls' }}</el-tag>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid #eaecef; height: 32px;">
                                    <td style="font-weight: 600;">Username</td>
                                    <td>
                                        <el-tag size="mini" :type="connection.username ? 'success' : 'danger'">
                                            {{ connection.username ? connection.username : 'Missing (Required)' }}
                                        </el-tag>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid #eaecef; height: 32px;">
                                    <td style="font-weight: 600;">Password</td>
                                    <td>
                                        <el-tag size="mini" :type="connection.password && connection.password !== '********************' ? 'success' : 'danger'">
                                            {{ connection.password ? 'Configured' : 'Missing (Required)' }}
                                        </el-tag>
                                    </td>
                                </tr>
                                <tr style="border-bottom: 1px solid #eaecef; height: 32px;">
                                    <td style="font-weight: 600;">Sender Name</td>
                                    <td>
                                        <span style="color: #666;">{{ connection.sender_name || 'My Site' }}</span>
                                    </td>
                                </tr>
                                <tr style="height: 32px;">
                                    <td style="font-weight: 600;">Sender Email</td>
                                    <td>
                                        <el-tag size="mini" :type="connection.sender_email ? 'success' : 'danger'">
                                            {{ connection.sender_email ? connection.sender_email : 'Missing' }}
                                        </el-tag>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <error :error="errors.get('host')"/>
                    <error :error="errors.get('username')"/>
                    <error :error="errors.get('password')"/>
                    <error :error="errors.get('sender_email')"/>
                </el-form-item>
            </div>
        </template>

        <template v-else>
            <el-row :gutter="20">
                <el-col :md="12" :sm="24">
                    <el-form-item>
                        <label for="host">
                            {{ $t('SMTP Host') }}
                        </label>
                        <el-input :placeholder="$t('SMTP Host')" id="host" v-model="connection.host"/>
                        <error :error="errors.get('host')"/>
                    </el-form-item>
                </el-col>
                <el-col :md="12" :sm="24">
                    <el-form-item>
                        <label for="port">
                            {{ $t('SMTP Port') }}
                        </label>

                        <el-input :placeholder="$t('SMTP Port')" id="port" v-model="connection.port"/>
                        <error :error="errors.get('port')"/>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="20">
                <el-col :span="24">
                    <el-form-item style="margin: 20px 0">
                        <label>
                            {{ $t('Encryption') }}
                        </label>

                        <div class="small-help-text" style="display:inline-block;">
                            Select <strong>ssl</strong> on port <strong>465</strong>, or <strong>tls</strong> on port <strong>25</strong> or <strong>587</strong>
                        </div>

                        <div style="display:inline-block;margin-left: 20px;">
                            <el-radio v-model="connection.encryption" label="none">{{ $t('None') }}</el-radio>
                            <el-radio v-model="connection.encryption" label="ssl">{{ $t('SSL') }}</el-radio>
                            <el-radio v-model="connection.encryption" label="tls">{{ $t('TLS') }}</el-radio>
                        </div>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="20">
                <el-col :span="24">
                    <el-form-item>
                        <label for="auth">
                            {{ $t('Use Auto TLS') }}
                        </label>

                        <el-switch
                            v-model="connection.auto_tls"
                            active-value="yes"
                            inactive-value="no">
                        </el-switch>

                        <span class="small-help-text">
                            {{ $t('__TLS_HELP') }}
                        </span>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="20">
                <el-col :span="24">
                    <el-form-item>
                        <label for="auth">
                            {{ $t('Authentication') }}
                        </label>

                        <el-switch
                            v-model="connection.auth"
                            active-value="yes"
                            inactive-value="no">
                        </el-switch>

                        <span class="small-help-text">
                            {{ $t('__SMTP_CRED_HELP') }}
                        </span>
                    </el-form-item>
                </el-col>
            </el-row>

            <template v-if="connection.auth == 'yes'">
                <el-row :gutter="20" :class="{ disabled: connection.auth==='no' }">
                    <el-col :span="12">
                        <el-form-item>
                            <label for="username">
                                {{ $t('SMTP Username') }}
                            </label>

                            <el-input type="text"
                                      id="username"
                                      :placeholder="$t('Your SMTP Username')"
                                      v-model="connection.username"
                                      :disabled="isDisabledUsername"
                            />

                            <error :error="errors.get('username')"/>
                        </el-form-item>
                    </el-col>

                    <el-col :span="12">
                        <el-form-item>
                            <label for="smtp-password">
                                {{ $t('SMTP Password') }}
                            </label>

                            <InputPassword
                                id="smtp-password"
                                v-model="connection.password"
                                :disabled="isDisabledPassword"
                                :disable_help="connection.disable_encryption === 'yes'"
                            />
                            <error :error="errors.get('password')"/>
                        </el-form-item>
                    </el-col>

                    <el-col :span="24">
                        <el-form-item>
                            <el-checkbox true-label="yes" false-label="no" v-model="connection.disable_encryption">
                                {{ $t('Disable Encryption for SMTP Password (Not Recommended)') }}
                            </el-checkbox>
                            <p style="color: red; margin-top: 0;" v-if="connection.disable_encryption === 'yes'">
                                {{
                                    $t('By disabling encryption, your API key will be stored in plain text in the database. This is not recommended for security reasons. Enable only if your security plugin rotate WP SALTS frequently.')
                                }}
                            </p>
                        </el-form-item>
                    </el-col>
                </el-row>
            </template>
        </template>
    </div>
</template>

<script>
import InputPassword from '@/Pieces/InputPassword';
import Error from '@/Pieces/Error';

export default {
    name: 'Smtp',
    props: ['connection', 'errors', 'connections'],
    components: {
        InputPassword,
        Error
    },
    data() {
        return {
            app_ready: false
        };
    },
    watch: {
        'connection.key_store'(value, oldValue) {
            if (value === 'wp_config' && oldValue !== undefined) {
                this.connection.password = '';
                this.connection.username = '';
                this.connection.host = '';
                this.connection.port = 587;
                this.connection.encryption = 'tls';
                this.connection.auth = 'yes';
            }
        }
    },
    computed: {
        isDisabledUsername() {
            return this.connection.auth === 'no';
        },
        isDisabledPassword() {
            return this.connection.auth === 'no';
        },
        connectionId() {
            if (this.connection.connection_id !== undefined && this.connection.connection_id !== null) {
                return this.connection.connection_id;
            }
            if (!this.connections) {
                return 0;
            }
            let maxId = -1;
            jQuery.each(this.connections, (key, conn) => {
                const id = conn.provider_settings?.connection_id;
                if (id !== undefined && id !== null) {
                    maxId = Math.max(maxId, parseInt(id));
                }
            });
            return maxId + 1;
        }
    },
    mounted() {
        if (!this.connection.key_store) {
            this.$set(this.connection, 'key_store', 'db');
        }
    }
};
</script>
