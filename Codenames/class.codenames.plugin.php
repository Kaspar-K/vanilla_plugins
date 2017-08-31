<?php

if (!defined('APPLICATION')) {
    exit();
}

$PluginInfo['Codenames'] = array(
    'Name' => 'Codenames',
    'Description' => 'Implements the Codenames game for a forum.',
    'Version' => '1.0',
    'Author' => "Tom Sassen",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'MobileFriendly' => TRUE,
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('PluginCommandParser' => '1.0', 'Dictionary' => '1.0')
);

class CodenamesPlugin extends Gdn_Plugin {
    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex=$Sender->EventArguments['CommandIndex'];
        $commands=[
            "[newcngame]X[/newcngame]"=>
            [0=>"Start a game with a grid of X by X cards.",
                'nl'=>"Start een spel met X bij X kaarten."
                ],
            "[cnhint]X,Y[/cnhint]"=>
            [0=>"Give X as hint as spymaster, and let your teammates guess Y+1 times. If Y = 0 or Y > 100, Y = 100.",
                'nl'=>"Geef X als hint as meesterspion, en laat je team Y+1 keer gokken. Als Y = 0 of Y > 100, Y = 100."]
            ];
            $commandIndex->addCommands($commands,$this);
    }

    public function getExplanation() {
        return "A plugin to play Codenames.";
    }
    public function getExplanation_nl() {
        return "Een plugin om codenames mee te spelen.";
    }
    public function __construct() {
        parent::__construct();
    }

    public function controller_index($Sender) {
        parent::controller_index($Sender);
    }

    public function addFiles($Sender) {
        $Sender->addJsFile('codenames.js', 'plugins/Codenames');
        $Sender->AddCssFile('codenames.css', 'plugins/Codenames');
    }

    public function DiscussionController_Render_Before($Sender) {
        $this->addFiles($Sender);
    }

    public function MessagesController_Render_Before($Sender) {
        $this->addFiles($Sender);
    }

    private $currentGameID;

    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $parser = $Sender->EventArguments['Parser'];
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        $parser->addRule('newcngame', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'createNewGame'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
        $parser->addRule('cnhint', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'giveHint'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
        $parser->addRule('cng', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'detectGame'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
    }

    //This is called to add rules to the NBBC bbcode parser before displaying the comments to the client
    //We add our own rules here.
    public function PluginCommandParserPlugin_BeforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        //Between [cng] and [/cng] is the gameID
        $BBCode->addRule('cng', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'RenderCodeNameGame'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
        $BBCode->addRule('cnh', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'renderCodeNameHint'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
    }

    public function renderCodeNameHint($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $hint = gdn::sql()->select("Hint,NumWords,TeamID")->from("CNHints")->where("HintID", intval($content))->get()->firstRow();
            if ($hint) {
                return "<p class='CNHint Team$hint->TeamID'>$hint->Hint: $hint->NumWords</p>";
            }
        }
        return false;
    }

    public function RenderCodeNameGame($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $content = intval($content);
            $id = sha1(uniqid() . microtime());
            if (isset($this->cngames[$content])) {
                return "<div id='$id'>" . $this->cngames[$content] . "<script>jQuery(document).ready(function($) {initializeCNGames('$id');});</script>";
            }
            $currentUser = Gdn::session()->UserID;
            $game = Gdn::sql()->select("Grid,WonBy,Turn,WordsLeft")->from("CNGames")->where("GameID", $content)->get()->firstRow();
            if ($game) {
                $hints = gdn::sql()->select("TeamID,Hint,NumWords,AfterGuessID")->from("CNHints")->where("GameID", $content)->get();
                $currentHint = $hints->nextRow();

                $guesses = Gdn::sql()->select("XCoo,YCoo,TeamID,GuessID")->from("CNGuesses")->where("GameID", $content)->orderBy("GuessID")->get();
                $userRole = Gdn::sql()->select("UserRole")->from("CNPlayers")->where(["GameID" => $content, "UserID" => $currentUser])->get()->firstRow();
                $userRole = $userRole ? $userRole->UserRole : 0;
                $class = "CNGame" . ($game->WonBy != 0 ? " Team$game->WonBy" : " Turn$game->Turn");
                $lastGuess = false;
                $html = "";
                $coordinates = [];
                while (($guess = $guesses->nextRow())) {
                    $lastGuess = $guess;
                    while ($currentHint && $currentHint->AfterGuessID < $guess->GuessID) {
                        $html.="<p class='CNHint Team$currentHint->TeamID'>$currentHint->Hint: $currentHint->NumWords</p>";
                        $currentHint = $hints->nextRow();
                    }
                    $coordinates[$guess->XCoo][$guess->YCoo] = true;
                    $html.="<div class='CNGuess Team$guess->TeamID'>" . $this->getBoardHTML($game->Grid, false, $coordinates) . "</div>";
                }
                while ($currentHint) {
                    $html.="<p class='CNHint Team$currentHint->TeamID'>$currentHint->Hint: $currentHint->NumWords</p>";
                    $currentHint = $hints->nextRow();
                }
                $html.="</div>";
                if ($game->WonBy != 0) {
                    $titleText = "Game over! " . ($game->WonBy == 1 ? "Red" : "Blue") . " team won!";
                } else if ($game->WordsLeft) {
                    if ($userRole == $game->Turn) {
                        $titleText = "Your team has $game->WordsLeft word(s) left.";
                    } else if ($userRole - 2 == $game->Turn) {
                        $titleText = "The spies of your team have $game->WordsLeft chance(s) left.";
                    } else {
                        $titleText = "Team " . ($game->Turn == 1 ? "Red" : "Blue") . " has $game->WordsLeft guess(es) left.";
                    }
                } else {
                    $titleText = ($game->Turn == $userRole - 2 ? "You" : (($game->Turn == 1 ? "Red" : "Blue") . " Spymaster")) . " should give a hint with [cnhint]{word},{amount}[/cnhint]";
                }
                $html = "<p>$titleText</p><div class='$class'>" . $this->getBoardHTML($game->Grid, $game->WonBy != 0 || $userRole > 2, $coordinates) . "<div class='Spoiler'>$html";
                if ($game->WonBy == 0 && ($userRole == 0 || ($userRole == $game->Turn && $game->WordsLeft > 0))) {
                    $skipRequests = gdn::sql()->select("UserID")->from("CNSkipRequests")->where("GameID", $content)->get();
                    $numRequests = 0;
                    $increment = 1;
                    while ($skip_requester = $skipRequests->nextRow()) {
                        $numRequests+=$increment;
                        if ($currentUser == $skip_requester->UserID) {
                            $numRequests*=-1;
                            $increment = -1;
                        }
                    }
                    $html.="<input name='CNSkipRequests' type='hidden' value='$numRequests'/>";
                    if($currentUser>0){
                    $html.="<input name='CNUserRole' type='hidden' value='$userRole'/>";}
                    $html.="<input name='CNGameID' type='hidden' value='$content'/>";
                    $html.="<input name='CNTurn' type='hidden' value='$game->Turn'/>";
                }
                $html.="</div></div>";
                $this->cngames[$content] = $html;
                return "<div id='$id'>" . $html . "<script>jQuery(document).ready(function($) {initializeCNGames('$id');});</script>";
            }
        }
        return false;
    }

    public function joinGame($gameID, $roleID) {
        if ($roleID < 1 || $roleID > 4) {
            return;
        }
        $currentUser = Gdn::session()->UserID;
        gdn::sql()->insert("CNPlayers", ['UserID' => $currentUser, 'GameID' => $gameID, 'UserRole' => $roleID]);
    }

    public function saveGuess($gameID, $word) {
        $currentUser = Gdn::session()->UserID;
        $role = gdn::sql()->select("UserRole")->from("CNPlayers")->where(["UserID" => $currentUser, "GameID" => $gameID])->get()->firstRow();
        $game = gdn::sql()->select("Grid,Turn,LastGuessID,WonBy,WordsLeft")->from("CNGames")->where("GameID", $gameID)->get()->firstRow();
        if (!($role && $game && $game->WordsLeft > 0 && $game->WonBy == 0 && $role->UserRole == $game->Turn)) {
            return;
        }
        $wordsLeft = $game->WordsLeft - 1;
        $guesses = gdn::sql()->select("XCoo,YCoo,TeamID")->from("CNGuesses")->where("GameID", $gameID)->get();
        $revealed = [];
        while (($row = $guesses->nextRow())) {
            $revealed[$row->XCoo][$row->YCoo] = true;
        }
        $rows = explode("|", $game->Grid);
        $unrevealedCards = [];
        $xCoo = -1;
        $yCoo = -1;
        foreach ($rows as $keyX => $row) {
            $columns = explode(" ", $row);
            foreach ($columns as $keyY => $column) {
                $isWordPicked = false;
                $data = explode(",", $column);
                $isAlreadyRevealed = (isset($revealed[$keyX]) && isset($revealed[$keyX][$keyY]));
                if ($data[1] == $word) {
                    if ($isAlreadyRevealed) {
                        return;
                    }
                    $isWordPicked = true;
                    $xCoo = $keyX;
                    $yCoo = $keyY;
                    if ($data[0] == -1) {
                        $wonBy = $role == 1 ? 2 : 1;
                    }
                    if ($data[0] != $role->UserRole) {
                        $wordsLeft = 0;
                    }
                } else if (!$isAlreadyRevealed) {
                    $unrevealedCards[$data[0]] = isset($unrevealedCards[$data[0]]) ? $unrevealedCards[$data[0]] + 1 : 1;
                }
            }
        }
        if ($xCoo < 0) {
            return;
        }
        $wonBy = empty($wonBy) ? (empty($unrevealedCards[1]) ? 1 : (empty($unrevealedCards[2]) ? 2 : 0)) : $wonBy;
        if ($wonBy) {
            gdn::sql()->update("CNGames")->set("WonBy", $wonBy)->where("GameID", $gameID)->put();
        }
        $lastGuessID = gdn::sql()->insert("CNGuesses", ['TeamID' => $role->UserRole, 'GameID' => $gameID, 'XCoo' => $xCoo, 'YCoo' => $yCoo, 'UserID' => $currentUser]);
        $newTurn = $wordsLeft > 0 ? $role->UserRole : ($role->UserRole == 1 ? 2 : 1);
        gdn::sql()->update("CNGames")->set(["Turn" => $newTurn, 'LastGuessID' => $lastGuessID, "WordsLeft" => $wordsLeft])->where("GameID", $gameID)->put();
        gdn::sql()->delete("CNSkipRequests", ['GameID' => $gameID]);
    }

    public function voteSkip($gameid) {
        $currentUser = Gdn::session()->UserID;
        $requests = gdn::sql()->select("UserID")->from("CNSkipRequests")->where("GameID", $gameid)->get();
        $game = gdn::sql()->select("Turn,WordsLeft")->from("CNGames")->where("GameID", $gameid)->get()->firstRow();
        $userRole = gdn::sql()->select("UserRole")->from("CNPlayers")->where(["UserID" => $currentUser, "GameID" => $gameid])->get()->firstRow();
        if (!($game && $userRole && $game->WordsLeft > 0 && $game->Turn == $userRole->UserRole)) {
            return -1;
        }
        $requestsNeeded = floor(gdn::sql()->getCount("CNPlayers", ['GameID' => $gameid, 'UserRole' => $game->Turn]) / 2) + 1;
        if ($requestsNeeded > 3) {
            $requestsNeeded = 3;
        }
        $numRequests = 1;
        $isPresent = false;
        while (($user = $requests->nextRow())) {
            $numRequests++;
            if ($user->UserID == $currentUser) {
                $numRequests--;
                $isPresent = true;
            }
        }
        if ($numRequests < $requestsNeeded) {
            if (!$isPresent) {
                gdn::sql()->insert("CNSkipRequests", ['UserID' => Gdn::session()->UserID, 'GameID' => $gameid]);
            }
            return $numRequests;
        } else {
            gdn::sql()->update("CNGames")->set(['WordsLeft' => 0, 'Turn' => $game->Turn == 1 ? 2 : 1])->where("GameID", $gameid)->put();
            gdn::sql()->delete("CNSkipRequests", ['GameID' => $gameid]);
            return 0;
        }
    }

    public function PluginController_Codenames_Create($Sender, $Args) {
        if (gdn::session()->UserID>0&&isset($Args[0]) && isset($Args[1]) && is_numeric($Args[1])) {
            $gameid = intval($Args[1]);
            if ($Args[0] == 'enlist') {
                if (isset($Args[2])) {
                    $this->joinGame($gameid, $Args[2]);
                    echo '{"action":"enlisted"}';
                }
            }
            if ($Args[0] == 'save') {
                if (isset($Args[2])) {
                    $this->saveGuess($gameid, $Args[2]);
                    echo '{"votes":"enlisted"}';
                }
            }
            if ($Args[0] == 'skip') {
                echo '{"votes":"' . $this->voteSkip($gameid) . '"}';
            }
            return;
        }
        echo '{"error":"Something went wrong :("}';
    }

    public function getBoardHTML($grid, $revealed = false, $revealedGrid = false) {
        $html = "";
        $rows = explode("|", $grid);
        foreach ($rows as $keyX => $row) {
            $columns = explode(" ", $row);
            foreach ($columns as $keyY => $column) {
                $data = explode(",", $column);
                $isGuessed = (isset($revealedGrid[$keyX]) && isset($revealedGrid[$keyX][$keyY]));
                $mustReveal = $revealed || $isGuessed;
                $class = ($isGuessed ? "Guessed " : "") . "Card" . ($mustReveal ? " Team$data[0]" : "");
                $html.="<div class='$class'>$data[1]</div>";
            }
            $html.="<br>";
        }
        return $html;
    }

    public function createGrid($size) {
        $dictionary = new DictionaryPlugin();
        $size = $size > 10 ? 10 : ($size < 3 ? 3 : $size);
        $cube = $size * $size;
        $numspies = floor($cube / 3);
        $spies = [];
        for ($i = 0; $i < $numspies; $i++) {
            $spies[] = 1;
            $spies[] = 2;
        }
        $spies[] = 1;
        $spies[] = -1;
        for ($i = count($spies); $i < $cube; $i++) {
            $spies[] = 0;
        }
        for ($i = 0; $i < $cube; $i++) {
            $temp = $spies[$i];
            $index = $this->crypto_rand_secure($i, $cube - 1);
            $spies[$i] = $spies[$index];
            $spies[$index] = $temp;
        }
        $game = "";
        for ($i = 0; $i < $size; $i++) {
            $game.="|";
            for ($j = 0; $j < $size - 1; $j++) {
                $game.=$spies[$i * $size + $j] . ',' . $dictionary->getRandomWord() . " ";
            }
            $game.=$spies[$i * $size + $j] . ',' . $dictionary->getRandomWord();
        }
        return substr($game, 1);
    }

//Function to draw a pseudorandom number, more random than mt_rand
    function crypto_rand_secure($min, $max) {
        $range = $max - $min;
        if ($range == 0)
            return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes, $s)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
    }

    public function Setup() {
        Gdn::structure()->table("CNGames")
                ->primaryKey('GameID')
                ->column('Grid', 'text', FALSE)
                ->column('WonBy', 'int(1)', 0)
                ->column('Turn', 'int(2)', 1)
                ->column('LastGuessID', 'int', 0)
                ->column('WordsLeft', 'int', 0)
                ->column('ParentID', 'int')
                ->column('ParentType', 'int(3)')
                ->set();
        Gdn::structure()->table("CNGuesses")
                ->primaryKey("GuessID")
                ->column('XCoo', 'int', FALSE)
                ->column('YCoo', 'int', FALSE)
                ->column("TeamID", "int(2)", false)
                ->column("UserID", "int", false)
                ->column("GameID", "int", false)
                ->set();
        Gdn::structure()->table("CNPlayers")
                ->primaryKey('EntryID')
                ->column('GameID', 'int', FALSE)
                ->column('UserID', 'int', FALSE)
                ->column('UserRole', 'int(3)', FALSE)
                ->set();
        Gdn::structure()->table("CNHints")
                ->primaryKey("HintID")
                ->column("TeamID", 'int(2)')
                ->column("UserID", 'int')
                ->column("Hint", 'varchar(20)')
                ->column("NumWords", 'int')
                ->column("AfterGuessID", 'int', 0)
                ->column("GameID", 'int', false)
                ->set();
        Gdn::structure()->table("CNSkipRequests")
                ->primaryKey("RequestID")
                ->column("GameID", 'int')
                ->column("UserID", 'int')
                ->set();
    }

    public function onDisable() {
        Gdn::structure()->table("Words")->drop();
    }

    public function createNewGame($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $currentPost = $params['_currentPost'];
            $grid = $this->createGrid($content);
            $gameid = Gdn::sql()->insert("CNGames", ['Grid' => $grid, 'ParentID' => $currentPost->ParentID, 'ParentType' => $currentPost->ParentType]);
            $this->currentGameID = $gameid;
            return "[cng]$gameid" . "[/cng]";
        }
        return false;
    }

    public function detectGame($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $this->currentGameID = $content;
        }
        return false;
    }

    public function getCurrentGameID() {
        if ($this->currentGameID > 0) {
            return $this->currentGameID;
        }

        $this->currentGameID = false;
        $activeGame = Gdn::sql()->select("GameID")->from("CNGames")->where(["ParentID" => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'WonBy' => 0])->orderBy("GameID", "desc")->limit(1)->get()->firstRow();
        if ($activeGame) {
            $this->currentGameID = $activeGame->GameID;
        }
        return $activeGame->GameID;
    }

    public function giveHint($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return $this->getCurrentGameID() ? true : false;
        }
        $data = explode(",", $content);
        if (empty($data[1])) {
            $data[1] = 0;
        }
        if (is_numeric($data[1]) && strlen($data[0]) < 21) {
            $hints = intval($data[1]);
            $currentUser = Gdn::session()->UserID;
            $gameID = $this->getCurrentGameID();
            $game = gdn::sql()->select("Turn,WordsLeft,LastGuessID")->from("CNGames")->where("GameID", $gameID)->get()->firstRow();
            $role = gdn::sql()->select("UserRole")->from("CNPlayers")->where(["GameID" => $gameID, "UserID" => $currentUser])->get()->firstRow();
            if ($game && $role && $game->Turn == $role->UserRole - 2 && $game->WordsLeft < 1) {
                gdn::sql()->update("CNGames")->set("WordsLeft", $hints > 0 && $hints < 100 ? $hints + 1 : 99)->where("GameID", $gameID)->put();
                $hintid = gdn::sql()->insert("CNHints", ['AfterGuessID' => $game->LastGuessID, 'TeamID' => $game->Turn, 'UserID' => $currentUser, 'GameID' => $gameID, 'Hint' => strtolower($data[0]), 'NumWords' => $data[1]]);
                return "[cnh]$hintid" . "[/cnh]";
            }
        }
        return false;
    }

}