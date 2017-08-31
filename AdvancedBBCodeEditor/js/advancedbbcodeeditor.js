// select the target node
var target = $("head")[0];

// create an observer instance
var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        setTimeout(initiliazeBBCodeFunctions,1000);
    });
});

// configuration of the observer:
var config = {attributes: false, childList: true, characterData: false};

// pass in the target node, as well as the observer options
observer.observe(target, config);
function addSizeTag(size)
{
    wrap("[size=" + size + "]", "[/size]");
}
function addColorTag(color)
{
    wrap("[color=" + color + "]", "[/color]");
}
function initiliazeBBCodeFunctions() {
    $('.bodybox-wrap').each(function (i, obj) {
        if (!obj.hasAttribute("hasExtendFunctions")) {
            obj.setAttribute('hasExtendFunctions', 'true');
            var body = $(obj).find('#Form_Body');
            currentBody = body.get(0);
            (function (body) {
                body.on("keypress", function (e) {
                    register_press(e, body.get(0));
                });
                body.on("keydown", function (e) {
                    register_press(e, body.get(0));
                });
                body.on("keyup", function (e) {
                    register_press(e, body.get(0));
                });
            })(body);
            $(obj).find(".editor-dialog-fire-close").each(function (j, button) {
                if (hasClass(button, "size")) {
                    (function (size) {
                        button.onclick = function () {
                            addSizeTag(size);
                        };
                    })(button.dataset.wysihtml5CommandValue);
                }
                if (hasClass(button, "underline"))
                {
                    button.onclick = function () {
                        wrap("[u]", "[/u]");
                    }
                }
                if (hasClass(button, "color"))
                {
                    (function (color) {
                        button.onclick = function () {
                            addColorTag(color);
                        };
                    })(button.dataset.wysihtml5CommandValue);

                }
            });
        }
    });

}
var map = {};
var currentBody;
function register_press(e, body)
{
    currentBody = body;
    e = e || event; // to deal with IE
    map[e.keyCode] = e.type == 'keydown';
    var ctrl = 17;
    var shift = 16;
    var zero = 48;
    var u = 85;
    var colors = {};
    colors[66] = "blue";
    colors[82] = "red";
    colors[71] = "green";
    colors[89] = "yellow";
    colors[79] = "orange";
    colors[87] = "white";
    if (map[ctrl]) {
        if (map[u])
        {
            wrap("[u]", "[/u]");
            // map = {};
            e.preventDefault();
            return false;
        }
        if (map[shift])
        {
            for (var i = 1; i < 6; i++) {
                if (map[zero + i]) {
                    addSizeTag(i);
                    // map = {};
                    e.preventDefault();
                    return false;
                }
            }
            for (var key in colors) {
                if (map[key]) {
                    addColorTag(colors[key]);
                    // map = {};
                    e.preventDefault();
                    return false;
                }
            }
        }
    }
}
function release_key(e) {
    map[e.keyCode] = false;
}
function wrap(starttag, endtag) {
    var body = currentBody;
    var startPos = body.selectionStart;
    var endPos = body.selectionEnd;
    var text = body.value;
    body.value = text.substring(0, startPos) + starttag + text.substring(startPos, endPos) + endtag + text.substring(endPos);
    body.selectionStart = startPos + starttag.length;
    body.selectionEnd = endPos + starttag.length;
    body.focus();
}
function hasClass(element, cls) {
    return (' ' + element.className + ' ').indexOf(' ' + cls + ' ') > -1;
}