jQuery(document).ready(function ($) {
    $('[data-countdown]').each(function () {
        createFlipClock($(this));
    })
});
function createFlipClock($this)
{
    var diff=$this.data('countdown')-(new Date).getTime()/1000;
    if(diff<0)
    {
        diff=0;
    }
    type=getClockTypeFromTime(diff);
    var clock = $this.FlipClock(diff, {
        countdown: true,
        clockFace: type
                //showSeconds: false
    });
}

function getClockTypeFromTime(time) {
        if (time < 3600) {
            return 'MinuteCounter';
        }
        if (time < 3600 * 24) {
            return 'HourlyCounter';
        }
        return 'DailyCounter';
    }
