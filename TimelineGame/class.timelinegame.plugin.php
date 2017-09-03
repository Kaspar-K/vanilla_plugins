<?php
if (!defined('APPLICATION'))
    die();

$PluginInfo['TimelineGame'] = array(
    'Name' => 'TimelineGame',
    'EventDescription' => 'Plugin to play a timeline game.',
    'Version' => '1.0',
    'Author' => "Tom",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'RequiredApplications' => array('Vanilla' => '>=2'),
    'MobileFriendly' => TRUE,
    'RequiredPlugins' => array('PluginCommandParser' => 1.0),
    'SettingsUrl' => '/settings/timelinegame'
);

class TimelineGamePlugin extends Gdn_Plugin {
    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex=$Sender->EventArguments['CommandIndex'];
        $commands=[
            "[tgstart]X[/tgstart]"=>t("Start a new game!"),
            "[tgcheck]"=>t("Check the time line for errors. If there is one, last person to add an event loses. Otherwise, you lose."),
            "[timeline=X]
(1):<--
(2): Battle of Hastings.
(3): WOII
[/timeline]"=>t("Event takes place before the other events."),
            "[timeline=X]
(1): Battle of Hastings.
(2): <--
(3): WOII
[/timeline]"=>t("Event takes place before event 3 and after event 1."),
            "[timeline=X]
(1): Battle of Hastings.
(2): WOII
(3): <--
[/timeline]"=>t("Event takes place after all other events.")
            ];
            $commandIndex->addCommands($commands,$this);
    }
    private $currentGame;
    private $currentPost;

    public function __construct() {
        parent::__construct();
    }

    public function getCurrentGameID() {
        if ($this->currentGame > 0) {
            return $this->currentGame;
        }
        $this->currentGame = false;
        $activeGame = Gdn::sql()->select("GameID")->from("TGTimeLineGames")->where(["ParentID" => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'LostBy' => 0])->orderBy("GameID", "desc")->limit(1)->get()->firstRow();
        if ($activeGame) {
            $this->currentGame = $activeGame->GameID;
        }
        return $this->currentGame;
    }

    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        $BBCode->addRule('timeline', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'SaveTimeLine'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
        $BBCode->addRule('tgstart', Array(
            'end_tag' => BBCODE_PROHIBIT,
            'method' => Array($this, 'startTimeLine'),
        ));
        $BBCode->addRule('tgcheck', Array(
            'end_tag' => BBCODE_PROHIBIT,
            'method' => Array($this, 'checkTimeLine'),
        ));
    }

    public function checkTimeLine($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        $gameID = $this->getCurrentGameID();
        if ($gameID) {
            $gameToCheck = gdn::sql()->select("LastUserID")->from("TGTimeLineGames")->where(['GameID' => $gameID, 'LostBy' => 0])->get()->firstRow();
            if ($gameToCheck) {
                $checkedTimeLine = $this->getCompleteTimeLine($gameID);
                $correct = $checkedTimeLine->Correct;
                $loser = $correct ? gdn::session()->UserID : $gameToCheck->LastUserID;
                $loser_name = strip_tags((new UserModel())->getID($loser)->Name, ENT_QUOTES);
                $finalHTML = t("The timeline was")." <strong>" . t($correct ? "correct" : "incorrect") . "</strong>. $loser_name".t(" loses! ") . $checkedTimeLine->HTML;
                gdn::sql()->update("TGTimeLineGames")->set(['HTML' => $finalHTML, 'LostBy' => $loser])->where('GameID', $gameID)->put();
                return "[timeline=$gameID]Won![/]";
            }
        }
        return false;
    }

    public function startTimeLine($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        $userID = gdn::session()->UserID;
        $startEvent = $this->getRandomEvent();
        $secondEvent = $this->getRandomEvent();
        $GameID = Gdn::sql()->insert("TGTimeLineGames", ["HTML" => "<p class='TGNewEvent'>New event to place: " . $secondEvent->EventDescription . "</p><br/>(1): " . $startEvent->EventDescription, "ParentID" => $this->currentPost->ParentID, "ParentType" => $this->currentPost->ParentType, "NewEventID" => $secondEvent->EventIndex, 'TimeLineName' => $secondEvent->TimeLineName, "LastUserID" => $userID]);
        gdn::sql()->insert("TGGameLinks", ["EventID" => $startEvent->EventIndex, "TimeLineName" => $secondEvent->TimeLineName, "GameID" => $GameID, "BeforeID" => -1, "AfterID" => 0, "UserID" => $userID]);
        return "[timeline=$GameID](1): " . $startEvent->EventDescription . "[/timeline]";
    }

    public function saveTimeLine($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        $timelineID = $params["_default"];
        if (!is_numeric($timelineID)) {
            return false;
        }
        $this->currentGame = $timelineID;
        $game = gdn::sql()->select("NewEventID,TimeLineName,LostBy")->from("TGTimeLineGames")->where("GameID", $timelineID)->get()->firstRow();
        if (!$game) {
            return false;
        }
        if ($game->LostBy > 0) {
            return "[timeline=$timelineID]Won![/]";
        }
        $newEvent = false;
        $timeline = trim($content);
        $arrowPos = strpos($timeline, '<--');
        $beginPos = strrpos($timeline, '(', $arrowPos - strlen($timeline));
        if ($beginPos !== false) {
            $beginPos++;
        }
        $endPos = strpos($timeline, ')', $beginPos);
        $position = substr($timeline, $beginPos, $endPos - $beginPos);
        if (is_numeric($timelineID) && is_numeric($position) && $position > 0) {

            $newEvent = $this->getEvent($game->NewEventID, $game->TimeLineName);
            $userID = gdn::session()->UserID;
            $html = $this->addEventToTimeLine($timelineID, $position - 1, $userID, $newEvent);
            if ($html) {
                return $html;
            }
        }
        return false;
    }

    public function addEventToTimeLine($timelineID, $eventPlace, $userID, $newevent) {
        if ($eventPlace < 0) {
            return false;
        }
        $events = gdn::sql()->select("TimeLineName,EventID,LinkID,BeforeID,AfterID")->from("TGGameLinks")->where("GameID", $timelineID)->get();
        $previousTimeLine = [];
        if ($eventPlace > $events->numRows()) {
            return false;
        }
        $last = $eventPlace === $events->numRows();
        while (($row = $events->nextRow())) {
            $oldEvent = $this->getEvent($row->EventID, $row->TimeLineName);
            $row->Event = $oldEvent;
            $previousTimeLine[$row->AfterID] = $row;
            if ($last && $row->BeforeID < 0) {
                $newIndex = gdn::sql()->insert("TGGameLinks", ['EventID' => $newevent->EventIndex, 'TimeLineName' => $newevent->TimeLineName, 'BeforeID' => -1, 'AfterID' => $row->LinkID, 'GameID' => $timelineID, 'UserID' => $userID]);
                gdn::sql()->update("TGGameLinks")->set("BeforeID", $newIndex)->where("LinkID", $row->LinkID)->put();
            }
        }
        $eventIndex = 0;
        $i = 0;
        $eventText = "";
        while (isset($previousTimeLine[$eventIndex])) {
            if ($i === $eventPlace) {
                $newIndex = gdn::sql()->insert("TGGameLinks", ['EventID' => $newevent->EventIndex, 'TimeLineName' => $newevent->TimeLineName, 'BeforeID' => $previousTimeLine[$eventIndex]->LinkID, 'AfterID' => $eventIndex, 'GameID' => $timelineID, 'UserID' => $userID]);
                if ($newIndex) {
                    gdn::sql()->update("TGGameLinks")->set("AfterID", $newIndex)->where("LinkID", $previousTimeLine[$eventIndex]->LinkID)->put();
                    gdn::sql()->update("TGGameLinks")->set("BeforeID", $newIndex)->where("LinkID", $previousTimeLine[$eventIndex]->AfterID)->put();
                }
                $eventText.="\n(" . ( ++$i) . '):' . $newevent->EventDescription;
            }
            $eventText.="\n(" . ( ++$i) . '): ' . $previousTimeLine[$eventIndex]->Event->EventDescription;
            $eventIndex = $previousTimeLine[$eventIndex]->LinkID;
        }
        if ($last) {
            $eventText.="\n(" . ( ++$i) . '): ' . $newevent->EventDescription;
        }
        $newEvent = $this->getRandomEvent();
        $HTML = "<p class='TGNewEvent'>New event to place: " . $newEvent->EventDescription . "</p>" . str_replace("\n", "<br/>", $eventText);
        gdn::sql()->update("TGTimeLineGames", ['NewEventID' => $newEvent->EventIndex, 'HTML' => $HTML, 'TimeLineName' => $newEvent->TimeLineName, 'LastUserID' => $userID], ['GameID' => $timelineID])->put();
        return "[timeline=$timelineID]" . substr($eventText, 1) . "[/timeline]";
    }

    public function getCompleteTimeLine($gameID) {
        $timeLine = new stdClass();
        $correct = true;
        $events = gdn::sql()->select("TimeLineName,EventID,LinkID,BeforeID,AfterID")->from("TGGameLinks")->where("GameID", $gameID)->get();
        $previousTimeLine = [];
        while (($row = $events->nextRow())) {
            $oldEvent = $this->getEvent($row->EventID, $row->TimeLineName);
            $row->Event = $oldEvent;
            $previousTimeLine[$row->AfterID] = $row;
        }
        $eventIndex = 0;
        $i = 0;
        $eventText = "";
        $previousYear = $previousTimeLine[$eventIndex]->Event->Year;
        while (isset($previousTimeLine[$eventIndex])) {
            $rowHTML = "(" . ( ++$i) . '): ' . $previousTimeLine[$eventIndex]->Event->Year . ':' . $previousTimeLine[$eventIndex]->Event->EventDescription;
            if ($previousYear > $previousTimeLine[$eventIndex]->Event->Year) {
                $rowHTML = "<span class='TGWrong'>$rowHTML</span>'";
                $correct = false;
            }
            $previousYear = $previousTimeLine[$eventIndex]->Event->Year;
            $eventText.="<br/>$rowHTML";
            $eventIndex = $previousTimeLine[$eventIndex]->LinkID;
        }
        $timeLine->HTML = $eventText;
        $timeLine->Correct = $correct;
        return $timeLine;
    }

    public function renderTimeLine($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (!is_numeric($default)) {
            return false;
        }
        if (isset($this->timeLineHTML[$default])) {
            return $this->timeLineHTML[$default];
        }
        $timeline = gdn::sql()->select("HTML")->from("TGTimeLineGames")->where("GameID", $default)->get()->firstRow();
        if ($timeline) {
            return $this->timeLineHTML[$default] = $timeline->HTML;
        }
        return $this->timeLineHTML[$default] = false;
    }

    public function PluginCommandParserPlugin_BeforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $Sender->addCSS($this->getPostCSSToAdd());
        $BBCode->addRule('timeline', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'RenderTimeLine'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
    }

    public function SettingsController_TimeLineGame_Create($Sender) {
        $Sender->permission('Garden.Moderation.Manage');
        if (checkPermission('Garden.Moderation.Manage')) {
            $Sender->Title($this->GetPluginKey('Name'));
            $Sender->SetData('Description', $this->GetPluginKey('Description'));
            $this->addSettingsCSS();
            $Sender->AddJsFile($this->GetResource('js/timelinegame_functions.js', FALSE, FALSE));
            $Sender->addSideMenu('dashboard/settings');
            $Sender->Render($this->GetView('timelinegame_settings.php'));
        }
    }

    public function Base_GetAppSettingsMenuItems_Handler($Sender) {
        $Menu = $Sender->EventArguments['SideMenu'];
        $Menu->AddItem('Forum', T('Forum'));
        $Menu->AddLink('Forum', T('TimeLine'), '/settings/timelinegame', 'Garden.Settings.Manage');
    }

    public function getEvent($EventIndex, $TimeLineName) {
        if (!gdn::structure()->tableExists($this->getTimeLinePath($TimeLineName))) {
            return false;
        }
        $result = gdn::sql()->select("Year,EventDescription,EventKey,EventIndex")->from($this->getTimeLinePath($TimeLineName))->where("EventIndex", $EventIndex)->get()->firstRow();
        $result->TimeLineName = $TimeLineName;
        $result->EventDescription = strip_tags($result->EventDescription);
        return $result;
    }

    public function getRandomEvent($specificLibrary = false) {
        $query = gdn::sql()->select("NumEvents,TimeLine")->from("TGTimeLinesNames");
        if ($specificLibrary) {
            foreach ($specificLibrary as $library) {
                $query->orWhere("TimeLine", $library);
            }
        }
        $timeLines = $query->get();
        $total = 0;
        $drawPile = [];
        while (($row = $timeLines->nextRow())) {
            if (gdn::structure()->tableExists($this->getTimeLinePath($row->TimeLine))) {
                $total+=$row->NumEvents;
                $drawPile[$total] = $row->TimeLine;
            } else {
                gdn::sql()->delete("TGTimeLinesNames", ['TimeLine' => $row->TimeLine]);
            }
        }
        if ($total < 1) {
            return false;
        }
        for ($i = 0; $i < 100; $i++) {
            $eventIndex = crypto_rand_secure(0, $total - 1);
            $event_lower = 0;
            foreach ($drawPile as $event_upper => $name) {
                if ($eventIndex < $event_upper) {
                    $index = $eventIndex - $event_lower + 1;
                    $event = $this->getEvent($index, $name);
                    if ($event) {
                        return $event;
                    }
                    continue;
                }
                $event_lower = $event_upper;
            }
        }
        return false;
    }

    public function addNewEventsFromFile($unsafe_name, $unsafe_content) {
        $extensionIndex = strrpos($unsafe_name, '.');
        if ($extensionIndex) {
            $unsafe_name = substr($unsafe_name, 0, $extensionIndex);
        }
        $safe_name = preg_replace("/[^a-z0-9]/", '', strtolower($unsafe_name));
        $unsafe_newEvents = explode("\n", $unsafe_content);
        $this->addNewEvents($unsafe_newEvents, $safe_name);
        Gdn::sql()->replace("TGTimeLinesNames", ['TimeLine' => $safe_name, 'NumEvents' => gdn::sql()->getCount($this->getTimeLinePath($safe_name)), 'UserID' => Gdn::session()->UserID], ['TimeLine' => $safe_name]);
    }

    public function PluginController_TimeLine_Create($Sender, $Args) {
        if (!gdn::session()->checkPermission("Garden.Settings.Manage")) {
            echo "{\"error\":\"no permission\"}";
            exit();
        }
        if ($Args[0] == "random") {
            echo $this->getRandomWord();
        }
        if ($Args[0] == "upload") {
            if (isset($_POST['name']) && isset($_POST['content'])) {
                $this->addNewEventsFromFile($_POST['name'], $_POST['content']);
            }
        }
        if ($Args[0] == "download") {
            if (is_numeric($Args[1])) {
                $timeLine = gdn::sql()->select("TimeLine")->from("TGTimeLinesNames")->where("TimeLineID", $Args[1])->get()->firstRow();
                if ($timeLine) {
                    header("Content-type: text/csv");
                    header("Cache-Control: no-store, no-cache");
                    header('Content-Disposition: attachment; filename="' . $timeLine->TimeLine . '.csv"');
                    $events = gdn::sql()->select("EventDescription,Year")->from($this->getTimeLinePath($timeLine->TimeLine))->get();
                    if ($row = $events->nextRow()) {
                        echo $row->Year . ':' . $row->EventDescription;
                    }
                    while ($row = $events->nextRow()) {
                        echo "\n" . $row->Year . ':' . $row->EventDescription;
                    }
                    exit();
                }
            }
        }
        if ($Args[0] == "remove") {
            if (isset($_POST['TimeLineID']) && is_numeric($_POST['TimeLineID'])) {
                $dic_id = intVal($_POST['TimeLineID']);
                $timeLine = gdn::sql()->select("TimeLine")->from("TGTimeLinesNames")->where("TimeLineID", $dic_id)->get()->firstRow();
                if ($timeLine) {
                    gdn::sql()->delete("TGTimeLinesNames", ["TimeLineID" => $dic_id]);
                    Gdn::structure()->table($this->getTimeLinePath($timeLine->TimeLine))->drop();
                }
            }
        }
    }

    public function getTimeLinePath($timeLine) {
        return "tgtimeLines_$timeLine";
    }

    public function setup() {
        Gdn::structure()->Table("TGTimeLinesNames")
                ->primaryKey("TimeLineID")
                ->column("TimeLine", 'varchar(255)', false, 'unique')
                ->column("NumEvents", 'int')
                ->column("UserID", 'int')->set();
        Gdn::structure()->Table("TGTimeLineGames")
                ->primaryKey("GameID")
                ->column("NewEventID", 'int')
                ->column("TimeLineName", 'varchar(255)')
                ->column("ParentID", 'int')
                ->column('ParentType', 'int(3)')
                ->column("LastUserID", 'int')
                ->column("LostBy", 'int', 0)
                ->column("HTML", 'text', '')->set();
        Gdn::structure()->Table("TGGameLinks")
                ->primaryKey("LinkID")
                ->column("EventID", 'int')
                ->column("TimeLineName", 'varchar(255)')
                ->column("GameID", 'int')
                ->column("BeforeID", 'int', -1)
                ->column("AfterID", 'int', 0)
                ->column("UserID", 'int')->set();
        $default_timeLines = scandir(__DIR__ . '/timeLines');
        foreach ($default_timeLines as $timeLine_to_add) {
            if (stripos($timeLine_to_add, ".csv")) {
                $this->addNewEventsFromFile($timeLine_to_add, file_get_contents(__DIR__ . '/timeLines/' . $timeLine_to_add));
            }
        }
    }

    function addNewEvents($unsafe_newEvents, $timeLine) {
        $table_name = $this->getTimeLinePath($timeLine);
        Gdn::structure()
                ->Table($table_name)
                ->primaryKey("EventIndex")
                ->column("Year", 'int')
                ->column("EventKey", 'varchar(40)', false, 'unique')
                ->column("EventDescription", 'text')->set();
        $existing_events = [];
        $all_events_result = gdn::sql()->select("EventKey")->from($table_name)->get();
        while ($row = $all_events_result->nextRow()) {
            $existing_events[$row->EventKey] = true;
        }
        foreach ($unsafe_newEvents as $unsafe_event) {
            $safe_event = htmlentities($unsafe_event, ENT_QUOTES);
            $split = strpos($safe_event, ":");
            if (!$split) {
                continue;
            }
            $year = substr($safe_event, 0, $split);
            $text = substr($safe_event, $split + 1);
            $key = sha1($text);
            if (is_numeric($year) && !isset($existing_events[$key])) {
                $existing_events[$key] = true;
                //If there isn't a word yet in the database, add it to the database
                Gdn::sql()->insert($table_name, ['Year' => $year, 'EventKey' => $key, 'EventDescription' => $text]);
            }
        }
    }

    public function addSettingsCSS() {
        ?><style>
            .TimeLineList
            {
                width:100%;
            }
            .TimeLineItem
            {
                margin:10px;
                width:90%;
                border:solid burlywood;
                background-color: beige;
                display:list-item;
            }
            .TimeLineItem p
            {
                display: inline;
            }
            .TimeLineButtons
            {
                float:right;
            }
            .TimeLineButtons p
            {

                cursor:pointer;
                border: 1px solid black;
                margin:2px;
            }
            .TimeLineDescription
            {
                width:70%;
            }
        </style>
        <?php
    }

    public function getPostCSSToAdd(){ return "
            .TGNewEvent{
                font-style:italic;
            }
            .TGWrong
            {
                font-weight: bold;
            }";
    }

}
