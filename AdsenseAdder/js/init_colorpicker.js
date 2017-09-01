(function($) {
    $.fn.setBgColor = function(hexval)
    {
        $(this).css('background-color','#' + hexval);
    };
})(jQuery);

$(function()
{
    // set color preview squares to the correct colors
    $("input[id^=Form_Plugins-dot-AdSense-dot-google_color]").each(function()
    {
        var hex = this.value;
        var sample = $('#' + this.id + '-picked');
        sample.setBgColor(hex);
    });
    
    // attach color pickers
    $("input[id^=Form_Plugins-dot-AdSense-dot-google_color]").ColorPicker
    ({
      	onBeforeShow: function()
        {
            var hex = this.value;
            var input = $(this);
            var sample = $('#' + this.id + '-picked');
	        input.ColorPickerSetColor(hex);
            sample.setBgColor(hex);
        },
        onSubmit: function(hsb, hex, rgb, el)
        {
            var input = $(el);
            var sample = $('#' + el.id + '-picked');
            input.val(hex);
            sample.setBgColor(hex);
            input.ColorPickerHide();
        }
    })
    .bind("keyup", function()
    {
        var hex = this.value;
        var input = $(this);
        var sample = $('#' + this.id + '-picked');
        input.ColorPickerSetColor(hex);
        sample.setBgColor(hex);
    });

    $("div[class=ASPicked]").ColorPicker
    ({
      	onBeforeShow: function()
        {
            var sample = $(this);
            var input = $('#' + this.id.replace('-picked',''));
            var hex = input.val();
	        sample.ColorPickerSetColor(hex);
        },
        onSubmit: function(hsb, hex, rgb, el)
        {
            var sample = $(el);
            var input = $('#' + el.id.replace('-picked',''));
            input.val(hex);
            sample.setBgColor(hex);
            sample.ColorPickerHide();
        }
    });

});
