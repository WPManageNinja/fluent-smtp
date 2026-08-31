<template>
    <!--
        The grid of providers, and only the grid.

        It used to hold two states - the grid, and the plate showing what you had picked
        with a `change` button under it - and switch between them on state of its own.
        That made the plate un-placeable: on the connection screen it belongs in the page
        heading beside the title, which is not where this component renders. The plate is
        the caller's now, and this is the picker.
    -->
    <el-radio-group class="fss_connections" v-model="connection.provider" @change="$emit('picked')">
        <el-radio-button :class="'con_'+providerName" v-for="(provider, providerName) in providers"
                         :key="providerName" :value="providerName">
            <img :title="provider.title" :alt="provider.title" :src="provider.image"/>
        </el-radio-button>
    </el-radio-group>
</template>

<script type="text/babel">
export default {
    name: 'ConnectionSelector',
    props: ['connection', 'providers'],
    emits: ['picked']
}
</script>
