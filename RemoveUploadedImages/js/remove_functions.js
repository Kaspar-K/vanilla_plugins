jQuery(document).ready(function ($) {
    $('div.allowToDelete').each(function () {
        addLargeDeleteButton($(this));
    });
    $('div.editor-file-preview.file-owner').each(function()
    {
        if($(this.parentElement.parentElement).find('div.allowDeletionInPost').length>0){
        this.style='display:block;'+this.style;
        addSmallDeleteButton($(this));}
    });
});
function addSmallDeleteButton($div)
{
        $div.addClass("allowToDelete");
        var $span=$("<span>",{"class":"delete small"});
        $span.click(removeFunction($div.find('a.filename')[0].href));
        $span.text("x");
        $div.append($span);
}
function addLargeDeleteButton($div)
{
    var $span=$("<span>",{"class":"delete large"});
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
