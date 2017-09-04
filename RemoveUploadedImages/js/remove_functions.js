jQuery(document).ready(function ($) {
    $('div.allowToDelete').each(function () {
        addDeleteLink($(this));
    })
});
function addDeleteLink($div)
{
    var $span=$("<span>",{"class":"delete"});
    $span.click(removeFunction($div.children("img")[0].src));
    $span.text("x");
    $div.append($span);
}
function removeFunction(source)
{
    var func=function()
    {
        if(confirm("Are you sure you wish to delete this image?")){
            jQuery.post(gdn.url("plugin/removeupload/"), {"source":source},
            function(data)
            {
                if(data.status=="success")
                {
                    alert("Deletion was a success! Refreshing page...");
                    location.reload();
                }
                else
                {
                    alert("Something went wrong and image couldn't be deleted...");
                }
            },"json");
    }
    };
    return func;
}
