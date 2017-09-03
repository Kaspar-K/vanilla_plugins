<?php

if (!defined('APPLICATION')) {
    exit();
}

$PluginInfo['VoteAggregator'] = array(
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'Description' => 'Lets you vote on stuff. Get instructions on proper usage by posting a post containing this command: [pluginexplanation]VoteAggregator[/pluginexplanation]',
    'HasLocale' => true,
    'MobileFriendly' => TRUE,
    'Name' => 'VoteAggregator',
    'RegisterPermissions' => ['Plugins.VoteAggregator.CreatePoll' => 'Garden.Moderation.Manage', 'Plugins.VoteAggregator.ClosePoll' => 'Garden.Moderation.Manage', 'Plugins.VoteAggregator.Vote' => 1],
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('PluginCommandParser' => '1.0'),
    'Version' => '1.0'
);

class VoteAggregatorPlugin extends Gdn_Plugin {

    private $currentDiscussionID;
    private $currentPoll;

    function __construct() {
        
    }

    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex = $Sender->EventArguments['CommandIndex'];
        $commands = [
            "[vastartpoll]X[/vastartpoll]" => t("Start a basic poll with title X. For example: Lynch voting round/Mayor voting round."),
            "[vastartpoll=hidden]X[/vastartpoll]" => t("Start a poll. Never show what people have voted what."),
            "[vastartpoll=reveal]X[/vastartpoll]" => t("Start a poll. Show after poll has closed what people voted."),
            "[vavote]Option[/vavote]" => t("Vote for option X. Overwrites earlier votes."),
            "[vaclosepoll]" => t("Close active poll/quoted poll."),
            "[vacloseall]" => t("Close all active polls in this discussion.")];
        $commandIndex->addCommands($commands, $this);
    }

    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        $this->currentPoll = false;
        $ownsPost = $this->currentPost->currentUserIsOwner();
        if ($ownsPost || checkPermission("Plugins.VoteAggregator.Vote")) {
            $BBCode->AddRule("vavote", [

                'method' => Array($this, 'voteOnPoll'),
            ]);
        }
        if ($ownsPost || checkPermission("Plugins.VoteAggregator.CreatePoll")) {
            $BBCode->AddRule("vastartpoll", [

                'method' => Array($this, 'startPoll'),
            ]);
        }
        if ($ownsPost || checkPermission("Plugins.VoteAggregator.ClosePoll")) {
            $BBCode->AddRule("vaclosepoll", [

                'method' => Array($this, 'closePoll'),
                'end_tag' => BBCODE_PROHIBIT]);
            $BBCode->AddRule("vacloseall", [

                'method' => Array($this, 'closeAllPolls'),
                'end_tag' => BBCODE_PROHIBIT]);
        }
        $BBCode->addRule('vashowpoll', Array(
            'method' => Array($this, 'setCurrentPollID'),
        ));
    }

    function setCurrentPollID($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $content = intval($content);
            if (($currentPoll = gdn::sql()->select("PollSubject")->from("VAPolls")->where(['ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'PollID' => $content])->get()->firstRow())) {
                $this->currentPoll = ['ID' => $content, 'Subject' => $currentPoll->PollSubject];
            }
        }
        return false;
    }

    function closePoll($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $pollID = isset($params['_default']) && is_numeric($params['_default']) ? intval($params['_default']) : $this->getCurrentPoll($this->currentDiscussionID)['ID'];
        if ($pollID) {
            gdn::sql()->update("VAPolls")->set("Open", 0)->where(["PollID" => $pollID, 'ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType])->put();
        }
    }

    function closeAllPolls($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        gdn::sql()->update("VAPolls")->set("Open", 0)->where(['ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType])->put();
        return "";
    }

    function startPoll($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $hiddenCmd = strtolower($params['_default']);
        $hidden = 0;
        if ($hiddenCmd === 'hidden') {
            $hidden = 2;
        } else if ($hiddenCmd === 'reveal') {
            $hidden = 1;
        }
        $html = $this->startNewPoll($content, gdn::session()->UserID, $hidden);
        return $html;
    }

    function voteOnPoll($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $html = $this->vote(gdn::session()->UserID, $content);
        return $html;
    }

    public function renderPoll($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            if (!isset($this->pollsHTML[$content])) {
                $this->pollsHTML[$content] = $this->getPollHTML($content);
            }
            return $this->pollsHTML[$content];
        }
        return false;
    }

    public function setup() {
        gdn::structure()
                ->table("VAPolls")
                ->primaryKey("PollID")
                ->column("ParentID", 'int')
                ->column("ParentType", "int(3)", 2)
                ->column("UserID", 'int')
                ->column('PollSubject', 'varchar(30)')
                ->column('TimePosted', 'datetime')
                ->column('Open', 'int(1)')
                ->column('Hidden', 'int(2)', 0)
                ->set();
        gdn::structure()
                ->table("VAVotes")
                ->primaryKey("VoteID")
                ->column("PollID", 'int')
                ->column("UserID", 'int')
                ->column("Vote", 'varchar(30)')
                ->column('TimePosted', 'datetime')
                ->set();
    }

    public function getCurrentPoll() {
        if ($this->currentPoll) {
            return $this->currentPoll;
        }
        $activePoll = Gdn::sql()->select("PollID,PollSubject,Hidden")->from("VAPolls")->where(['ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'Open' => 1])->orderBy("PollID", "desc")->limit(1)->get();
        if (($poll = $activePoll->firstRow())) {
            return $this->currentPoll = ['ID' => $poll->PollID, 'Subject' => $poll->PollSubject, 'Hidden' => $poll->Hidden];
        }
        return false;
    }

    public function vote($userID, $vote) {
        if (empty($vote) || strlen($vote) > 30) {
            return false;
        }
        if (($poll = $this->getCurrentPoll())) {
            $pollID = $poll['ID'];
            $subject = str_replace(" ", "_", $poll['Subject']);
            $Now = Gdn_Format::toDateTime();
            Gdn::sql()->replace("VAVotes", ['TimePosted' => $Now, 'PollID' => $pollID, 'UserID' => $userID, 'Vote' => $vote], ['PollID' => $pollID, 'UserID' => $userID], true);
            $html = "[vasavedvote=$subject]" . ($poll['Hidden'] === 0 ? $vote : 'Voted!') . "[/vasavedvote]";
            return $html;
        }
        return false;
    }

    public function startNewPoll($pollSubject, $userID, $hidden) {
        if (empty($pollSubject) || strlen($pollSubject) > 30) {
            return false;
        }
        $Now = Gdn_Format::toDateTime();
        $pollID = Gdn::sql()->insert("VAPolls", ['Open' => 1, 'TimePosted' => $Now, 'ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'UserID' => $userID, 'PollSubject' => $pollSubject, 'Hidden' => $hidden]);
        return "[vashowpoll]$pollID" . "[/vashowpoll]";
    }

    public function getPollHTML($pollID) {
        $pollResult = Gdn::sql()->select("PollSubject,Open,Hidden")->from("VAPolls")->where("PollID", $pollID)->get();
        if (($poll = $pollResult->firstRow())) {
            $pollSubject = ($poll->Open ? "" : "<span class='VAClosed'>[CLOSED]</span>") . htmlspecialchars($poll->PollSubject, ENT_QUOTES);
            if ($poll->Hidden === 0 || !$poll->Open) {
                $votes = Gdn::sql()->select("v.Vote,u.Name")->from("VAVotes v")->join("User u", "v.UserID=u.UserID")->where("v.PollID", $pollID)->get();
                $voteArray = [];
                while (($row = $votes->nextRow())) {
                    $votekey = strtolower(htmlspecialchars($row->Vote, ENT_COMPAT | ENT_HTML401 | ENT_QUOTES));
                    $voteArray[$votekey]['Vote'] = htmlspecialchars($row->Vote, ENT_COMPAT | ENT_HTML401 | ENT_QUOTES);
                    $voteArray[$votekey]['Users'][] = htmlspecialchars($row->Name, ENT_COMPAT | ENT_HTML401 | ENT_QUOTES);
                }
                usort($voteArray, [$this, 'sortVotes']);
                $html = "";
                foreach ($voteArray as $voteOption) {
                    $html.="<br/><b>" . $voteOption['Vote'] . "</b>: " . count($voteOption['Users']);
                    foreach ($voteOption['Users'] as $userName) {
                        if ($poll->Hidden === 0 || ($poll->Hidden === 1 && !$poll->Open)) {
                            $html.=", $userName";
                        }
                    }
                }
            }
            $class = 'VATitle';
            $subject = explode(" ", strtolower(htmlentities($poll->PollSubject, ENT_QUOTES)));
            foreach ($subject as $part) {
                $class.=" VA_$part";
            }
            if ($poll->Hidden === 1) {
                $class.=" VAReveal";
            }
            if ($poll->Hidden === 2) {
                $class.=" VAHidden";
            }
            return "<div class='VAPoll'><p class='$class'>$pollSubject</p><div class='VAVoteList'>" . substr($html, 5) . "</div></div>";
        }
        return false;
    }

    function sortVotes($a, $b) {
        return count($b['Users']) - count($a['Users']);
    }

    //This is called to add rules to the NBBC bbcode parser before displaying the comments to the client
    //We add our own rules here.
    public function PluginCommandParserPlugin_BeforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        //Between [cng] and [/cng] is the gameID
        $BBCode->addRule('vashowpoll', Array(
            'method' => Array($this, 'renderPoll'),
        ));
        $BBCode->addRule('vasavedvote', [
            'method' => Array($this, 'renderVote'),
        ]);
    }

    public function renderVote($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return isset($params['_default']);
        }
        $subject = explode("_", strtolower(htmlentities($params['_default'], ENT_QUOTES)));
        $class = "";
        foreach ($subject as $part) {
            $class.=" VA_$part";
        }
        $vote = $content;
        return '<div class="VAVote' . $class . '">' . $vote . '</div>';
    }

    public function getCSSToAdd() {
        return "
            .VAPoll
            {

            }
            .VATitle
            {
                text-transform: capitalize;
                font-size:large;
            }
            .VAVoteList
            {

            }
            .VAVote
            {
                font-size:large
            }
            .VA_burgermeester.VA_stemronde,.VA_mayor.VA_voting.VA_round
            {
                color:blue;
            }
            .VA_lynch.VA_stemronde,.VA_lynch.VA_voting.VA_round
            {
                color:red;
            }
            .VAHidden
            {
                background-color: activeborder;
                font-style:italic;
            }
            .VAReveal
            {
                font-style:italic;   
            }";
    }

}
