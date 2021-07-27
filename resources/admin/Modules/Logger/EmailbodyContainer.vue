<template>
    <div @mouseover="onMouseOver" @mouseleave="onMouseOut">
        <span ref="fullscreen" class="full-screen-text" @click="fullScreen">
            {{$t('Enter Full Screen')}}
        </span>
        <iframe
            ref="ifr"
            frameborder="0"
            allowFullScreen
            mozallowfullscreen
            webkitallowfullscreen
            style="width:100%;height: 400px;"
        ></iframe>
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
                this.$nextTick(() => {
                    const ifr = this.$refs.ifr;
                    const doc = ifr.contentDocument || ifr.contentWindow.document;
                    doc.body.innerHTML = body;
                });
            },
            onMouseOver() {
                this.$refs.fullscreen.classList.add('show');
            },
            onMouseOut() {
                this.$refs.fullscreen.classList.remove('show');
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