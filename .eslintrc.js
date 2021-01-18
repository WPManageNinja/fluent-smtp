// If you want to customize please check this
// https://simonkollross.de/posts/using-eslint-with-vuejs-and-laravel-mix
// To Customize .vue File Rules: https://github.com/vuejs/eslint-plugin-vue#bulb-rules
module.exports = {
    "parser": "vue-eslint-parser",
    "parserOptions": {
        "parser": "babel-eslint",
        "ecmaVersion": 8,
        "sourceType": "module"
    },
    "extends": [
        "standard",
        "plugin:vue/essential",
    ],
    "globals": {
        "jQuery": false
    },
    "rules": {
        "vue/max-attributes-per-line": "off",
        "indent": ["error", 4],
        "vue/html-indent": ["error", 4, {
            "attribute": 1,
            "baseIndent": 1,
            "closeBracket": 0,
            "alignAttributesVertically": true,
            "ignores": []
        }],
        "vue/no-mutating-props": "off",
        "semi": "off",
        "vue/no-parsing-error": "off",
        "vue/no-textarea-mustache": "off",
        "no-trailing-spaces": "off",
        "no-new": "off",
        "no-unused-vars": "off",
        "space-before-function-paren": "off",
        "vue/script-indent": ["error", 4, { "baseIndent": 1 }]
    },
    "overrides": [
        {
            "files": ["*.vue"],
            "rules": {
                "indent": "off"
            }
        }
    ]
};
