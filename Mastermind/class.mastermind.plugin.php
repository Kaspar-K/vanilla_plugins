<?php
if (!defined('APPLICATION'))
    die();

$PluginInfo['Mastermind'] = array(
    'Name' => 'Mastermind',
    'Description' => 'Mastermind plugin. Get instructions on proper usage by posting a post containing this command: [pluginexplanation]Mastermind[/pluginexplanation]',
    'Version' => '1.1',
    'Author' => "Tom",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'RequiredApplications' => array('Vanilla' => '>=2'),
    'MobileFriendly' => TRUE,
    'RequiredPlugins' => array('PluginCommandParser' => 1.0),
);

class MastermindPlugin extends Gdn_Plugin {
    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex=$Sender->EventArguments['CommandIndex'];
        $commands=[
            "[mg]".T("rood groen zwart blauw")."[/mg]"=>
            [0=>"Create a new code for people to guess with the specified colours.",
                'nl'=>"Maak een nieuwe code aan voor mensen om te raden met de genoemde kleuren."
                ],
            "[mr]X[/mr]"=>
            [0=>"Create a random code of length X.",
                'nl'=>"Maak een nieuwe random code aan van lengte X."],
            "[mg]".T("zwart groen geel blauw")."[/mg]"=>
            [0=>"Make a guess on either a quoted code or the latest code.",
                'nl'=>"Doe een gok op een gequote code of de laatstgeposte code."]
            ];
            $commandIndex->addCommands($commands,$this);
    }

    public function getExplanation() {
        return "A plugin to play MasterMind.

It has the followings colours: Red black yellow green blue orange white pink and x (as a hole).";
    }
    public function getExplanation_nl() {
        return "Een plugin om mastermind mee te spelen.

Het heeft de volgende kleuren: Rood zwart geel groen blauw oranje wit roze en x (als gat).";
    }

    private $currentCode;
    private $currentPost;

    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $this->currentCode = false;
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        //This rule will check if the mastercode is part of the original post, if not it will replace it with a link
        $BBCode->AddRule('mclink', Array(
            'method' => Array($this, 'createLinkToMasterCode'),
        ));
        $BBCode->AddRule('mmcode', $BBCode->tag_rules['mclink']);
        //[mr]X[/mr] creates a random code of lenght x.
        $BBCode->AddRule('mr', Array(
            'method' => Array($this, 'createRandomCode'),
        ));
        $BBCode->AddRule('mc', Array(
            'method' => Array($this, 'saveCodeTag'),
        ));
        $BBCode->AddRule('mg', Array(
            'method' => Array($this, 'saveGuessTag'),
        ));
    }

    public function createRandomCode($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content) && $content > 0 && $content < 20) {
            $random_code = [];
            for ($i = 0; $i < $content; $i++) {
                $random_code[] = mt_rand(0, 8);
            }

            $code = Code::saveMasterCode($random_code, $this->currentPost, true);
            if ($code) {
                return "[mmcode]" . $code . "[/mmcode]";
            }
        }
        if (empty($params['_tag']) || empty($content) || empty($params['_endtag'])) {
            return $content;
        }
        return htmlentities($params['_tag'], ENT_QUOTES) . $content
                . htmlentities($params['_endtag'], ENT_QUOTES);
    }

    public function createLinkToMasterCode($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $this->currentCode = $content;
            if (!Gdn::sql()->select("*")->from("MMCodes")->where(["CodeID" => $content, "PostID" => $this->currentPost->OwnID, 'Type' => $this->currentPost->Type])->get()->firstRow()) {
                return "[mclink]" . $content . "[/mclink]";
            }
        }
        return false;
    }

    public function saveCodeTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $code = Code::saveMasterCode($content, $this->currentPost);
        if ($code) {
            return "[mmcode]" . $code . "[/mmcode]";
        }
        return "[mc]" . $content . "[/mc]";
    }

    public function getCurrentCode() {
        if ($this->currentCode) {
            return $this->currentCode;
        }
        $this->currentCode = false;
        $activeCode = Gdn::sql()->select("CodeID")->from("MMCodes")->where(["ParentID" => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'Guessed' => 0])->orderBy("CodeID", "desc")->limit(1)->get()->firstRow();
        if ($activeCode) {
            $this->currentCode = $activeCode->CodeID;
        }
        return $this->currentCode;
    }

    public function saveGuessTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $currentCode = $this->getCurrentCode();
        if ($currentCode) {
            $result = Guess::saveCodeGuess($currentCode, $content);
            if ($result) {
                return "[mmguess]" . $result . "[/mmguess]";
            }
        }
        return "[mg]" . $content . "[/mg]";
    }

    public function RenderCodeTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $content = intval($content);
            if (isset($this->code_tags_rendered[$content])) {
                $text = $this->code_tags_rendered[$content];
            } else {
                $codeResult = Gdn::sql()->select("Code,PostID,Random,Guessed")->from("MMCodes")->where("CodeID", intval($content))->get()->firstRow();
                if ($codeResult) {
                    $random = $codeResult->Random;
                    $pins = explode(" ", $codeResult->Code);
                    $numPins = count($pins);
                    $guessResult = Gdn::sql()->select("GuessID")->from("MMGuesses")->where("CodeID", $content)->get();

                    $text = "<div class='MMCode" . ($random ? " MMRandom" : "") . "'><div class='MMHiddenCode'>";
                    for ($i = 0; $i < $numPins - 1; $i++) {
                        $text.=$codeResult->Guessed ? "<div class='MMPin' style='background-color: " . Code::getColorNameFromNumber($pins[$i]) . ";'></div>" : "x-";
                    }
                    $text.=$codeResult->Guessed ? "<div class='MMPin' style='background-color: " . Code::getColorNameFromNumber($pins[$numPins - 1]) . ";'></div>" : "x";
                    $text.="</div><div class='Spoiler'><p>Earlier guesses:</p>";
                    while ($row = $guessResult->nextRow()) {
                        $text.=$this->RenderGuessTag($bbcode, $action, $name, $default, $params, $row->GuessID);
                    }
                    $text.="</div></div>";
                    $this->code_tags_rendered[$content] = $text;
                }
            }
            return $text;
        }
        if (empty($params['_tag']) || empty($content) || empty($params['_endtag'])) {
            return $content;
        }
        return htmlentities($params['_tag'], ENT_QUOTES) . $content
                . htmlentities($params['_endtag'], ENT_QUOTES);
    }

    public function RenderMCLinkTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $content = intval($content);
            if ($result = Gdn::sql()->select("PostID,Type,ParentID")->from("MMCodes")->where("CodeID", $content)->get()->firstRow()) {
                $link = false;
                $postID = $result->PostID;
                $ParentID = $result->ParentID;
                if ($result->Type === CurrentPost::DISCUSSION_TYPE) {
                    $DiscussionModel = new DiscussionModel();
                    $DiscussionModel->DiscussionID = $result->PostID;
                    $link = discussionLink($DiscussionModel);
                } else if ($result->Type === CurrentPost::COMMENT_TYPE) {
                    $CommentModel = new CommentModel();
                    $CommentModel->CommentID = $result->PostID;
                    $link = commentUrl($CommentModel);
                } else if ($result->Type === CurrentPost::MESSAGE_TYPE) {
                    $link = "/messages/$ParentID#Message_$postID";
                }
                if ($link) {
                    return $this->RenderCodeTag($bbcode, $action, $name, $default, $params, $content) . "<br/>" . wrap("Link to code", "a", ["href" => $link]);
                }
            }
        }
        return false;
    }

    public function RenderGuessTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $result = Gdn::sql()->select("CodeGuessed,PostID,NumRed,NumWhite")->from("MMGuesses")->where("GuessID", $content)->get()->firstRow();
            if ($result) {
                $guesstext = "<div class='MMGuess'>";
                $pins = explode(" ", $result->CodeGuessed);
                for ($i = 0; $i < count($pins); $i++) {
                    $guesstext.="<div class='MMPin' style='background-color: " . Code::getColorNameFromNumber($pins[$i]) . ";'></div>";
                }
                if (count($pins) == $result->NumWhite) {
                    $guesstext.="<p>Congratulations! You've guessed the code!</p>";
                } else {
                    $guesstext.="<p>, " . $result->NumWhite . " pin(s) correct, " . $result->NumRed . " pin(s) wrong positioned.</p>";
                }return $guesstext . "</div>";
            }
        }
        if (empty($params['_tag']) || empty($content) || empty($params['_endtag'])) {
            return $content;
        }
        return htmlentities($params['_tag'], ENT_QUOTES) . $content
                . htmlentities($params['_endtag'], ENT_QUOTES);
    }

    public function PluginCommandParserPlugin_BeforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $Sender->addCSS($this->getCSSToAdd());
        $BBCode->addRule('mmcode', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'RenderCodeTag'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
        $BBCode->addRule('mclink', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'RenderMCLinkTag'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
        $BBCode->addRule('mmguess', Array(
            'mode' => BBCODE_MODE_CALLBACK,
            'method' => Array($this, 'RenderGuessTag'),
            'class' => 'block',
            'allow_in' => Array('listitem', 'block', 'columns'),
        ));
    }

    function setup() {
        parent::setup();
        //This function is called when the plugin is enabled, and creates a few tables to store codes
        Gdn::structure()->table("MMCodes")
                ->primaryKey('CodeID')
                ->column('PostID', 'int', FALSE)
                ->column('Type', 'int', 1)
                ->column('ParentID', 'int', 0)
                ->column('ParentType', 'int(3)', 0)
                ->column('Code', 'text', false)
                ->column('Random', 'int(1)', false)
                ->column('Guessed', 'int(1)', false)
                ->set();
        Gdn::structure()->table("MMGuesses")
                ->primaryKey('GuessID')
                ->column('CodeID', 'int', FALSE)
                ->column('CodeGuessed', 'text', false)
                ->column('NumRed', 'int', 0)
                ->column('NumWhite', 'int', 0)
                ->set();
    }

    // Adds CSS to the relevant pages
    public function getCSSToAdd(){ return ".MMGuess
            {
                background: #f3f3f3;
                background: rgba(0, 0, 0, 0.05);
                border-left: 4px solid rgba(0, 0, 0, 0.1);
            }
            .MMGuess p
            {
                display: inline-block;
                font-style: italic;
            }
            .MMPin
            {
                display: inline-block;
                border: solid black;
                margin: 5px;
                margin-bottom:0px;
                width:10px;
                height:10px;
            }
            .MMCode
            {
                white-space:nowrap;
            }
            .MMRandom div:first-child
            {
                background-color: pink;
            }
            .MMHiddenCode
            {
                border: solid black;
                width:50%;
                text-align: center;
                font-weight:bold;
            }";
    }

}
