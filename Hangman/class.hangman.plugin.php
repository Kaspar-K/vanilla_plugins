<?php

if (!defined('APPLICATION')) {
    exit();
}

$PluginInfo['Hangman'] = array(
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'Description' => 'Implements a hangman game.',
    'HasLocale' => true,
    'MobileFriendly' => TRUE,
    'Name' => 'Hangman',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('PluginCommandParser' => '1.0'),
    'Version' => '1.0'
);

class HangmanPlugin extends Gdn_Plugin {

    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex = $Sender->EventArguments['CommandIndex'];
        $commands = [
            "[hstart]X[/hstart]" => t("Start a new game with the word X."),
            "[hg]e[/hg]" => t("Guess the letter 'e'."),
            "[hrandom]" => t("Start a new game with a random word. Dictionary plugin has to be enabled!")];
        $commandIndex->addCommands($commands, $this);
    }

    private $currentWordID;
    private $currentPost;

    function __construct() {
        parent::__construct();
    }

    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        $this->currentWordID = false;
        $BBCode->AddRule("hstart", [
            'method' => Array($this, 'saveHangmanWord'),]);
        $BBCode->AddRule("hg", [
            'method' => Array($this, 'saveHangmanGuess'),]);
        $BBCode->AddRule("hmword", [
            'method' => Array($this, 'detectWord')]);
        $BBCode->AddRule("hrandom", [
            'method' => Array($this, 'saveRandomWord'),
            'end_tag' => BBCODE_PROHIBIT,]);
    }

    function saveRandomWord($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (Gdn::pluginManager()->isEnabled("Dictionary")) {
            $randomWord = (new DictionaryPlugin())->getRandomWord();
            return $this->saveHangmanWord($bbcode, $action, $name, $default, $params, $randomWord);
        }
        return "<em>For this function the Dictionary plugin has to be enabled.</em>";
    }

    public function getCSSToAdd() {
        return "
            .try
            {
                position:absolute;
                right:20px;
                top:30%;
            }
            .HMLetter
            {
                font-weight: bold;
                padding-left: 3px;
                padding-right: 3px;
                border:1px solid black;
            }
            .HangmanWord
            {
                border:1px solid black;
                display:inline-block;
                padding-left: 3px;
                padding-right: 3px;
            }
            .HangmanWord.HMStage2,.HangmanWord.HMStage3
            {
                background-color: lightgray;
            }
            .HangmanWord.HMStage4,.HangmanWord.HMStage5
            {
                background-color: gray;
            }
            .HangmanWord.HMStage6,.HangmanWord.HMStage7
            {
                background-color: darkslategrey;
                color:white;
            }
            .HangmanWord.HMStage8,.HangmanWord.HMStage9
            {
                background-color: black;
                color:white;
            }";
    }

    function detectWord($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $this->currentWordID = intval($content);
        }
        return false;
    }

    function renderHangmanWord($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            if (isset($this->wordsRendered[$content])) {
                return $this->wordsRendered[$content];
            }
            $word = gdn::sql()->select("RevealedWord,GuessesWrong,Won")->from("HMWords")->where("WordID", $content)->get()->firstRow();
            if ($word) {
                $stage = $word->Won > 0 ? -1 : $word->GuessesWrong;
                $html = "<div><p class='HangmanWord HMStage$stage'>$word->RevealedWord</p><br><img src='" . $this->GetResource("images/hangman$stage.png", FALSE, FALSE) . "'></div>";
                $allGuesses = gdn::sql()->select("Letter")->from("HMGuesses")->orderBy("Letter")->where("WordID", $content)->get();
                $guessString = "";
                while ($row = $allGuesses->nextRow()) {
                    $guessString.=',' . $row->Letter;
                }
                $html.="<p class='HMGuesses'>Letters guessed: " . substr($guessString, 1) . ".</p>";
                if ($word->Won < 0) {
                    $html.="<p>The executioner won :(</p>";
                }
                if ($word->Won > 0) {
                    $html.="<p>The convict lives to see another day :D</p>";
                }
                $this->wordsRendered[$content] = $html;
                return $html;
            }
        }
        return false;
    }

    function renderHangmanGuess($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            if (isset($this->guessRendered[$content])) {
                return $this->guessRendered[$content];
            }
            $guess = gdn::sql()->select("Letter,UserID,LettersFound")->from("HMGuesses")->where("GuessID", $content)->get()->firstRow();
            if ($guess) {
                $name = gdn::session()->UserID == $guess->UserID ? "You" : htmlspecialchars((new UserModel())->getID($guess->UserID)->Name);
                $html = "<p class='HMLetterGuess'>$name guessed: <span class='HMLetter'>$guess->Letter</span>, the word contained this letter <strong><em>" . $guess->LettersFound . "</em></strong> time(s).</p>";
                $this->guessRendered[$content] = $html;
                return $html;
            }
        }
        return false;
    }

    function saveHangmanWord($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (ctype_alpha($content)) {
            $word = strtolower($content);
            $length = strlen($word);
            if ($length > 0 && $length < 20) {
                $firstGuess = "";
                for ($i = 0; $i < $length; $i++) {
                    $firstGuess.='.';
                }
                $currentUser = Gdn::session()->UserID;
                $wordID = Gdn::sql()->insert("HMWords", ['ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'Word' => $word, 'RevealedWord' => $firstGuess, 'UserID' => $currentUser]);
                return "[hmword]$wordID" . "[/hmword]";
            }
        }
        return false;
    }

    public function getCurrentWordID() {
        if ($this->currentWordID > 0) {
            return $this->currentWordID;
        }
        $this->currentWordID = false;
        $activeWord = gdn::sql()->select("WordID")->from("HMWords")->where(['ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'Won' => 0])->orderBy("WordID", "desc")->limit(1)->get()->firstRow();
        if ($activeWord) {
            $this->currentWordID = $activeWord->WordID;
        }
        return $this->currentWordID;
    }

    function saveHangmanGuess($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return $this->getCurrentWordID() > 0;
        }
        if (strlen($content) == 1 && ctype_alpha($letter = strtolower($content[0]))) {
            $currentWordID = $this->getCurrentWordID();
            $result = gdn::sql()->select("Word,RevealedWord,GuessesWrong,Won")->from("HMWords")->where("WordID", $currentWordID)->get()->firstRow();
            if ($result && $result->Won == 0) {
                $hangmanWord = $result->Word;
                $length = strlen($hangmanWord);
                $revealedWord = $result->RevealedWord;
                $newGuess = "";
                $lettersFound = 0;
                $won = 1;
                for ($i = 0; $i < $length; $i++) {
                    if ($revealedWord[$i] != '.') {
                        $newGuess.=$revealedWord[$i];
                    } else if ($hangmanWord[$i] == $letter) {
                        $newGuess.=$letter;
                        $lettersFound++;
                    } else {
                        $newGuess.='.';
                        $won = 0;
                    }
                }
                $currentUser = Gdn::session()->UserID;
                $guessesWrong = $result->GuessesWrong + ($lettersFound > 0 ? 0 : 1);
                if ($guessesWrong > 8) {
                    $won = -1;
                }
                Gdn::sql()->update("HMWords")->set(['GuessesWrong' => $guessesWrong, 'RevealedWord' => $newGuess, 'Won' => $won])->where("WordID", $this->currentWordID)->put();
                $guessID = Gdn::sql()->insert("HMGuesses", ['UserID' => $currentUser, 'WordID' => $this->currentWordID, 'Letter' => $letter, 'LettersFound' => $lettersFound]);
                return "[hmguess]$guessID" . "[/hmguess]";
            }
        }
        return false;
    }

    public function setup() {
        parent::setup();
        gdn::structure()->table("HMWords")
                ->primaryKey("WordID")
                ->column("UserID", 'int')
                ->column("PostID", 'int', 0)
                ->column("ParentID", 'int', 0)
                ->column("ParentType", 'int(3)', 2)
                ->column("Word", 'varchar(20)')
                ->column("RevealedWord", 'varchar(20)')
                ->column("GuessesWrong", 'int')
                ->column("Won", 'int(1)', 0)
                ->set();
        gdn::structure()->table("HMGuesses")
                ->primaryKey("GuessID")
                ->column("WordID", 'int')
                ->column("UserID", 'int')
                ->column("Letter", 'varchar(1)')
                ->column("LettersFound", 'int')
                ->set();
    }

    //This is called to add rules to the NBBC bbcode parser before displaying the comments to the client
    //We add our own rules here.
    public function PluginCommandParserPlugin_BeforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $Sender->addCSS($this->getCSSToAdd());
        //Between [cng] and [/cng] is the gameID
        $BBCode->addRule('hmword', Array(
            'method' => Array($this, 'renderHangmanWord'),
        ));
        $BBCode->addRule('hmguess', Array(
            'method' => Array($this, 'renderHangmanGuess'),
        ));
    }

}

?>