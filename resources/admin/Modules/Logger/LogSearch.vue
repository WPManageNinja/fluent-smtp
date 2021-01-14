<template>
    <div style="float:right;">
        <el-input
            clearable
            size="small"
            v-model="query"
            @clear="query=''"
            @keyup.enter.native="search"
            placeholder="Type & press enter..."
        >
            <el-button slot="append" icon="el-icon-search" @click="search" />
        </el-input>
    </div>
</template>

<script>
    export default {
        name: 'LogSearch',
        data() {
            return {
                query: ''
            };
        },
        methods: {
            search() {
                if (this.query) {
                    this.$emit('on-search', this.query);
                }
            }
        },
        watch: {
            query: function(newValue, oldValue) {
                if (newValue) {
                    this.$emit('on-search-change', newValue);
                }

                if (newValue !== oldValue) {
                    this.$emit('reset-page');
                }

                if (!newValue) {
                    this.$emit('reset-page');
                    this.$emit('on-search', this.query);
                }
            }
        },
        created() {
            this.query = this.$route.query.search || this.query;
        }
    };
</script>
