<template>
    <div class="email-body-container">
        <div class="msg-body"><slot></slot></div>
        <el-button size="small" icon="el-icon-full-screen" class="fullscreen-toggle" type="primary" @click="fullscreen=!fullscreen">{{ $t(fullscreenLabel) }}</el-button>
    </div>
</template>

<style lang="scss" scoped>

.email-body-container {
    background: white;
    background-color: #eee;
    border-radius: 0.25rem;
    padding: 1rem;

    > .fullscreen-toggle {
        margin-left: auto;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease-in-out;
    }

    &:hover {
        > .fullscreen-toggle {
            opacity: 1;
            pointer-events: auto;
        }
    }

    p {
        margin: 0 0 1rem 0;
        padding: 0;
    }
}
</style>

<script>
    export default {
        name: 'EmailbodyContainer',

        data() {
            return {
                fullscreen: document.fullscreenElement != null
            }
        },

        computed: {
            fullscreenLabel() {
                return this.fullscreen ? 'Exit Full Screen' : 'Enter Full Screen';
            }
        },

        watch: {
            fullscreen(flag) {
                if (document.fullscreenEnabled && flag != (document.fullscreenElement != null)) {
                    flag ? (this.$el || document.body).requestFullscreen() : document.exitFullscreen();
                }
            }
        },

        created() {
            document.addEventListener('fullscreenchange', () => {
                this.fullscreen = document.fullscreenElement != null;
            });
        },

    }
</script>
