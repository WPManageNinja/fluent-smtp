<template>
    <div>
        <iframe
            ref="ifr"
            frameborder="0"
            allowFullScreen
            mozallowfullscreen
            webkitallowfullscreen
            style="width:100%;height: 400px;"
        ></iframe>
        <el-button size="small" type="primary" icon="el-icon-full-screen" ref="fullscreen" @click="fullScreen">
            {{$t('Enter Full Screen')}}
        </el-button>

    </div>
</template>

<script>
export default {
    name: 'EmailbodyContainer',
    props: ['content'],
    data() {
        return {
            // ...
        };
    },
    methods: {
        setBody(body) {
            if(!body) {
                body = ' ';
            }

            this.$nextTick(() => {
                const ifr = this.$refs.ifr;
                const doc = ifr.contentDocument || ifr.contentWindow.document;
                doc.body.innerHTML = body;
            });
        },
        fullScreen() {
            const d = document;
            const iframe = this.$refs.ifr;
            if (
                d.fullscreenEnabled ||
                d.webkitFullscreenEnabled ||
                d.mozFullScreenEnabled ||
                d.msFullscreenEnabled
            ) {
                if (iframe.requestFullscreen) {
                    iframe.requestFullscreen();
                } else if (iframe.webkitRequestFullscreen) {
                    iframe.webkitRequestFullscreen();
                } else if (iframe.mozRequestFullScreen) {
                    iframe.mozRequestFullScreen();
                } else if (iframe.msRequestFullscreen) {
                    iframe.msRequestFullscreen();
                }
            }
        }
    },
    watch: {
        content: {
            immediate: true,
            handler: 'setBody'
        }
    }
};
</script>
