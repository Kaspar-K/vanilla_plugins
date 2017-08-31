function initializeCanvasses()
{
    $(".SketchGameCanvas").each(function (index, object) {
        if (!object.hasAttribute('initialized')) {
            object.setAttribute('initialized', true);
            var canvas = $(object).find("canvas")[0];
            canvas.setAttribute('widthBrush',4);
            canvas.setAttribute('backgroundColor',"black");
            var rect = $(object).find(".rect")[0];
            var time = $(object).find(".time")[0];
            var link = $(object).find(".submitPic")[0];
            $(canvas).bind('mouseenter', startShowingRect(rect));
            $(canvas).bind('mousemove', mouseMoved(rect));
            $(canvas).bind('mousedown', mouseDown(rect, time));
            $(canvas).bind('mouseup', mouseUp());
            $(canvas).bind('mouseout', mouseLeave(rect));
            $(object).find('.bigbrush').bind('click',getBrushEvent(canvas,10));
            $(object).find('.smallbrush').bind('click',getBrushEvent(canvas,4));
            $(object).find('.bigeraser').bind('click',getEraserEvent(canvas,10));
            $(object).find('.smalleraser').bind('click',getEraserEvent(canvas,4));
        }
    });
}
function getBrushEvent(canvas,width)
{
    return function(event)
    {
        setBrush(canvas,width);
    }
}
function getEraserEvent(canvas,width)
{
    return function(event)
    {
        eraserMode(canvas,width);
    }
}

function uploadPic(canvas) {
    share(canvas.toDataURL(), canvas.dataset.sketchid,canvas.parentElement);
}
function detectLeftButton(event) {
    if ('buttons' in event) {
        return event.buttons === 1;
    } else if ('which' in event) {
        return event.which === 1;
    } else {
        return event.button === 1;
    }
}
function countSecond(canvas, time)
{
    var minutes_remaining = canvas.dataset.minutes;
    var seconds_remaining = canvas.dataset.seconds;
    seconds_remaining--;
    if (seconds_remaining < 0)
    {
        minutes_remaining--;
        seconds_remaining = 59;
    }
    canvas.dataset.minutes= minutes_remaining;
    canvas.dataset.seconds= seconds_remaining;
    if (minutes_remaining < 0) {
        window.clearInterval(canvas.getAttribute("timer"));
        uploadPic(canvas);
        $(canvas).off();
    } else if (seconds_remaining < 10) {
        time.innerHTML = "" + minutes_remaining + ":0" + seconds_remaining;
    } else {
        time.innerHTML = "" + minutes_remaining + ":" + seconds_remaining;
    }
}

var mousedown = false;
function mouseDown(rect, time)
{
    return function (event) {
        var canvas = event.target;
        if (!canvas.hasAttribute('timer')) {
            event.target.setAttribute('timer', window.setInterval(
                    function () {
                        countSecond(canvas, time);
                    }, 1000));
        }
        mousedown = detectLeftButton(event);
        event.eventName = "mousemove";
        mouseMoved(rect)(event);
    }
}
function startShowingRect(rect)
{
    return function (event) {
        var div = rect;
        div.style.display = "block";
        var widthBrush=parseInt(event.target.getAttribute('widthBrush'));
        div.style.backgroundColor = backgroundColor;
        div.style.width = widthBrush - 2;
        div.style.height = widthBrush - 2;
    }
}
var backgroundColor = "black";
function mouseMoved(rect) {
    return function (event) {
        var div = $(rect);
        var widthBrush=parseInt(event.target.getAttribute('widthBrush'));
        div.offset({left: event.pageX - widthBrush / 2, top: event.pageY - widthBrush / 2});
        if (mousedown) {
            toDo(event);
        }
    }
}
function mouseLeave(rect)
{
    return function (event) {
        var div = rect;
        div.style = "";
        div.style.display = "none";
        document.body.onmousemove = "";
        mousedown = false;
    }
}
function draw(event) {
    var canvas = event.target;
    var widthBrush=parseInt(canvas.getAttribute('widthBrush'));
    var context = canvas.getContext("2d");
    var offset = $(canvas).offset();
    var X = event.pageX - offset.left - 1;
    var Y = event.pageY - offset.top - 1;
    context.fillRect(X - widthBrush / 2, Y - widthBrush / 2, widthBrush, widthBrush);
}
var toDo = draw;
function mouseUp()
{
    return function (event) {
        mousedown = false;
    }
}
function clear(event) {
    var canvas = event.target;
    var context = canvas.getContext("2d");
    var offset = $(canvas).offset();
    var widthBrush=parseInt(canvas.getAttribute('widthBrush'));
    var X = event.pageX - offset.left - 1;
    var Y = event.pageY - offset.top - 1;
    context.clearRect(X - widthBrush / 2 + 1, Y - widthBrush / 2 + 1, widthBrush - 2, widthBrush - 2);
}
function setBrush(canvas,width)
{
    toDo = draw;
    canvas.setAttribute('widthBrush', width);
    canvas.setAttribute('backgroundColor',"black");
}
function eraserMode(canvas,width)
{
    toDo = clear;
    canvas.setAttribute('widthBrush', width);
    canvas.setAttribute('backgroundColor',"");
}