<template>
    <div v-loading="loading" style="min-height: 200px" :element-loading-text="$t('Loading connection details...')"
         class="fss_connection_details">
        <div v-html="connection_content"></div>

        <template v-if="verificationSettings && verificationSettings.verified_domain">
            <el-button style="margin-top: 10px;" @click="showEmailManageModal = true" type="primary">
                {{ $t('Manage Additional Senders') }}
            </el-button>

            <sender-manager v-model="showEmailManageModal"
                            :connection_id="connection_id"
                            @updated="onSendersChanged"/>
        </template>
    </div>
</template>

<script type="text/babel">
import SenderManager from './SenderManager'

export default {
    name: 'connection_details',
    props: ['connection_id'],
    components: {
        SenderManager
    },
    emits: ['senders_changed'],
    data() {
        return {
            loading: false,
            connection_content: '',
            verificationSettings: null,
            showEmailManageModal: false
        }
    },
    methods: {
        fetchDetails() {
            this.loading = true;
            this.$get('settings/connection_info', {
                connection_id: this.connection_id
            })
                .then(response => {
                    this.connection_content = response.data.info;
                    this.verificationSettings = response.data.verificationSettings;
                })
                .catch(errors => {
                    this.$notify.error(this.$errorMessage(errors));
                })
                .always(() => {
                    this.loading = false;
                });
        },

        /*
         * The panel prints the sender list itself, so a sender added or removed in the
         * dialog has to be re-read here as well - and the connections list above needs
         * to hear about it too, since the count on the row is drawn from the mappings.
         */
        onSendersChanged() {
            this.fetchDetails();
            this.$emit('senders_changed');
        }
    },
    created() {
        this.fetchDetails();
    }
}
</script>
