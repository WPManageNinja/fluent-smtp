<template>
    <i @click="dismiss()" class="el-icon el-icon-close"></i>
</template>

<script type="text/babel">
    export default {
        name: 'SubscribeDismiss',
        methods: {
            dismiss() {
                this.$post('settings/subscribe-dismiss')
                    .then(response => {
                        this.appVars.require_optin = 'no';
                    })
                    .catch((errors) => {
                        this.$notify.error(errors.responseJSON.data.message);
                    });
            }
        }
    }
</script>
