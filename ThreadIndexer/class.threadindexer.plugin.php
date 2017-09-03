<?php
if (!defined('APPLICATION')) {
    exit();
}

$PluginInfo['ThreadIndexer'] = array(
    'Name' => 'ThreadIndexer',
    'Description' => 'Enables you to create an index of a thread.',
    'Version' => '1.0',
    'Author' => "Tom Sassen",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'MobileFriendly' => TRUE,
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('PluginCommandParser' => '1.0'),
    'RegisterPermissions' => ['Plugins.ThreadIndexer.CreateIndex' => 'Garden.Moderation.Manage', 'Plugins.ThreadIndexer.AddEntry' => 1]
);

class ThreadIndexerPlugin extends Gdn_Plugin {

    private $currentPost;
    private $currentCommentID;
    private $IndexHTML;

    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex=$Sender->EventArguments['CommandIndex'];
        $commands=[
            "[tiindex]X[/tiindex]"=>t("Only works in the first post of a discussion! Creates the index. You can only have one index per discussion."),
            "[tientry]X[/tientry]"=>t("Adds a link with title X to this comment in the index.")];
            $commandIndex->addCommands($commands,$this);
    }
    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        if ($this->currentPost->Type === CurrentPost::DISCUSSION_TYPE && checkPermission('Plugins.ThreadIndexer.CreateIndex')) {
            $BBCode->AddRule('tiindex', Array(
                'method' => Array($this, 'createIndex'),
            ));
        } else if ($this->currentPost->Type === CurrentPost::COMMENT_TYPE && checkPermission("Plugins.ThreadIndexer.AddEntry")) {
            $this->currentCommentID = $this->currentPost->OwnID;
            gdn::sql()->delete("TIEntries", ['CommentID' => $this->currentCommentID]);
            $BBCode->AddRule('tientry', Array(
                'method' => Array($this, 'createEntry'),
            ));
        }
    }

    public function PluginCommandParserPlugin_BeforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $Sender->addCSS($this->getCSSToAdd());
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        $BBCode->AddRule('tishowindex', Array(
            'method' => Array($this, 'showIndex'),
            'end_tag' => BBCODE_PROHIBIT
        ));
        $BBCode->AddRule('tientry', Array(
            'method' => Array($this, 'showEntry'),
        ));
    }

    public function __construct() {
        parent::__construct();
        $this->IndexHTML = [];
    }

    public function setup() {
        parent::setup();
        gdn::structure()->
                table('TIIndices')->
                column('DiscussionID', 'int', false, 'primary')->
                column('Title', 'varchar(30)')
                ->column("HTML", "Text")->set();
        gdn::structure()->
                table('TIEntries')->
                primaryKey('EntryID')->
                column('UserID', 'int')->
                column('DiscussionID', 'int')->
                column('CommentID', 'int')->
                column('Title', 'varchar(30)')->set();
    }

    function createIndex($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        $title = ucfirst($content);
        if (empty($title) || strlen($title) > 30) {
            gdn::controller()->informMessage("Index title is too short/long!");
            return false;
        }
        $discussionID = $this->currentPost->ParentID;
        gdn::sql()->replace('TIIndices', ['DiscussionID' => $discussionID, 'Title' => $title], ['DiscussionID' => $this->currentPost->ParentID], true);
        $this->needsUpdate = $discussionID;
        return "[tishowindex]";
    }

    function commentModel_AfterSaveComment_Handler($Sender) {
        $this->updateIndex();
    }

    function discussionModel_AfterSaveDiscussion_Handler($Sender) {
        $this->updateIndex();
    }

    function createEntry($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        $title = ucfirst($content);
        if (empty($title) || strlen($title) > 30) {
            gdn::controller()->informMessage("Entry title is too short/long!");
            return "[tientry=error]$title" . "[/tientry]";
        }
        $entriesWithoutComment = gdn::sql()->select('e.EntryID')->from("TIEntries e")->leftJoin("Comment c", "c.CommentID=e.CommentID")->where("c.CommentID", null)->get();
        while ($entry = $entriesWithoutComment->nextRow()) {
            gdn::sql()->delete('TIEntries', ['EntryID' => $entry->EntryID]);
        }
        $EntryID = gdn::sql()->insert('TIEntries', ['UserID' => gdn::session()->UserID, 'CommentID' => $this->currentPost->OwnID, 'DiscussionID' => $this->currentPost->ParentID, 'Title' => $title]);
        if ($EntryID) {
            $this->needsUpdate = $this->currentPost->ParentID;

            return "[tientry]$title" . "[/]";
        }
        return false;
    }

    public function updateIndex() {
        if (isset($this->needsUpdate) && $this->needsUpdate > 0) {
            $html = $this->getIndexHTML($this->needsUpdate);
            gdn::sql()->update("TIIndices")->set("HTML", $html)->where('DiscussionID', $this->needsUpdate)->put();
        }
    }

    function showEntry($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if ($default == 'error') {
            return "[tientry]$content" . "[/tientry]";
        }
        return "<p class='TIEntry'>$content</p>";
    }

    function getIndexHTML($indexID) {
        $html = "";
        $index = gdn::sql()->select("Title")->from("TIIndices")->where("DiscussionID", $this->currentPost->ParentID)->get()->firstRow();
        if (!$index) {
            return false;
        }
        $title = htmlentities($index->Title, ENT_QUOTES);
        $html.="<div class='TIIndex'><p class='TIIndexTitle'>$title</p>";
        $entries = gdn::sql()->select("Title,CommentID")->from("TIEntries")->where("DiscussionID", $this->currentPost->ParentID)->orderBy("Title")->get();
        $previousChar = false;
        while ($entry = $entries->nextRow()) {
            $title = htmlentities($entry->Title, ENT_QUOTES);
            $CommentModel = new CommentModel();
            $CommentModel->CommentID = $entry->CommentID;
            $link = commentUrl($CommentModel);
            $firstChar = ctype_alnum($title[0]) ? $title[0] : '-';
            if ($firstChar != $previousChar) {
                if ($previousChar) {
                    $html.= "</ul>";
                }
                $previousChar = $firstChar;
                $html.= "<p class='TIEntryChar'>$firstChar</p><ul class='TIEntry'>";
            }
            $html.= "<li>" . wrap($title, 'a', ['href' => $link]) . "</li>";
        }
        if ($previousChar) {
            $html.= "</ul>";
        }
        $html.= "</div>";
        return $html;
    }

    function showIndex($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (isset($this->IndexHTML[$this->currentPost->ParentID])) {
            return $this->IndexHTML[$this->currentPost->ParentID];
        }
        $index = gdn::sql()->select("HTML")->from("TIIndices")->where("DiscussionID", $this->currentPost->ParentID)->get()->firstRow();
        $this->IndexHTML[$this->currentPost->ParentID] = $index ? $index->HTML : false;
        return $this->IndexHTML[$this->currentPost->ParentID];
    }

    // Adds CSS to the relevant pages
    public function getCSSToAdd(){"
            .TIIndex
            {

            }
            .TIEntry
            {
                font-weight:bold;
                font-style: italic;
            }
            .TIIndexTitle
            {
                font-weight:bold;
                font-size: x-large;
            }
            .TIEntryChar
            {
                font-weight:bold;
            }
            .TIIndex ul.TIEntry li
            {
                list-style-type: none !important;

            }";
    }

}
