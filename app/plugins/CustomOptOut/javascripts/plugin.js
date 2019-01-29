/*!
 * CustomOptOut Plugin
 */

$(document).ready(function () {

    // Check for CodeMirror
    if(typeof CodeMirror === "undefined") {
        return;
    }

    $("textarea.codemirror-textarea").each(function() {

        var theme = $(this).attr('data-codemirror-theme');

        CodeMirror.fromTextArea(this, {
            mode : 'css',
            lineNumbers: true,
            gutters: ["CodeMirror-lint-markers"],
            theme: (theme === "default" ? "default" : "blackboard"),
            lint: true,
            lineWrapping: true
        });
    });

    $("textarea.codemirror-textarea-js").each(function() {

        var theme = $(this).attr('data-codemirror-theme');

        CodeMirror.fromTextArea(this, {
            mode : 'javascript',
            lineNumbers: true,
            gutters: ["CodeMirror-lint-markers"],
            theme: (theme === "default" ? "default" : "blackboard"),
            lint: true,
            lineWrapping: true
        });
    });
});
