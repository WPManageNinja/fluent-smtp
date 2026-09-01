<template>
    <div class="fss_connection_wizard">
        <el-form :model="connection" label-position="top" autocomplete="off" data-bwignore data-lpignore="true" data-1p-ignore data-form-type="other">
            <!--
                The picker, shown while there is nothing chosen and again when `change` is
                pressed. It takes the whole width because it is fourteen logos, and the
                form it replaces has nothing to say until one of them is picked.

                `hide_chosen` is for the connection screen, which shows what you picked in
                the page heading beside the title - a place this component cannot reach.
                The dashboard's first-run wizard has no heading of its own, so it keeps
                the plate here.
            -->
            <div v-if="pickerOpen" class="fss_config_section">
                <h3 class="fs_config_title">{{ $t('Connection Provider') }}</h3>
                <connection-provider :providers="providers" :connection="connection"
                                     @picked="picker_open = false"/>
                <p v-if="!connection.provider" class="fsm_form_hint">
                    {{ $t('save_connection_error_1') }}
                </p>
                <!--
                    The way back out for someone who opened the picker to look and then
                    changed their mind. Only offered once there is something to go back
                    to - with no provider chosen the picker is the whole screen.
                -->
                <div v-else class="fsm_picker_actions">
                    <el-button size="small" @click="picker_open = false">
                        {{ $t('Cancel') }}
                    </el-button>
                </div>
            </div>

            <template v-if="connection.provider && !pickerOpen">
                <div v-if="!hide_chosen" class="fsm_provider_chosen">
                    <span class="fsm_provider_chosen_mark">
                        <img :src="providers[connection.provider].image"
                             :alt="providers[connection.provider].title"
                             :title="providers[connection.provider].title"/>
                    </span>
                    <el-button size="small" icon="FsmIconEdit" @click="picker_open = true">
                        {{ $t('change') }}
                    </el-button>
                </div>

                <div class="fss_config_section">
                    <h3 class="fs_config_title">{{ $t('Sender Settings') }}</h3>
                    <el-row :gutter="20">
                        <el-col :md="12" :sm="24">
                            <el-form-item :label="$t('From Email')">
                                <error :error="errors.get('sender_email')"/>
                                <el-input
                                    type="email"
                                    :placeholder="$t('From Email')"
                                    v-model="connection.sender_email"
                                    autocomplete="off"
                                    data-bwignore
                                    data-lpignore="true"
                                    data-1p-ignore
                                    data-form-type="other"
                                    name="fluentsmtp_sender_email"
                                ></el-input>
                                <p style="color: var(--fsm-danger-fg);" v-if="is_conflicted">{{ $t('__ANOTHER_CONNECTION_NOTICE') }}</p>
                            </el-form-item>
                            <div v-if="connection.force_from_email != undefined">
                                <el-checkbox
                                    true-value="yes"
                                    false-value="no"
                                    v-model="connection.force_from_email"
                                >
                                    {{ $t('Force From Email (Recommended Settings: Enable)') }}
                                    <el-tooltip effect="dark" placement="top-start">
                                        <template #content>
                                            <div>
                                                {{ $t('__from_email_tooltip') }}
                                            </div>
                                        </template>
                                        <el-icon><FsmIconInfo /></el-icon>
                                    </el-tooltip>
                                </el-checkbox>
                            </div>
                            <div v-if="connection.return_path != undefined">
                                <el-checkbox
                                    true-value="yes"
                                    false-value="no"
                                    v-model="connection.return_path"
                                >
                                    {{ $t('Set the return-path to match the From Email') }}
                                    <el-tooltip effect="dark" placement="top-start">
                                        <template #content>
                                            <div v-html="$t('__RETURN_PATH_TOOLTIP')">
                                            </div>
                                        </template>
                                        <el-icon><FsmIconInfo /></el-icon>
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
                                <error :error="errors.get('sender_name')"/>
                            </el-form-item>
                            <el-checkbox
                                v-model="connection.force_from_name"
                                true-value="yes"
                                false-value="no"
                            >
                                {{ $t('Force Sender Name') }}
                                <el-tooltip effect="dark" placement="top-start">
                                    <template #content>
                                        <div>
                                            {{ $t('force_sender_tooltip') }}
                                        </div>
                                    </template>
                                    <el-icon><FsmIconInfo /></el-icon>
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
                        :is_new="is_new"
                    />
                </div>
                <!--
                    A provider's caveat - "not recommended for mass marketing" - which
                    was set at 16px, larger than the form's own labels and every heading
                    on the screen bar the page title.
                -->
                <p v-if="providers[connection.provider].note" class="fsm_provider_note"
                   v-html="providers[connection.provider].note"></p>
                <el-button v-loading="saving" @click="saveConnectionSettings()" type="primary">
                    {{ $t('Save Connection Settings') }}
                </el-button>
            </template>
            <p v-if="saving">{{ $t('Validating Data. Please wait...') }}</p>
            <!--
                `error_message` is set only when the failure was not about the
                credentials - an expired nonce, a lost connection, an HTML error page.
                Falling back to the credential wording in those cases sent people off to
                re-enter a password that was never wrong.
            -->
            <el-alert style="margin-top: 20px" v-if="has_error" type="error">{{
                    error_message || $t('save_connection_error_2')
                }}
            </el-alert>
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
import Tosend from "./Partials/Providers/Tosend.vue";
import cloudflare from './Partials/Providers/Cloudflare';

export default {
    name: 'ConnectionWizard',
    props: ['connection', 'is_new', 'providers', 'connection_key', 'connections', 'hide_chosen'],
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
        ConnectionProvider,
        Tosend,
        cloudflare
    },
    data() {
        return {
            saving: false,
            picker_open: false,
            errors: new Errors(),
            api_error: '',
            error_message: '',
            has_error: false
        }
    },
    computed: {
        /* Open on purpose, or open because there is nothing to show instead. */
        pickerOpen() {
            return this.picker_open || !this.connection.provider;
        },

        is_conflicted() {
            if (!this.connections) {
                return false;
            }

            let isConflicted = false;
            each(this.connections, (existingConnection, connectionKey) => {
                if (this.connection_key != connectionKey && existingConnection.provider_settings.sender_email == this.connection.sender_email) {
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
                this.connection[key] = value;
            });
        }
    },
    methods: {
        /* Called by the connection screen, whose `change` button lives in its heading. */
        openPicker() {
            this.picker_open = true;
        },

        saveConnectionSettings() {
            this.saving = true;
            this.api_error = '';
            this.error_message = '';
            this.has_error = false;
            this.$post('settings', {
                connection: this.connection,
                connection_key: this.connection_key
            })
                .then(response => {
                    this.$notify.success(response.data.message);
                    this.settings.connections = response.data.connections;
                    this.settings.mappings = response.data.mappings;
                    this.settings.misc = response.data.misc;
                    this.$router.push({
                        name: 'connections'
                    });
                })
                /*
                 * Three different failures arrive here and only one of them is about
                 * what the user typed.
                 *
                 * An expired nonce comes back 403 with "Security Failed. Please reload
                 * the page". Recording that against the field list found nothing to
                 * attach it to - `errors.get('sender_email')` returns undefined - so no
                 * field error rendered and the template fell through to its generic
                 * "Credential Verification Failed. Please check your inputs" alert. The
                 * admin's next move was to go and reset an SMTP password that had never
                 * stopped working. It is shown as what it is instead.
                 *
                 * A transport or non-JSON failure has no field payload either, so it
                 * takes the same path rather than throwing on `.data`.
                 */
                .fail((error) => {
                    const payload = error && error.responseJSON && error.responseJSON.data;

                    if (this.$isAuthError(error) || !payload) {
                        this.error_message = this.$errorMessage(error);
                        this.has_error = true;
                        return;
                    }

                    this.errors.record(payload);
                    this.api_error = payload.api_error;
                    this.has_error = true;
                })
                .always(() => {
                    this.saving = false;
                });
        }
    }
};
</script>
