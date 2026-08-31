<template>
    <div>
        <!--
            The body is arbitrary HTML from whoever called wp_mail(), which on
            most sites includes a public contact form. DOMPurify runs over it
            first, but this frame is the containment: without allow-scripts,
            script elements and event handlers that survive a sanitizer bypass
            still never execute, and forms and top-level navigation are refused.

            allow-same-origin is required because setBody() writes through
            contentDocument. Never add allow-scripts alongside it — the two
            together let framed content clear its own sandbox, which would put
            script execution back inside the wp-admin origin.
        -->
        <iframe
            ref="ifr"
            frameborder="0"
            sandbox="allow-same-origin"
            allowFullScreen
            mozallowfullscreen
            webkitallowfullscreen
            style="width:100%;height: 400px;"
            @load="setBody(content)"
        ></iframe>
        <el-button size="small" type="primary" icon="FsmIconFullScreen" ref="fullscreen" @click="fullScreen">
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
                if (!ifr) {
                    return;
                }

                const doc = ifr.contentDocument || ifr.contentWindow.document;
                if (doc && doc.body) {
                    doc.body.innerHTML = body;
                }
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
