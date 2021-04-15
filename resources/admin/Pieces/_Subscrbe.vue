<template>
    <div class="fst_subscribe_form">
        <template v-if="!subscribed">
            <p style="margin-top: 0;">
                Subscribe with your email to know about this plugin updates, releases and useful tips.
            </p>
            <div class="fsmtp_subscribe">
                <el-input v-model="email" placeholder="Your Email Address"/>
                <el-checkbox true-label="yes" false-label="no" v-model="share_details">
                    (Optional) Share Non-Sensitive Data. It will help us to improve the integrations
                    <el-tooltip class="item" effect="dark" content="Access Data: Active SMTP Connection Provider, installed plugin names, php & mysql version" placement="top-end">
                        <i class="el-icon el-icon-info"></i>
                    </el-tooltip>
                </el-checkbox>
                <el-button style="margin-top: 10px;" v-loading="saving" :disabled="saving" @click="subscribeToEmail()" type="success" size="small">
                    Subscribe To Updates
                </el-button>
            </div>
        </template>
        <div style="text-align: center;" v-else>
            <p>Awesome! You are subscribed. We will only send you updates emails and some tips as monthly basis.</p>
        </div>
    </div>
</template>

<script type="text/babel">
    export default {
        name: 'SubscriberForm',
        data() {
            return {
                email: window.FluentMailAdmin.user_email,
                share_details: 'no',
                saving: false,
                subscribed: false
            }
        },
        methods: {
            subscribeToEmail() {
                if (!this.email) {
                    this.$notify.error('Please Provide an email');
                    return false;
                }

                this.saving = true;
                this.$post('settings/subscribe', {
                    email: this.email,
                    share_essentials: this.share_details
                })
                    .then(response => {
                        this.subscribed = true;
                        this.appVars.require_optin = 'no';
                        this.$notify.success(response.data.message);
                    })
                    .catch((errors) => {
                        this.$notify.error(errors.responseJSON.data.message);
                    })
                    .always(() => {
                        this.saving = false
                    });
            }
        }
    }
</script>
