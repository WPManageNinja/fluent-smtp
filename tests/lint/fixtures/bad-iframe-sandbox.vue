<template>
    <div>
        <!-- Fixture for the iframe-sandbox lint self-test. Never built or shipped. -->

        <!-- Must fire: no sandbox attribute at all. -->
        <iframe ref="unsandboxed" frameborder="0" style="width:100%"></iframe>

        <!-- Must fire: the pair that lets framed content clear its own sandbox. -->
        <iframe
            ref="selfClearing"
            sandbox="allow-same-origin allow-scripts"
            frameborder="0"
        ></iframe>

        <!-- Must NOT fire: the shape EmailbodyContainer uses. -->
        <iframe
            ref="safe"
            sandbox="allow-same-origin"
            frameborder="0"
        ></iframe>

        <!-- Must NOT fire: scripts without same-origin cannot reach the parent. -->
        <iframe ref="alsoSafe" sandbox="allow-scripts"></iframe>
    </div>
</template>
