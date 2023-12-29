<template>
    <div v-loading="loading" style="min-height: 200px" element-loading-text="Loading Details..."
         class="fss_connection_details">
        <div v-html="connection_content"></div>

        <template v-if="verificationSettings && verificationSettings.verified_domain">
            <el-button @click="showEmailManageModal = true" type="primary">Add Additional Senders</el-button>
            <el-dialog :visible.sync="showEmailManageModal" title="Manage Additional Senders" width="50%">
                <p style="font-size: 16px;">You may add additional sending emails in this
                    {{ verificationSettings.connection_name }} connection.</p>

                <el-input type="text"
                          :placeholder="'Enter new email address ex: new_sender@'+verificationSettings.verified_domain"
                          v-model="newSender">
                    <el-button :disabled="addingNew" v-loading="addingNew" @click="addNewSender()" slot="append" type="primary" icon="el-icon-plus">Add</el-button>
                </el-input>

                <p>The email address must match the domain: <code>{{ verificationSettings.verified_domain }}</code></p>

                <hr/>

                <h3>Current verified senders:</h3>
                <table v-loading="loading" class="wp-list-table widefat striped">
                    <tbody>
                    <tr v-for="sender in verificationSettings.all_senders" :key="sender">
                        <th>{{ sender }}</th>
                    </tr>
                    </tbody>
                </table>
            </el-dialog>
        </template>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'connection_details',
    props: ['connection_id'],
    data() {
        return {
            loading: false,
            connection_content: '',
            verificationSettings: null,
            showEmailManageModal: false,
            newSender: '',
            addingNew: false
        }
    },
    methods: {
        fetchDetails() {
            this.loading = true;
            this.$get('settings/connection_info', {
                connection_id: this.connection_id
            })
                .then(response => {
                    console.log(response.data);
                    this.connection_content = response.data.info;
                    this.verificationSettings = response.data.verificationSettings;
                })
                .catch(errors => {
                    this.connection_content = errors.responseText;
                    console.log(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        addNewSender() {
            if (!this.newSender) {
                this.$notify.error({
                    title: 'Error',
                    message: 'Please enter a valid email address'
                });
                return;
            }

            // check if the newSender already exists in the list
            if (this.verificationSettings.all_senders.indexOf(this.newSender) > -1) {
                this.$notify.error({
                    title: 'Error',
                    message: 'The email address already exists in the list'
                });
                return;
            }

            // check if the email domain matches the verified domain
            if (this.newSender.split('@')[1] !== this.verificationSettings.verified_domain) {
                this.$notify.error({
                    title: 'Error',
                    message: 'The email address must match the domain: ' + this.verificationSettings.verified_domain
                });
                return;
            }

            this.addingNew = true;
            this.$post('settings/add_new_sender_email', {
                connection_id: this.connection_id,
                new_sender: this.newSender
            })
                .then(response => {
                    this.$notify.success(response.data.message);
                    this.newSender = '';
                    this.fetchDetails();
                })
                .catch(errors => {
                    this.$notify.error({
                        title: 'Validation Failed',
                        message: errors.responseJSON.data.message
                    });
                })
                .always(() => {
                    this.addingNew = false;
                });

        }
    },
    created() {
        this.fetchDetails();
    }
}
</script>
