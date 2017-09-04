<?php
if (!defined('APPLICATION')) {
    exit();
}
if (!defined("BBCODE_CHECK")) {
    define("BBCODE_CHECK", 1);
    define("BBCODE_MODE_CALLBACK", 1);
    define("BBCODE_PROHIBIT", -1);
}

$PluginInfo['PluginCommandParser'] = array(
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'Description' => 'Parses comments, discussions & PMs with custom commands',
    'HasLocale' => true,
    'MobileFriendly' => TRUE,
    'Name' => 'PluginCommandParser',
    'RegisterPermissions' => ['Plugins.PluginCommandParser.ShowExplanation' => 'Garden.Moderation.Manage'],
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'SettingsUrl' => '/settings/plugincommandparser',
    'Version' => '1.0'
);

class PluginCommandParserPlugin extends Gdn_Plugin {

    private $css = "";
    private $js = "";
    private $javascript = "";

    public function addCSS($css) {
        $this->css.="\n" . preg_replace('/\s+/', ' ', $css);
    }

    public function addJS($js) {
        $this->js.="\n$js";
    }

    /**
     * Makes a list of all plugins that are currently enabled and use this command interface
     */
    public function getExplanation() {
        $html = t("This is a list of the available commands on this forum. Note: end tags can be abbreviated to '[/]'.");
        if (checkPermission("Plugins.PluginCommandParser.ShowExplanation")) {
            $html.="<br/>" . t("Get more specific information about a plugin with [explanation]PluginName[/explanation]");
        }
        $commandIndex = $this->getAvailableCommands();
        $sortedNames = get_object_vars($commandIndex);
        ksort($sortedNames);
        foreach ($sortedNames as $name => $plugin) {
            $html.="<br/><p class='ExpPluginName'>" . $name . "</p>";
            $html.=$this->getListOfCommandsInHTMLFormat($plugin, $name);
        }
        return $html;
    }

//This parser is used before posts are saved to the database.
    private $beforeSaveToDBParser;
//This parser is used before posts are displayed
    private $beforeDisplayParser;
    private $shouldRender = false;
    private $ExplanationHTML;

    /**
     * Includes the Parser, CurrentPost and CommandIndex class
     */
    function __construct() {
        parent::__construct();
        require_once __DIR__ . '/StrippedNBBCParser.php';
        require_once __DIR__ . '/helperclasses.php';
        $this->beforeSaveToDBParser = false;
        $this->beforeDisplayParser = false;
    }

    public function setup() {
        parent::setup();
    }

    /**
     * Creates a settings page.
     * You can set Plugins.PluginCommandParser.EnabledInConversation to enable custom commands in private messages
     * You can set Plugins.PluginCommandParser.ReloadOnNewMessage to make the page refresh after each private message in case some plugins interfere badly with this plugin
     */
    public function SettingsController_PluginCommandParser_Create($Sender) {
        //This function creates a setting page
        $Sender->Permission('Garden.Settings.Manage');
        if (checkPermission('Garden.Settings.Manage')) {
            $Sender->AddSideMenu('dashboard/settings/plugins');
            $Sender->SetData('Title', T('Game Command Handler Settings'));
            //Create a new ConfigurationModule which will build the settings page for us.
            $Conf = new ConfigurationModule($Sender);
            //Add input fields
            $Conf->Initialize(array(
                "Plugins.PluginCommandParser.EnabledInConversation" => array(
                    'Control' => 'checkbox',
                    'Default' => true,
                    'LabelCode' => T('Whether or not to also enable functions in private messages.')
                ),
                'Plugins.PluginCommandParser.ReloadOnNewMessage' => array(
                    'Control' => 'checkbox',
                    'Default' => false,
                    'LabelCode' => T('Whether or not to reload the whole page on posting a private message. Use this option if it interferes with other plugins.')
                )
            ));
            $Conf->RenderAll();
        }
    }

    function parsePostBeforeSave($currentPost) {
        //If an anonymous poster tries to post, refuse to parse commands.
        if (gdn::session()->UserID < 1) {
            return $currentPost->Body;
        }
        $this->EventArguments['CurrentPost'] = $currentPost;
        //If the parser hasn't been initialized yet, initialize it.
        if ($this->beforeSaveToDBParser === false) {
            $this->beforeSaveToDBParser = new BeforeSaveToDBParser();
            $this->EventArguments['Parser'] = $this->beforeSaveToDBParser;
            //This event is fired once to gather all the custom commands of other plugins
            $this->FireEvent('BeforeSaveParserSetup');
            //If the user hasn't the correct permission to add explanation commands, remove them from the post.
            if (!checkPermission("Plugins.PluginCommandParser.ShowExplanation")) {
                $this->beforeSaveToDBParser->addRule('explanation', []);
                $this->beforeSaveToDBParser->addRule('pluginexplanation', []);
            }
        }
        $this->FireEvent('BeforeSaveParse');
        //Parse the post
        $Body = $this->beforeSaveToDBParser->parse($currentPost->Body, $currentPost);
        return $Body;
    }

    function format($Mixed) {
        global $currentPM;
        if (isset($currentPM->OldFormat)) {
            $currentPM->Format = $currentPM->OldFormat;
        }
        $result = Gdn_Format::to($Mixed, $currentPM->Format);
        $currentPost = new CurrentPost($currentPM->MessageID, $currentPM->ConversationID, $currentPM->Format, $result, 'Message');
        return $this->parsePostBeforeDisplay($currentPost);
    }

    public function parsePostBeforeDisplay($currentPost) {
        $this->EventArguments['CurrentPost'] = $currentPost;
        if ($this->beforeDisplayParser === false) {
            $this->beforeDisplayParser = new BeforeDisplayParser();
            $this->beforeDisplayParser->addRule('explanation', ['method' => Array($this, 'lookupExplanation')]);
            $this->beforeDisplayParser->addRule('pluginexplanation', ['method' => Array($this, 'lookupExplanation')]);
            $this->EventArguments['Parser'] = $this->beforeDisplayParser;
            $this->FireEvent('BeforeDisplaySetup');
            if ($this->shouldRender) {
                echo "<style>" . $this->css . "</style><script>" . $this->js . "\n</script>";
            }
        }
        $this->EventArguments['Parser'] = $this->beforeDisplayParser;
        $this->FireEvent('BeforeDisplayParse');
        return $this->beforeDisplayParser->parse($currentPost->Body, $currentPost);
    }

    public function lookupExplanation($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (empty($this->ExplanationHTML)) {
            $this->ExplanationHTML = [];
        }
        if (isset($this->ExplanationHTML[$content])) {
            return $this->ExplanationHTML[$content];
        }
        $enabledplugins = array_change_key_case(Gdn::pluginManager()->enabledPlugins(), CASE_LOWER);
        if (arrayHasValue($enabledplugins, strtolower($content))) {
            $classname = false;
            if (class_exists($content)) {
                $classname = $content;
            } else if (class_exists($content . "Plugin")) {
                $classname = $content . "Plugin";
            }
            $explanation = t(strtolower($classname . ".Explanation"), "");
            if ($classname === $this->ClassName && !$explanation) {
                $explanation = $this->getExplanation();
            }
            if ($explanation) {
                return $this->ExplanationHTML[$content] = $this->getSummaryOfPluginCommands($classname,$explanation);
            } else if ($info = gdn::pluginManager()->getPluginInfo($content)) {
                return $this->ExplanationHTML[$content] = "<p class='ExpPluginName'>" . $info['Name'] . "</p>" . $info['Description'];
            } else {
                return $this->ExplanationHTML[$content] = false;
            }
        }
        return $this->ExplanationHTML[$content] = false;
    }

    public function getListOfCommandsInHTMLFormat($commands, $name) {
        if (empty($this->commandHTML)) {
            $this->commandHTML = [];
        }
        if (isset($this->commandHTML[$name])) {
            return $this->commandHTML[$name];
        }
        $html = "<hr><span class='ExpAvailableCommands'>" . T("Available commands") . ":</span>";
        $hasCommands = false;
        foreach ($commands as $title => $explanation) {
            $hasCommands = true;
            $html.=str_replace("\n", "<br/>", htmlentities("\n$title: " . $explanation, ENT_QUOTES));
        }
        return $this->commandHTML[$name] = ($hasCommands ? $html : "" );
    }

    function getAvailableCommands($SpecificPlugin = false) {
        $commandIndex = new CommandIndex();
        $this->EventArguments['CommandIndex'] = $commandIndex;
        $Args = false;
        if ($SpecificPlugin === false) {
            $this->FireEvent('AvailableCommandsSetup');
        } else if (method_exists($SpecificPlugin, "PluginCommandParserPlugin_AvailableCommandsSetup_Handler")) {
            $SpecificPlugin->PluginCommandParserPlugin_AvailableCommandsSetup_Handler($this, $Args);
        }
        return $commandIndex;
    }

    public function initializeCSSAndJS() {
        $this->shouldRender = true;
        $this->addCSS("
            .ExpOtherStart
            {
                font-style: italic;
            }
            .ExpPluginName
            {
                font-size:large;
                font-style: italic;
            }
            .ExpAvailableCommands
            {
                font-weight:bold;
            }");
    }

    public function DiscussionController_Render_Before($Sender) {
        $this->initializeCSSAndJS();
    }

    public function MessagesController_Render_Before($Sender) {
        $this->initializeCSSAndJS();
    }

    public function MessagesController_BeforeConversation_handler($Sender, $Args) {
        if (c('Plugins.PluginCommandParser.EnabledInConversation', true)) {
            Gdn::FactoryInstall('SpecialFormatter', 'PluginCommandParserPlugin', __FILE__, Gdn::FactorySingleton);
            $Messages = $Sender->data('Messages', array());
            foreach ($Messages as $Message) {
                $Message->OldFormat = $Message->Format;
                $Message->Format = "Special";
            }
        }
    }

    public function MessagesController_BeforeConversationMessageBody_handler($Sender) {
        if (c('Plugins.PluginCommandParser.EnabledInConversation', true)) {
            global $currentPM;
            $currentPM = $Sender->EventArguments['Message'];
            if ($currentPM && (!isset($currentPM->OldFormat))) {
                if (c('Plugins.PluginCommandParser.ReloadOnNewMessage', false)) {
                    ?><script>location.reload();</script><?php
                } else {
                    $currentPM->OldFormat = $currentPM->Format;
                    echo $this->format($currentPM->Body);
                    $currentPM->Body = '';
                }
                $currentPM = false;
            }
        }
    }

    public function DiscussionController_AfterCommentFormat_handler($Sender) {
        //echo serialize($Sender->EventArguments);
        $Format = $Sender->EventArguments['Discussion']->Format;
        $Type = $Sender->EventArguments['Type'];
        if ($Type === 'Discussion') {
            $Body = $Sender->EventArguments['Discussion']->FormatBody;
            $OwnID = $Sender->EventArguments['Discussion']->DiscussionID;
            $ParentID = $OwnID;
        } else {
            $Body = $Sender->EventArguments['Object']->FormatBody;
            $OwnID = $Sender->EventArguments['Object']->CommentID;
            $ParentID = $Sender->EventArguments['Object']->DiscussionID;
        }
        $currentPost = new CurrentPost($OwnID, $ParentID, $Format, $Body, $Type);
        $newbody = $this->parsePostBeforeDisplay($currentPost);
        if ($Type === 'Discussion') {
            $Sender->EventArguments['Discussion']->FormatBody = $newbody;
        } else {
            $Sender->EventArguments['Object']->FormatBody = $newbody;
        }
    }

    public function discussionModel_AfterSaveDiscussion_Handler($Sender, $Args) {
        //We need an PostID, so we can't use BeforeSaveHandler.
        //Just parse the post afterwards and update the body of the post in the database
        //My convention for PostID is: PostID = -1*DiscussionID or PostID = CommentID
        $Format = $Sender->EventArguments['Discussion']->Format;
        $ParentID = $Sender->EventArguments['DiscussionID'];
        $OwnID = $Sender->EventArguments['DiscussionID'];
        $Type = 'Discussion';
        $Body = $Sender->EventArguments['FormPostValues']['Body'];
        $currentPost = new CurrentPost($OwnID, $ParentID, $Format, $Body, $Type);
        $Sender->EventArguments['FormPostValues']['Body'] = $this->parsePostBeforeSave($currentPost);
        if (strlen($Sender->EventArguments['FormPostValues']['Body']) < 1) {
            if (c('Plugins.PluginCommandParser.DeleteEmptyPosts', false)) {
                (new DiscussionModel())->delete($Sender->EventArguments['DiscussionID']);
            }
            $Sender->EventArguments['FormPostValues']['Body'] = "[empty post]";
        }
        Gdn::sql()->update("Discussion")->set("Body", $Sender->EventArguments['FormPostValues']['Body'])->where("DiscussionID", $Sender->EventArguments['DiscussionID'])->put();
    }

    public function conversationMessageModel_AfterSave_Handler($Sender) {

        if (c('Plugins.PluginCommandParser.EnabledInConversation', true)) {
            $Message = $Sender->EventArguments['Message'];
            $currentPost = new CurrentPost($Message->MessageID, $Message->ConversationID, $Message->Format, $Message->Body, 'Message');
            $Message->Body = $this->parsePostBeforeSave($currentPost);
            if (strlen($Message->Body)) {
                gdn::sql()->update("ConversationMessage")->set("Body", $Message->Body)->where("MessageID", $currentPost->OwnID)->put();
            } else {
                (new ConversationMessageModel())->delete(array('MessageID' => $Message->MessageID));
            }
        }
    }

    public function commentModel_AfterSaveComment_Handler($Sender, $Args) {
        $currentPost = new CurrentPost($Sender->EventArguments['CommentID'], $Sender->EventArguments['FormPostValues']['DiscussionID'], $Sender->EventArguments['FormPostValues']['Format'], $Sender->EventArguments['FormPostValues']['Body'], 'Comment');

        //Same as discussionModel_AfterSaveDiscussion_Handler but for comments
        $Sender->EventArguments['FormPostValues']['Body'] = $this->parsePostBeforeSave($currentPost);
        if (strlen($Sender->EventArguments['FormPostValues']['Body']) > 0) {
            Gdn::sql()->update("Comment")->set("Body", $Sender->EventArguments['FormPostValues']['Body'])->where("CommentID", $Sender->EventArguments['CommentID'])->put();
        } else {
            (new CommentModel())->delete($Sender->EventArguments['CommentID']);
        }
    }

    public function getSummaryOfPluginCommands($classname,$explanation) {
                $plugin = new $classname();
                $pluginName = $plugin->getPluginName();
                $html = "<p class='ExpPluginName'>" . $pluginName . "</p>";
                $commands = $this->getAvailableCommands($plugin);
                $html.= $explanation;
                if (isset($commands->$pluginName)) {
                    $html.=$this->getListOfCommandsInHTMLFormat($commands->$pluginName, $pluginName);
                }
                unset($commands->$pluginName);
                $sortedNames = get_object_vars($commands);
                if (count($sortedNames) > 0) {
                    $html.="<br/><p class='ExpOtherStart'>" . $pluginName . t(" also uses the following commands of other plugins:") . "</p>";
                    ksort($sortedNames);
                    foreach ($sortedNames as $name => $otherplugin) {
                        $html.="<p class='ExpPluginName'>" . $name . "</p>";
                        $html.="" . $this->getListOfCommandsInHTMLFormat($otherplugin, $name);
                    }
                }
                return $html;
        
    }

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
