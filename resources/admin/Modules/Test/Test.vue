<template>
    <div>
        <div class="header">
            Send Test Email
        </div>
        <div class="content">
            <div class="test_form" v-if="!email_success">
                <el-form ref="form" :model="form" label-position="left" label-width="120px">

                    <el-form-item for="email" label="From">
                        <el-select placeholder="Select Email or Type" :allow-create="true" :filterable="true" v-model="form.from">
                            <el-option
                                v-for="(emailHash, email) in sender_emails"
                                :key="email" :label="email"
                                :value="email"
                            ></el-option>
                        </el-select>

                        <span class="small-help-text" style="display:block;margin-top:-10px">
                            Enter the sender email address (optional).
                        </span>
                    </el-form-item>

                    <el-form-item for="from" label="Send To">
                        <el-input id="from" v-model="form.email" />

                        <span class="small-help-text" style="display:block;margin-top:-10px">
                            Enter email address where test email will be sent (By default, logged in user email will be used if email address is not provided).
                        </span>
                    </el-form-item>

                    <el-form-item for="isHtml" label="HTML">
                        <el-switch
                            v-model="form.isHtml"
                            active-color="#13ce66"
                            inactive-color="#dcdfe6"
                            active-text="On"
                            inactive-text="Off"
                        />

                        <span class="small-help-text" style="display:block;margin-top:-10px">
                            Send this email in HTML or in plain text format.
                        </span>
                    </el-form-item>

                    <el-form-item align="left">
                        <el-button
                            type="primary"
                            size="small"
                            icon="el-icon-s-promotion"
                            :loading="loading"
                            @click="sendEmail"
                            :disabled="!maybeEnabled"
                        >Send Test Email</el-button>

                        <el-alert
                            v-if="!maybeEnabled"
                            :closable="false"
                            type="warning"
                            style="display:inline;margin-left:20px;"
                        >{{ inactiveMessage }}</el-alert>
                    </el-form-item>
                </el-form>
                <el-alert v-if="debug_info" type="error" :title="debug_info.message" show-icon />
            </div>
            <div v-else class="success_wrapper">
                <h1><i class="el-icon el-icon-success"></i></h1>
                <h3>Test Email Has been successfully sent</h3>
                <hr />
                <div v-if="appVars.require_optin == 'yes'" style="margin-top: 10px;">
                    <email-subscriber />
                </div>
                <el-button v-else @click="email_success = false" v-else>Run Another Test Email</el-button>

                <div v-if="appVars.require_optin != 'yes'" style="margin-top: 50px;">
                    If you have a minute, consider <a target="_blank" href="https://wordpress.org/support/plugin/fluent-smtp/reviews/?filter=5">write a review for FluentSMTP</a>
                </div>

            </div>
        </div>
    </div>
</template>

<script type="text/babel">
    import isEmpty from 'lodash/isEmpty'
    import EmailSubscriber from '../../Pieces/_Subscrbe';

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
                email_success: false
            };
        },
        methods: {
            sendEmail() {
                this.loading = true;
                this.debug_info = '';

                this.$post('settings/test', { ...this.form }).then(res => {
                    this.$notify.success({
                        title: 'Great!',
                        offset: 19,
                        message: res.data.message
                    });
                    this.email_success = true;
                }).fail(res => {
                    if (Number(res.status) === 504) {
                        return this.$notify.error({
                            title: 'Oops!',
                            offset: 19,
                            message: '504 Gateway Time-out.'
                        });
                    }

                    const responseJSON = res.responseJSON;

                    if (responseJSON.data.email_error) {
                        return this.$notify.error({
                            title: 'Oops!',
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
