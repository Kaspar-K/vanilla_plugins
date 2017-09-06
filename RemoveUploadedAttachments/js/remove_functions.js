if (typeof delete_button_html === 'undefined')
{
    delete_button_html = "x";
}

jQuery(document).ready(function ($) {
    $('.Comment').each(function ()
    {
        var $comment = $(this);
        if ($comment.find('div.allowDeletionInPost').length > 0)
        {
            $comment.find('div.editor-file-preview.file-owner').each(function ()
            {
                this.style = 'display:block;' + this.style;
                addSmallDeleteButton($(this));
            });
        }
    });
});
function addSmallDeleteButton($div)
{
    $div.addClass("allowToDelete");
    var $span = $("<span>", {"class": "delete"});
    $span.click(removeFunction($div.find('a.filename')[0].href));
    $span.html(delete_button_html);
    $div.append($span);
}
function removeFunction(source)
{
    var func = function ()
    {
        if (confirm("Are you sure you wish to delete this image?")) {
            jQuery.post(gdn.url("plugin/removeupload/"), {"source": source},
                    function (data)
                    {
                        if (data.status === "success")
                        {
                            alert("Deletion was a success! Refreshing page...");
                            location.reload();
                        } else
                        {
                            alert("Something went wrong and image couldn't be deleted...");
                        }
                    }, "json");
        }
    };
    return func;
}
