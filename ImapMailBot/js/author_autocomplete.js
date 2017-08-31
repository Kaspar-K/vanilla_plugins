jQuery(document).ready(function ($) {
    // Enable multicomplete on selected inputs
    jQuery('.AuthorSuggest').livequery(function () {
        /// Author tag token input.
        var $author = $(this);
        $author.keyup(function () {

            if($author.val().length<1){
                return;
            }
            $.get(gdn.url('/user/tagsearch') + "&q=" + $author.val(), function (data) {
                $($author.data('autosuggestlist'))
                        .find('option')
                        .remove()
                        .end();
                data = JSON.parse(data);
                for (var i = 0; i < data.length && i<10; i++) {
                    $($author.data('autosuggestlist')).append($("<option></option>")
                            .attr("value", data[i].id)
                            .text(data[i].name));
                }
            });
        });
    });
});