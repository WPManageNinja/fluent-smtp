<template>
    <!--
        The extra From addresses one connection may send as, in a dialog of their own.

        This used to live inside the Connection Details panel, which meant reaching it
        took three clicks and knowing it was there: open the connection, wait for the
        provider round-trip that panel makes, then press a button that only appeared once
        that call came back. Lifted out, the same dialog is opened straight from the
        connections list, and Connection Details opens this one rather than keeping a
        second copy of it.
    -->
    <el-dialog v-model="visible" :title="$t('Manage Additional Senders')" width="50%"
               @open="fetchSettings">
        <div v-loading="loading" style="min-height: 120px;">
            <template v-if="verificationSettings && verificationSettings.verified_domain">
                <p style="margin-top: 0;">
                    {{ $t('You may add additional sending emails in this') }}
                    {{ verificationSettings.connection_name }}{{ $t(' connection.') }}
                </p>

                <el-input type="text"
                          :placeholder="$t('Enter new email address ex: new_sender@') + verificationSettings.verified_domain"
                          v-model="newSender"
                          @keyup.enter="addNewSender">
                    <template #append>
                        <el-button :disabled="addingNew" v-loading="addingNew" @click="addNewSender"
                                   type="primary" icon="FsmIconPlus">
                            {{ $t('Add') }}
                        </el-button>
                    </template>
                </el-input>

                <p v-if="verificationSettings.email_help_message"
                   v-html="verificationSettings.email_help_message"></p>
                <p v-else>
                    {{ $t('The email address must match the domain: ') }}
                    <code>{{ verificationSettings.verified_domain }}</code>
                </p>

                <hr/>

                <h3>{{ $t('Current verified senders:') }}</h3>
                <table class="wp-list-table widefat striped">
                    <tbody>
                    <tr v-for="sender in verificationSettings.all_senders" :key="sender">
                        <th>
                            {{ sender }}
                            <el-button plain
                                       v-if="verificationSettings.verified_senders.indexOf(sender) === -1 || verificationSettings.supports_multi_domain"
                                       type="danger" size="small" @click="removeSender(sender)">
                                {{ $t('Remove') }}
                            </el-button>
                        </th>
                    </tr>
                    </tbody>
                </table>
            </template>

            <!--
                Opened from the list, the dialog is the first thing that asks the provider
                whether this account can take extra senders - so it is also the first place
                the answer "not yet" can be given, and it has to be given in words. Silence
                or an empty table would read as a broken dialog rather than as an account
                with no verified domain on it.
            -->
            <el-alert v-else-if="!loading" type="info" :closable="false">
                {{ $t('No verified sending domain was found on this connection, so there are no additional senders to add yet. Verify a domain with your email provider first.') }}
            </el-alert>
        </div>
    </el-dialog>
</template>

<script type="text/babel">
export default {
    name: 'SenderManager',
    props: {
        modelValue: Boolean,
        connection_id: String
    },
    emits: ['update:modelValue', 'updated'],
    data() {
        return {
            loading: false,
            addingNew: false,
            newSender: '',
            verificationSettings: null
        };
    },
    computed: {
        visible: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit('update:modelValue', value);
            }
        }
    },
    methods: {
        /*
         * connection_info is what asks the provider for its verified identities, and it
         * is a live API call on the site's behalf - so it is made when the dialog opens
         * rather than when the row that offers it is drawn.
         */
        fetchSettings() {
            this.loading = true;
            this.newSender = '';

            this.$get('settings/connection_info', {
                connection_id: this.connection_id
            })
                .then(response => {
                    this.verificationSettings = response.data.verificationSettings || null;
                })
                .catch(errors => {
                    this.verificationSettings = null;
                    this.$notify.error(this.$errorMessage(errors));
                })
                .always(() => {
                    this.loading = false;
                });
        },

        addNewSender() {
            const email = (this.newSender || '').trim();

            if (!email) {
                this.$notify.error({
                    title: 'Error',
                    message: this.$t('Please enter a valid email address')
                });
                return;
            }

            if (this.verificationSettings.all_senders.indexOf(email) > -1) {
                this.$notify.error({
                    title: 'Error',
                    message: this.$t('The email address already exists in the list')
                });
                return;
            }

            if (!this.verificationSettings.supports_multi_domain && email.split('@')[1] !== this.verificationSettings.verified_domain) {
                this.$notify.error({
                    title: 'Error',
                    message: this.$t('The email address must match the domain: ') + this.verificationSettings.verified_domain
                });
                return;
            }

            this.addingNew = true;
            this.$post('settings/add_new_sender_email', {
                connection_id: this.connection_id,
                new_sender: email
            })
                .then(response => {
                    this.$notify.success(response.data.message);
                    this.newSender = '';
                    this.fetchSettings();
                    this.$emit('updated');
                })
                .catch(errors => {
                    this.$notify.error({
                        title: 'Validation Failed',
                        message: this.$errorMessage(errors)
                    });
                })
                .always(() => {
                    this.addingNew = false;
                });
        },

        removeSender(email) {
            this.$confirm(this.$t('Are you sure you want to remove this email address?'), 'Warning', {
                confirmButtonText: 'Yes',
                cancelButtonText: 'No',
                type: 'warning'
            }).then(() => {
                this.loading = true;
                this.$post('settings/remove_sender_email', {
                    connection_id: this.connection_id,
                    email: email
                })
                    .then(response => {
                        this.$notify.success(response.data.message);
                        this.$emit('updated');
                    })
                    .catch(errors => {
                        this.$notify.error({
                            title: 'Validation Failed',
                            message: this.$errorMessage(errors)
                        });
                    })
                    .always(() => {
                        this.loading = false;
                        this.fetchSettings();
                    });
            })
                /*
                 * ElMessageBox rejects on cancel and on close, so without this every
                 * dismissal of the confirmation threw an uncaught 'cancel'.
                 */
                .catch(() => {});
        }
    }
}
</script>
