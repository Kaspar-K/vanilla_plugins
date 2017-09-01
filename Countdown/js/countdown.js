jQuery(document).ready(function($) {
    $('[data-countdown]').each(function() {
    createFlipClock($(this));
})});
function createFlipClock($this)
{
    var clock = $this.FlipClock($this.data('countdown'), {
					clockFace: 'DailyCounter'
					//showSeconds: false
				});
}
