<template>
    <section class="fsm_card fsm_test_card">
        <div class="fsm_card_head"><h2>{{ $t('Domain authentication') }}</h2></div>
        <form class="fsm_card_body" @submit.prevent="check">
            <p>{{ $t('Check SPF and DKIM records as seen by this WordPress host. Recent DNS changes may take time to appear.') }}</p>
            <div class="fsm_row fsm_row_stacked">
                <div class="fsm_row_label">
                    <label class="fsm_row_title" for="fsm-envelope-domain">{{ $t('Envelope domain (SPF)') }}</label>
                    <p>{{ $t('Use the Return-Path domain from your provider. It can differ from your From address.') }}</p>
                </div>
                <el-input id="fsm-envelope-domain" v-model="form.envelope_domain" :disabled="loading" required />
            </div>
            <div class="fsm_row fsm_row_stacked">
                <div class="fsm_row_label">
                    <label class="fsm_row_title" for="fsm-signing-domain">{{ $t('Signing domain (DKIM)') }}</label>
                    <p>{{ $t('Use the domain after d= in the DKIM-Signature header of a received email.') }}</p>
                </div>
                <el-input id="fsm-signing-domain" v-model="form.signing_domain" :disabled="loading" />
            </div>
            <div class="fsm_row fsm_row_stacked">
                <div class="fsm_row_label">
                    <label class="fsm_row_title" for="fsm-dkim-selector">{{ $t('DKIM selector') }}</label>
                    <p>{{ $t('Use the value after s= in that header, or leave blank to check SPF only.') }}</p>
                </div>
                <el-input id="fsm-dkim-selector" v-model="form.selector" :disabled="loading" />
            </div>
            <el-button native-type="submit" :loading="loading" :disabled="!form.envelope_domain.trim()">{{ $t('Check DNS records') }}</el-button>
            <div aria-live="polite">
                <p v-if="error">{{ error }}</p>
                <template v-if="result">
                    <p v-for="(record, type) in result" :key="type">
                        <strong>{{ type.toUpperCase() }}</strong> <code v-if="record.name">{{ record.name }}</code>
                        {{ message(record.status) }}
                    </p>
                    <p>{{ $t('To verify authentication for an actual send, check the SPF and DKIM results in the received message headers.') }}</p>
                </template>
            </div>
        </form>
    </section>
</template>

<script>
export default {
    props: {
        suggestedDomain: { type: String, default: '' }
    },
    data() {
        return {
            form: { envelope_domain: this.suggestedDomain, signing_domain: this.suggestedDomain, selector: '' },
            result: null,
            error: '',
            loading: false
        };
    },
    watch: {
        form: {
            deep: true,
            handler() {
                this.result = null;
                this.error = '';
            }
        }
    },
    methods: {
        check() {
            if (this.loading) return;
            this.loading = true;
            this.result = null;
            this.error = '';
            this.$post('settings/domain-authentication', { ...this.form }).then(response => {
                this.result = response.data;
            }).fail(response => {
                this.error = this.$errorMessage(response);
            }).always(() => { this.loading = false; });
        },
        message(status) {
            const messages = {
                published: this.$t('Record found.'),
                missing: this.$t('Record missing. Check the name and publish the record supplied by your provider.'),
                multiple: this.$t('Multiple matching records found. Review the records at this name with your provider.'),
                revoked: this.$t('This DKIM key is revoked. Ask your provider for the current selector.'),
                incomplete: this.$t('The DKIM record is incomplete. Compare it with the key supplied by your provider.'),
                skipped: this.$t('Enter a signing domain and selector to check DKIM.'),
                unavailable: this.$t('DNS lookup unavailable. Try again or check with your host.')
            };
            return messages[status];
        }
    }
};
</script>
