<template>
    <!--
        The one screen you are sent to from the bar, so it says what it is like every
        other screen does, and the form is a column rather than a page-wide row: a From
        address and a recipient are short fields, and stretching them to 1300px only
        makes the label further from the value it labels.
    -->
    <div class="fsm_test">
        <div class="fsm_page_head">
            <h1 class="fsm_page_title">{{ $t('Email Test') }}</h1>
        </div>

        <div class="fsm_card fsm_test_card">
            <div class="fsm_card_head">
                <h2>{{ $t('Send Test Email') }}</h2>
            </div>

            <div class="fsm_card_body" v-if="!email_success">
                <div class="fsm_row fsm_row_stacked">
                    <div class="fsm_row_label">
                        <span class="fsm_row_title">{{ $t('From') }}</span>
                        <p>{{ $t('Enter the sender email address(optional).') }}</p>
                    </div>
                    <div class="fsm_row_control">
                        <el-select autocomplete="off" data-bwignore data-lpignore="true" data-1p-ignore
                                   :placeholder="$t('Select Email or Type')" v-model="form.from">
                            <el-option
                                v-for="(emailHash, email) in sender_emails"
                                :key="email" :label="email"
                                :value="email"
                            ></el-option>
                        </el-select>
                    </div>
                </div>

                <div class="fsm_row fsm_row_stacked">
                    <div class="fsm_row_label">
                        <span class="fsm_row_title">{{ $t('Send To') }}</span>
                        <p>{{ $t('__TEST_EMAIL_INST') }}</p>
                    </div>
                    <div class="fsm_row_control">
                        <el-input id="from" v-model="form.email" autocomplete="off" data-bwignore
                                  data-lpignore="true" data-1p-ignore name="fluentsmtp_test_to"/>
                    </div>
                </div>

                <!--
                    A switch is on or off; the On and Off labels Element can print either
                    side of it said the same thing twice. The colours went with them - they
                    were hard-coded to a green that appears nowhere else in the app.
                -->
                <div class="fsm_toggle">
                    <div class="fsm_toggle_main">
                        <el-switch v-model="form.isHtml" :aria-label="$t('HTML')"/>
                        <span class="fsm_toggle_title" @click="form.isHtml = !form.isHtml">
                            {{ $t('HTML') }}
                        </span>
                    </div>
                    <div class="fsm_toggle_body">
                        <p>{{ $t('Send this email in HTML or in plain text format.') }}</p>
                    </div>
                </div>

                <div class="fsm_row_actions">
                    <el-button
                        type="primary"
                        icon="FsmIconSPromotion"
                        :loading="loading"
                        @click="sendEmail"
                        :disabled="!maybeEnabled"
                    >{{ $t('Send Test Email') }}</el-button>

                    <el-alert
                        v-if="!maybeEnabled"
                        :closable="false"
                        type="warning"
                        show-icon
                        class="fsm_test_blocked"
                    >{{ inactiveMessage }}</el-alert>
                </div>

                <el-alert v-if="debug_info" type="error" :title="debug_info.message" show-icon/>
            </div>

            <div v-else class="fsm_card_body">
                <div class="success_wrapper">
                    <h1><el-icon><FsmIconSuccess /></el-icon></h1>
                    <h3>{{ $t('Test Email Has been successfully sent') }}</h3>
                    <p v-if="time_taken_human" class="small-help-text">
                        <el-icon><FsmIconTimer /></el-icon> {{ time_taken_human }}
                    </p>
                    <hr />
                    <div v-if="appVars.require_optin == 'yes'" style="margin-top: 10px;">
                        <email-subscriber />
                    </div>
                    <el-button v-else @click="email_success = false">{{ $t('Run Another Test Email') }}</el-button>

                    <div v-if="appVars.require_optin != 'yes'" style="margin-top: 50px;">
                        {{ $t('If you have a minute, consider ') }} <a target="_blank" href="https://wordpress.org/support/plugin/fluent-smtp/reviews/?filter=5">{{ $t('write a review for FluentSMTP') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
    import isEmpty from 'lodash/isEmpty'
    import EmailSubscriber from '../../Pieces/_Subscribe';

    export default {
        name: 'EmailTest',
        components: {
            EmailSubscriber
        },
        data() {
            return {
                loading: false,
                debug_info: '',
                form: {
                    from: '',
                    email: '',
                    isHtml: true
                },
                email_success: false,
                time_taken_human: ''
            };
        },
        methods: {
            sendEmail() {
                this.loading = true;
                this.debug_info = '';
                this.time_taken_human = '';

                this.$post('settings/test', { ...this.form }).then(res => {
                    this.time_taken_human = res.data.time_taken_human || '';
                    this.$notify.success({
                        title: this.$t('Great!'),
                        offset: 19,
                        message: this.time_taken_human
                            ? `${res.data.message} (${this.time_taken_human})`
                            : res.data.message
                    });
                    this.email_success = true;
                }).fail(res => {
                    if (Number(res.status) === 504) {
                        return this.$notify.error({
                            title: this.$t('Oops!'),
                            offset: 19,
                            message: '504 Gateway Time-out.'
                        });
                    }

                    const responseJSON = res.responseJSON;

                    if (responseJSON.data.email_error) {
                        return this.$notify.error({
                            title: this.$t('Oops!'),
                            offset: 19,
                            message: responseJSON.data.email_error
                        });
                    }
                    this.debug_info = responseJSON.data;
                }).always(() => {
                    this.loading = false;
                });
            }
        },
        computed: {
            active: function() {
                if (this.settings.misc.is_inactive === 'yes') {
                    return false;
                }
                return true;
            },
            inactiveMessage() {
                const msg = 'Plugin is not configured properly.';

                return msg;
            },
            maybeEnabled() {
                return !isEmpty(this.settings.connections);
            },
            sender_emails() {
                return this.settings.mappings;
            }
        },
        created() {
            this.form.email = this.settings.user_email;
        }
    };
</script>
