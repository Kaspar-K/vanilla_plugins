jQuery(document).ready(function ($) {
    $('div.allowToDelete').each(function () {
        addDeleteLink($(this));
    })
});
function addDeleteLink($div)
{
    var $span=$("<span>",{"class":"delete"});
    $span.click(removeFunction($div,$div.children("img")));
    $span.text("x");
    $div.append($span);
}
function removeFunction($div,$image)
{
    var func=function()
    {
        var source=($image[0].src);
        jQuery.post(gdn.url("plugin/removeupload/"), {"source":source},function(data){alert(data)});
    };
    return func;
}
