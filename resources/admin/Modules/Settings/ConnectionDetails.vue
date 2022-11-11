<template>
    <div v-loading="loading" style="min-height: 200px" element-loading-text="Loading Details..."
         class="fss_connection_details">
        <div v-html="connection_content"></div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'connection_details',
    props: ['connection_id'],
    data() {
        return {
            loading: false,
            connection_content: ''
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
                })
                .catch(errors => {
                    this.connection_content = errors.responseText;
                    console.log(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        }
    },
    created() {
        this.fetchDetails();
    }
}
</script>
