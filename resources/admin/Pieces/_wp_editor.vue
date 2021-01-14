<template>
    <div class="wp_vue_editor_wrapper">
        <textarea @click="maybeInitAgain()" v-if="hasWpEditor" class="wp_vue_editor" :id="editor_id">{{value}}</textarea>
        <textarea v-else
                  class="wp_vue_editor wp_vue_editor_plain"
                  v-model="plain_content"
                  @click="updateCursorPos">
        </textarea>
    </div>
</template>

<script type="text/babel">

    export default {
        name: 'wp_editor',
        props: {
            editor_id: {
                type: String,
                default() {
                    return 'wp_editor_' + Date.now() + parseInt(Math.random() * 1000);
                }
            },
            value: {
                type: String,
                default() {
                    return '';
                }
            },
            editorShortcodes: {
                type: Array,
                default() {
                    return []
                }
            },
            height: {
                type: Number,
                default() {
                    return 250;
                }
            }
        },
        data() {
            return {
                hasWpEditor: !!window.wp.editor,
                hasMedia: true,
                plain_content: this.value,
                cursorPos: this.value.length,
                isLive: false
            }
        },
        watch: {
            plain_content() {
                this.$emit('input', this.plain_content);
            }
        },
        methods: {
            initEditor() {
                window.wp.editor.remove(this.editor_id);
                const that = this;
                window.wp.editor.initialize(this.editor_id, {
                    mediaButtons: that.hasMedia,
                    tinymce: {
                        height: that.height,
                        toolbar1: 'formatselect,table,bold,italic,bullist,numlist,link,blockquote,alignleft,aligncenter,alignright,underline,strikethrough,forecolor,removeformat,codeformat,outdent,indent,undo,redo',
                        setup(ed) {
                            that.isLive = true;
                            ed.on('change', function (ed, l) {
                                that.changeContentEvent();
                            });
                        }
                    },
                    quicktags: true
                });

                jQuery('#' + this.editor_id).on('change', function(e) {
                    that.changeContentEvent();
                });
            },
            maybeInitAgain() {
                if (!this.isLive && this.hasWpEditor) {
                    this.initEditor();
                }
            },
            changeContentEvent() {
                const content = window.wp.editor.getContent(this.editor_id);
                this.$emit('input', content);
            },

            handleCommand(command) {
                if (this.hasWpEditor) {
                    window.tinymce.activeEditor.insertContent(command);
                } else {
                    const part1 = this.plain_content.slice(0, this.cursorPos);
                    const part2 = this.plain_content.slice(this.cursorPos, this.plain_content.length);
                    this.plain_content = part1 + command + part2;
                    this.cursorPos += command.length;
                }
            },

            updateCursorPos() {
                var cursorPos = jQuery('.wp_vue_editor_plain').prop('selectionStart');
                this.$set(this, 'cursorPos', cursorPos);
            }
        },
        mounted() {
            if (this.hasWpEditor) {
                this.initEditor();
            }
        }
    };
</script>
 
<style lang="scss">
    .wp_vue_editor {
        width: 100%;
        min-height: 100px;
    }
    .wp_vue_editor_wrapper {
        position: relative;

        .popover-wrapper {
            z-index: 2;
            position: absolute;
            top: 0;
            right: 0;

            &-plaintext {
                left: auto;
                right: 0;
                top: -32px;
            }
        }
        .wp-editor-tabs {
            float: left;
        }
    }
</style>
