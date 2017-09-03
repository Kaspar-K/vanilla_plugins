<?php

if (!defined('APPLICATION')) {
    exit();
}

$PluginInfo['Avalon'] = array(
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'Description' => 'Lets you play avalon. Get instructions on proper usage by posting a post containing this command: [pluginexplanation]Avalon[/pluginexplanation]',
    'HasLocale' => true,
    'MobileFriendly' => TRUE,
    'Name' => 'Avalon',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('VoteAggregator' => '1.0'),
    'RegisterPermissions' => ['Plugins.Avalon.RunGame' => 'Garden.Moderation.Manage'],
    'Version' => '1.0');

class AvalonPlugin extends Gdn_Plugin {

    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex = $Sender->EventArguments['CommandIndex'];
        $commands = [
            "[avstartgame]User1,User2,User3,User4,User5[/avstartgame]" => t("Start a game with Users 1 t/m 5."),
            "[avendgame]" => t("End a game and show everyone who had what role."),
            "[avmission]W/F[/avmission]" => t("Log the result of a mission. (W)in or (F)ail."),
            "[vastartpoll=reveal]" . T("Avalon voting round") . "[/vastartpoll]" => t("Start a poll to accept or reject a team."),
            "[vastartpoll=hidden]" . T("Avalon mission round") . "[/vastartpoll]" => t("Add a poll to determine the success of the mission.")];
        $commandIndex->addCommands($commands, $this);
        if (gdn::pluginManager()->isEnabled("VoteAggregator")) {
            (new VoteAggregatorPlugin())->PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args);
        }
    }

    const Goodguy = 1;
    const Merlijn = 2;
    const Percival = 3;
    const Mordred = -1;
    const Morgana = -self::Merlijn;
    const Oberon = -3;
    const Badguy = -4;

    private $currentGameID;

    function __construct() {
        $this->currentGameID = false;
        $this->currentPost = false;
        if (!t('AvalonPlugin.Explanation', "")) {
            require_once __DIR__ . '/locale/en.php';
        }
    }

    public function canAlwaysEdit() {
        return checkPermission('Plugins.Avalon.RunGame');
    }

    public function getCurrentGame() {
        if ($this->currentGameID) {
            return $this->currentGameID;
        }
        $activeGame = Gdn::sql()->select("GameID")->from("AvGames")->where(['ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'Done' => 0])->orderBy("GameID", "desc")->limit(1)->get()->firstRow();
        if ($activeGame) {
            return ($this->currentGameID = $activeGame->GameID);
        }
        return false;
    }

    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        $ownsPost = $this->currentPost->currentUserIsOwner();
        if ($ownsPost || $this->canAlwaysEdit()) {
            $BBCode->addRule('avstartgame', Array(
                'mode' => BBCODE_MODE_CALLBACK,
                'method' => Array($this, 'startGameTag'),
                'class' => 'block',
                'allow_in' => Array('listitem', 'block', 'columns'),
            ));
            $BBCode->addRule('avendgame', Array(
                'mode' => BBCODE_MODE_CALLBACK,
                'method' => Array($this, 'endGameTag'),
                'class' => 'block',
                'allow_in' => Array('listitem', 'block', 'columns'),
                'end_tag' => BBCODE_PROHIBIT
            ));
            $BBCode->addRule('avmission', Array(
                'mode' => BBCODE_MODE_CALLBACK,
                'method' => Array($this, 'setResultOfMission'),
                'class' => 'block',
                'allow_in' => Array('listitem', 'block', 'columns'),
            ));
            $BBCode->addRule('avgame', Array(
                'mode' => BBCODE_MODE_CALLBACK,
                'method' => Array($this, 'detectGameTag'),
                'class' => 'block',
                'allow_in' => Array('listitem', 'block', 'columns'),
            ));
        }
    }

    function startGameTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $users = explode(',', $content);
        $html = $this->startAvalonGame($users);
        return $html;
    }

    function endGameTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $gameID = isset($params['_default']) && is_numeric($params['_default']) ? intval($params['_default']) : $this->getCurrentGame();
        if ($gameID) {
            gdn::sql()->update("AvGames")->set("Done", 1)->where(["GameID" => $gameID, 'ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType])->put();
            return "[Game ended]";
        }
        gdn::controller()->informMessage("No Avalon game found to end!");
        return false;
    }

    function setResultOfMission($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $gameID = isset($params['_default']) && is_numeric($params['_default']) ? intval($params['_default']) : $this->getCurrentGame();
        $allowed = ['W' => true, 'N' => true, 'L' => true];
        $win_status = strtoupper($content);
        if ($gameID && isset($allowed[$win_status])) {
            $game = gdn::sql()->select('Wins,Done')->from("AvGames")->where(["GameID" => $gameID, 'ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType])->get()->firstRow();
            if ($game && !$game->Done) {
                $statuses = explode(',', $game->Wins);
                $newwins = "";
                foreach ($statuses as $status) {
                    if ($win_status && $status === 'N') {
                        $newwins.=",$win_status";
                        $win_status = false;
                    } else {
                        $newwins.=",$status";
                    }
                }
                gdn::sql()->update("AvGames", ['Wins' => substr($newwins, 1)])->where(["GameID" => $gameID, 'ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType])->put();
            }
            return "[mission status logged]";
        }
        return false;
    }

    function detectGameTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $this->currentGameID = intval($content);
        }
        return false;
    }

    public function startAvalonGame($users) {
        $numplayers = count($users);
        if ($numplayers < 3 || $numplayers > 10) {
            return false;
        }
        //    gdn::sql()->insert("");
        $roles = [];
        $roles[] = self::Merlijn;
        $roles[] = self::Mordred;
        $roles[] = self::Morgana;
        $roles[] = self::Percival;
        $civ_roles = $numplayers - 4;
        shuffle($users);
        $badguys = [];
        $badguys[] = ['Name' => $users[1], 'Role' => self::Mordred];
        $badguys[] = ['Name' => $users[2], 'Role' => self::Badguy];
        if ($numplayers > 6) {
            $roles[] = self::Oberon;
            $badguys[] = ['Name' => $users[4], 'Role' => self::Oberon];
            $civ_roles--;
        }
        if ($numplayers > 9) {
            $roles[] = self::Badguy;
            $badguys[] = ['Name' => $users[5], 'Role' => self::Badguy];
            $civ_roles--;
        }
        for ($i = 0; $i < $civ_roles; $i++) {
            $roles[] = self::Goodguy;
        }
        $i = 0;
        foreach ($users as $username) {

            $user = is_numeric($username) ? gdn::userModel()->getID($username) : gdn::userModel()->getByUsername($username);
            if ($user === false) {
                break;
            }
            $validated_users[$username] = ['UserID' => $user->UserID, 'Name' => $user->Name, 'Role' => $roles[$i++]];
        }
        if (count($validated_users) < $numplayers) {
            return false;
        }
        usort($badguys, [$this, 'sortBadguys']);
        $merlin_vision_text = "";
        $badguys_text = "";
        foreach ($badguys as $badguy) {
            $name = $badguy['Name'];
            $role = $badguy['Role'];
            if ($role !== self::Mordred) {
                $merlin_vision_text.=", " . $validated_users[$name]['Name'];
            }
            if ($role !== self::Oberon) {
                $badguys_text.=", " . $validated_users[$name]['Name'];
            }
        }
        $perc_vision_text = rand(0, 1) ? $validated_users[$users[0]]['Name'] . t(" and ") . $validated_users[$users[2]]['Name'] : $validated_users[$users[2]]['Name'] . t(" and ") . $validated_users[$users[0]]['Name'];
        $game_id = gdn::sql()->insert("AvGames", ['ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'NumPlayers' => $numplayers, 'MerlinVision' => substr($merlin_vision_text, 2) . t((count($badguys) > 2 ? " are traitors" : " is a traitor") . " to the Round Table."), 'PercivalVision' => $perc_vision_text . t(" are schooled in the art of Witchcraft."), 'BadGuys' => substr($badguys_text, 2) . t(" are traitors to the round table.")]);
        foreach ($validated_users as $name => $user) {
            gdn::sql()->insert("AvPlayers", ['GameID' => $game_id, 'UserID' => $user['UserID'], 'Role' => $user['Role']]);
        }
        $html = "[avgame]$game_id" . "[/avgame]";
        return $html;
    }

    function sortBadguys($a, $b) {
        return strnatcmp($a['Name'], $b['Name']);
    }

    public function getRole($roleID) {
        if (empty($this->roles)) {
            $this->roles = [];
            $this->roles[self::Merlijn] = "<span class='AvRole AvGoodRole'>Merlin</span>";
            $this->roles[self::Percival] = "<span class='AvRole AvGoodRole'>Percival</span>";
            $this->roles[self::Oberon] = "<span class='AvRole AvEvilRole'>Oberon</span>";
            $this->roles[self::Mordred] = "<span class='AvRole AvEvilRole'>Mordred</span>";
            $this->roles[self::Morgana] = "<span class='AvRole AvEvilRole'>Morgana</span>";
        }
        if (isset($this->roles[$roleID])) {
            return $this->roles[$roleID];
        }
        if ($roleID < 0) {
            return "<span class='AvRole AvEvilRole'>Henchperson</span>";
        } else {
            return "<span class='AvRole AvGoodRole'>Knight of the Round Table</span>";
        }
    }

    public function lookupMissionReq($numPlayers) {
        $req = [5 => ['2', '3', '2', '3', '3'],
            6 => ['2', '3', '4', '3', '4'],
            7 => ['2', '3', '3', '4*', '3'],
            8 => ['3', '4', '4', '5*', '5'],
            9 => ['3', '4', '4', '5*', '5'],
            10 => ['3', '4', '4', '5*', '5']];
        if (isset($req[$numPlayers])) {
            return $req[$numPlayers];
        }
        return ['1', '2', '3', '4', '5'];
    }

    public function getGameHTML($id) {
        if (isset($this->gameHTML[$id])) {
            return $this->gameHTML[$id];
        }

        $game_row = gdn::sql()->select('MerlinVision,PercivalVision,BadGuys,Done,NumPlayers,Wins')->from("AvGames")->where('GameID', $id)->get()->firstRow();
        if (!$game_row) {
            return false;
        }
        $html = "";
        $wins = explode(",", $game_row->Wins);
        $req = $this->lookupMissionReq($game_row->NumPlayers);
        $i = 0;
        foreach ($wins as $win) {
            if (!empty($win)) {
                $html.="<span class='AvalonMission$win'>" . $req[$i++] . "</span>";
            }
        }
        $html.="<br/>";
        if (!$game_row->Done) {
            $role_row = gdn::sql()->select('Role')->from("AvPlayers")->where(["UserID" => gdn::session()->UserID, 'GameID' => $id])->get()->firstRow();
            if ($role_row) {
                $html .= "<p>" . t("You are") . $this->getRole($role_row->Role) . ". ";
                if ($role_row->Role === self::Merlijn) {
                    $html.=t("You have received a vision that ") . $game_row->MerlinVision;
                } else if ($role_row->Role === self::Percival) {
                    $html.=t("You have received a vision that ") . $game_row->PercivalVision;
                } else if ($role_row->Role === self::Oberon) {
                    $html.=t("Down with Avalon!");
                } else if ($role_row->Role < 0) {
                    if ($role_row->Role === self::Mordred) {
                        $html.=$game_row->BadGuys;
                    } else if ($role_row->Role === self::Morgana) {
                        $html.=$game_row->BadGuys;
                    } else {
                        $html.=$game_row->BadGuys;
                    }
                } else {
                    $html.="To arms!";
                }
                $html.="</p>";
            }
            return ($this->gameHTML[$id] = "<p class='AvTitle'>Current game in progress...</p>$html");
        } else {
            $role_rows = gdn::sql()->select('p.Role,u.Name')->from("AvPlayers p")->join("User u", "u.UserID=p.UserID")->where('p.GameID', $id)->orderBy("u.Name")->get();
            $html = substr($html, 0, strlen($html) - 5);
            while ($row = $role_rows->nextRow()) {
                $html.="<br/>" . $row->Name . " was: " . $this->getRole($row->Role);
            }
            return ($this->gameHTML[$id] = "<p class='AvTitle'>Game finished!</p>$html");
        }
        return "failure";
    }

    public function renderGame($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            return "<div class='AvGame'>" . $this->getGameHTML(intval($content)) . "</div>";
        }
        return false;
    }

    public function Setup() {

        //This function is called when the plugin is enabled and creates a Table in the database
        Gdn::structure()->table("AvGames")
                ->primaryKey('GameID')
                ->column('ParentID', 'int')
                ->column('ParentType', 'int')
                ->column('MerlinVision', 'Text', FALSE)
                ->column('PercivalVision', 'Text', false)
                ->column('BadGuys', 'Text', false)
                ->column('Done', 'int(1)', 0)
                ->column('NumPlayers', 'int')
                ->column('Wins', 'varchar(10)', 'N,N,N,N,N')
                ->set();
        Gdn::structure()->table("AvPlayers")
                ->primaryKey('PlayerID')
                ->column('GameID', 'int', false)
                ->column('UserID', 'int', false)
                ->column('Role', 'int', false)
                ->set();
    }

    //This is called to add rules to the NBBC bbcode parser before displaying the comments to the client
    //We add our own rules here.
    public function PluginCommandParserPlugin_BeforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $Sender->addCSS($this->getCSSToAdd());
        //Between [cng] and [/cng] is the gameID
        $BBCode->addRule('avgame', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'renderGame'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
    }

    public function getCSSToAdd() {
        return ".AvEvilRole
            {
                color:red;
                font-weight: bold;
            }
            .AvGoodRole
            {
                color:blue;
                font-weight: bold;
            }
            .AvGame
            {
                border: solid black 2px;
            }
            .AvGame p
            {
                margin-left: 10px;
            }
            .AvTitle
            {
                font-weight: bold;
                font-size: large;
            }
            .AvalonMissionN,.AvalonMissionW,.AvalonMissionL
            {
                text-align: center;
                width:20px;
                height:20px;
                display: inline-block;
                margin-left:15px;
                margin-right:-10px;
            }
            .AvalonMissionN
            {
                background-color: beige;
            }
            .AvalonMissionW
            {
                background-color: blue;
                color:white;
            }
            .AvalonMissionL
            {
                background-color: red;
                color:white;
            }
            .VA_avalon.VA_stemronde, .VA_avalon.VA_voting.VA_round
            {
                color:green;
            }";
    }

}
