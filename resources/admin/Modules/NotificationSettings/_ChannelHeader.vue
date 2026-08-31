<template>
    <!--
        The head of the setup form that replaces the channel list inside the card. The
        way back comes first, because it is the only way back - the list it covers is
        gone until this is closed.
    -->
    <div class="fsm_chan_head">
        <el-button @click="goBack()" size="small" link class="fsm_chan_back"
                   icon="FsmIconArrowLeft">{{ $t('Back to Alerts') }}</el-button>
        <div class="fsm_chan_head_main">
            <img v-if="logo" class="fsm_chan_head_logo" :src="logo" :alt="displayTitle"/>
            <h3 class="fsm_chan_head_title">{{ displayTitle }}</h3>
            <span v-if="connected" class="fsm_tag is_sent">{{ $t('Connected') }}</span>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'ChannelHeader',
    props: {
        title: {
            type: String,
            required: false
        },
        channelTitle: {
            type: String,
            required: false
        },
        logo: {
            type: String,
            default: ''
        },
        connected: {
            type: Boolean,
            default: false
        }
    },
    computed: {
        displayTitle() {
            if (this.title) {
                return this.title;
            }
            if (this.channelTitle) {
                return this.connected
                    ? this.channelTitle + ' ' + this.$t('Notifications')
                    : this.channelTitle + ' ' + this.$t('Settings');
            }
            return '';
        }
    },
    methods: {
        goBack() {
            this.$emit('back');
        }
    }
}
</script>
