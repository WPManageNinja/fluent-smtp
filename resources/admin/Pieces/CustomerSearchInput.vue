<template>
    <a-select
        showSearch
        :value="selectedCustomer"
        labelInValue
        placeholder="Search & Select Customer"
        :defaultActiveFirstOption="false"
        :showArrow="false"
        :filterOption="false"
        @search="search"
        @change="handleChange"
        :notFoundContent="null"
    >
        <a-spin v-if="fetching" slot="notFoundContent" size="small" />
        <a-select-option v-for="d in users" :value="d.value" :key="d.value">{{d.text}}</a-select-option>
    </a-select>
</template>
<script type="text/babel">
    export default {
        name: 'customerSelector',
        data() {
            return {
                selectedCustomer: this.value,
                users: [],
                lastFetchId: 0,
                fetching: false
            }
        },
        methods: {
            search(value) {
                this.lastFetchId += 1;
                const fetchId = this.lastFetchId;
                this.users = [];
                this.fetching = true;

                if (fetchId !== this.lastFetchId) {
                    // for fetch callback order
                    return;
                }

                this.users = [
                    {
                        value: 1,
                        text: 'Jewel (cep.jewel@gmail.com)'
                    },
                    {
                        value: 2,
                        text: 'Adre (adre@gmail.com)'
                    }
                ];
                this.fetching = false;
            },
            handleChange(value) {
                this.users = [];
                this.fetching = false;
                this.selectedCustomer = value;
                this.$emit('input', value.key);
            }
        }
    }
</script>
