function initializeCNGames(id)
{
    var game = document.getElementById(id);
    if (game)
    {
        var paramInputs = game.getElementsByTagName("input");
        var params = [];
        for (var i = 0; i < paramInputs.length; i++)
        {
            params[paramInputs[i].name] = paramInputs[i].value;
        }
        if(params['CNUserRole']=== undefined)
        {
            return;
        }
        if (params['CNUserRole'] == 0)
        {
            game.appendChild(getPlayerButton(id, params['CNGameID'], params['CNTurn']));
            game.appendChild(getMasterButton(id, params['CNGameID'], params['CNTurn']));
        } else if (params['CNUserRole'] == params['CNTurn'])
        {
            var gameBoard = game.getElementsByClassName("CNGame");
            if (gameBoard[0])
            {
                var cards = gameBoard[0].getElementsByClassName("Card");
                for (var i = 0; i < cards.length; i++)
                {
                    if(cards[i].className==='Card'){
                    cards[i].style.cursor = "pointer";
                    cards[i].onclick = function () {
                        var card = cards[i];
                        var gameid=params['CNGameID'];
                        return function ()
                        {
                            cardclicked(gameid, card.innerHTML);
                        }
                    }();
                    }
                }
                if(params['CNSkipRequests']>-1){
                game.appendChild(getSkipButton(id, params['CNGameID'],params['CNSkipRequests'],game));}
            }
        }
    }
}
function cardclicked(gameID, word)
{
    if (confirm("Are you sure you want to check agent " + word.charAt(0).toUpperCase() + word.slice(1) + "?"))
    {
        $.get(gdn.url('plugin/codenames/save/' + gameID + '/' + word), function (data) {
        location.reload();
        }
        );

    }
}
function getPlayerButton(id, gameid, team)
{
    var teamColor = (team == 1 ? "Red" : "Blue");
    var button = document.createElement("input");
    button.type = "button";
    button.value = "Start playing as player on team " + teamColor;
    button.onclick = function ()
    {
        joinTeam(gameid, team);
    };
    return button;
}
function getMasterButton(id, gameid, team)
{
    var teamColor = (team == 1 ? "Red" : "Blue");
    var button = document.createElement("input");
    button.type = "button";
    button.value = "Start playing as Masterspy on team " + teamColor;
    button.onclick = function ()
    {
        joinTeam(gameid, (2+parseInt(team)));
    };
    return button;
}
function joinTeam(game, role) {
    $.get(gdn.url('plugin/codenames/enlist/' + game + '/' + role), function (data) {
        location.reload();
    }
    );
}
function getSkipButton(id,game,numRequests,parent){
    var button = document.createElement("input");
    button.type = "button";
    button.value = "Vote to skip remaining guesses. There already have been "+numRequests+" requests.";
    button.onclick = function ()
    {
    $.get(gdn.url('/plugin/codenames/skip/' + game), function (data) {
        if(data.votes==0){
            location.reload();
        }
        else
        {
            alert("The number of votes to skip the turn is currently: "+data.votes);
            parent.removeChild(button);
        }
    },'json');
    };
    return button;
}