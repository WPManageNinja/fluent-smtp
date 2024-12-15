<template>
    <div>
        <div v-if="connection.provider && !showAll">
            <div :class="'con_'+connection.provider" class="fsmtp_conncection_selected">
                <img :title="providers[connection.provider].title" style="max-width:100px;height:auto;" :src="providers[connection.provider].image" />
                <i class="fstmp_check_icon">
                    <svg fill="#000000" xmlns="http://www.w3.org/2000/svg"  viewBox="0 0 26 26" width="26px" height="26px"><path d="M 13 1 C 6.382813 1 1 6.382813 1 13 C 1 19.617188 6.382813 25 13 25 C 19.617188 25 25 19.617188 25 13 C 25 6.382813 19.617188 1 13 1 Z M 13 3 C 18.535156 3 23 7.464844 23 13 C 23 18.535156 18.535156 23 13 23 C 7.464844 23 3 18.535156 3 13 C 3 7.464844 7.464844 3 13 3 Z M 17.1875 7.0625 C 17.039063 7.085938 16.914063 7.164063 16.8125 7.3125 L 11.90625 14.59375 L 9.59375 12.3125 C 9.394531 12.011719 9.011719 11.988281 8.8125 12.1875 L 7.90625 13.09375 C 7.707031 13.394531 7.707031 13.800781 7.90625 14 L 11.40625 17.5 C 11.605469 17.601563 11.886719 17.8125 12.1875 17.8125 C 12.386719 17.8125 12.707031 17.707031 12.90625 17.40625 L 18.90625 8.59375 C 19.105469 8.292969 18.992188 8.011719 18.59375 7.8125 L 17.59375 7.09375 C 17.492188 7.042969 17.335938 7.039063 17.1875 7.0625 Z"/></svg>                </i>
            </div>
            <el-button @click="showAll = !showAll" size="small" icon="el-icon-edit" type="default">{{ $t('change') }}</el-button>
        </div>
        <el-radio-group @change="showAll = false" v-else class="fss_connections" v-model="connection.provider">
            <el-radio-button :class="'con_'+providerName" v-for="(provider, providerName) in providers" :key="providerName" :label="providerName">
                <img :title="provider.title" :src="provider.image" />
            </el-radio-button>
        </el-radio-group>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'ConnectionSelector',
    props: ['connection', 'providers'],
    data() {
        return {
            showAll: !this.connection.provider
        }
    }
}
</script>
