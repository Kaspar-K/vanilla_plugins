<?php
if (!defined('APPLICATION')) {
    exit();
}
require "DieParser_2.1.php";

$PluginInfo['DiceRoller'] = array(
    'Author' => "Tom Sassen",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'Description' => 'Implements a dice roll.Get instructions on proper usage by posting a post containing this command: [pluginexplanation]DiceRoller[/pluginexplanation]',
    "HasLocale"=>true,
    'MobileFriendly' => TRUE,
    'Name' => 'DiceRoller',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('PluginCommandParser' => '1.0'),
    'Version' => '3.0'
);

class DiceRollerPlugin extends Gdn_Plugin {
    
    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex=$Sender->EventArguments['CommandIndex'];
        $commands=[
            "[roll]1d6[/roll]"=>t("Roll a 6-sided die."),
            "[roll]5d6b3[/roll]"=>t("Roll 5 six sided dice, sum the best 3."),
            "[roll]5d10s6[/roll]"=>t("Roll 5d10, every dice with 6 or more is a success."),
            "[roll]5d10s6f1[/roll]"=>t("5d10, every dice with 6 or more is a success, every dice with 1 or less substracts a success."),
            "[roll](5*7d6)^2/5+3[/roll]"=>t("Do basic arithmatic with the results of the dice.")];
            $commandIndex->addCommands($commands,$this);
    }

    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $BBCode->AddRule('roll', Array(
            'method' => Array($this, 'saveDiceTag'),
        ));
        //There shouldn't be any active metaroll tags in here.
        $BBCode->AddRule('metaroll',[]);
    }

    public function PluginCommandParserPlugin_beforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $Sender->addCSS($this->getCSSToAdd());
        //Between [r] and [/r] is the RollID
        $BBCode->addRule('r', Array(
            'method' => Array($this, 'RenderDiceTag')
        ));
        //Between metaroll tags are the total number of rolls in the post,
        //and metaroll tags always come after all the posts have been parsed.
        $BBCode->addRule('metaroll', Array(
            'method' => Array($this, 'RenderMetaTag'),
        ));
    }

    public function RenderMetaTag($parser, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            //If there are still rolls that can be found in the database, but not in the posts itself,
            //an user deleted them, so give an error.
            if (!empty($this->newrolls)) {
                return "<br/><p class='DRError'>" . count($this->newrolls) . " out of " . intval($content) . "  rolls missing</p>";
            }
        }
        return "";
    }

    //Render [r]RollID[/r] before displaying it in the post
    public function renderDiceTag($parser, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $content = intval($content);
            $roll = Gdn::sql()->select("Roll,PostID")->from("DRRolls")->where(["RollID" => $content])->get()->firstRow();
            if ($roll) {
                $class = "DRDiceRoll";
                //$this->newrolls are all the rolls with a matching postID to this post.
                //If it's the first time we encounter a certain RollID, highlight it
                if (isset($this->newrolls[$content])) {
                    $class = "$class DRNewroll";
                    unset($this->newrolls[$content]);
                }
                return "<div class='$class'>$roll->Roll</div>";
            }
        }
        return false;
    }

    //Try to parse [roll]RollExp[/roll] before saving the post to the database
    public function saveDiceTag($bbcode, $action, $name, $default, $params, $content) {
        if ($action === BBCODE_CHECK) {
            return true;
        }
        $currentPost = $params["_currentPost"];
        $roll = $this->parseRoll(htmlEntityDecode($content), $currentPost);
        return $roll;
    }

    protected function parseRoll($roll_to_parse, $currentPost) {
        //If for some reason the roll can't be rendered, return false.
        $parser = new Diceparser(str_replace(' ', '', $roll_to_parse));
        $total=$parser->getResult();
        if ($total === false)
            {
            gdn::controller()->informMessage("Diceexpression is not correct!");
            return false;
        }else if(is_infinite($total)) {
            gdn::controller()->informMessage("Diceexpression leads to overflow!");
            return false;
        } else {
            //Else create HTML, and save it to the database.
            $roll_to_save = "<p>[$roll_to_parse]: " . $total . "</p><div class='Spoiler'>";
            for ($roll = $parser->getDiceRolled()->first; $roll; $roll = $roll->next) {
                $roll_to_save.="<p class='DRDie'>d$roll->kind:$roll->value</p>";
            }
            $roll_to_save.="</div>";
            $roll_id = Gdn::sql()->insert("DRRolls", ["Roll" => $roll_to_save, "PostID" => $currentPost->OwnID, 'Type' => $currentPost->Type]);
            return "[r]" . $roll_id . "[/r]";
        }
    }

    public function Setup() {

        //This function is called when the plugin is enabled and creates a Table in the database
        Gdn::structure()->table("DRRolls")
                ->primaryKey('RollID')
                ->column('Roll', 'Text', FALSE)
                ->column('PostID', 'int', 0)
                ->column('Type', 'int(3)', 1)
                ->set();
    }

    public function renderPost($currentPost) {
        $postID = $currentPost->OwnID;
        $type = $currentPost->Type;
        //Lookup all the rolls that should be present in this post.
        $this->newrolls = [];
        $result = GDn::sql()->select("RollID")->from("DRRolls")->where(["PostID" => $postID, 'Type' => $type])->get();
        while (($row = $result->nextRow())) {
            $this->newrolls[$row->RollID] = true;
        }
        //If there are any, add a metaroll tag with the number of rolls at the end.
        if ($this->newrolls) {
            $currentPost->Body = $currentPost->Body . "[metaroll]" . count($this->newrolls) . "[/metaroll]";
        }
    }

    public function PluginCommandParserPlugin_beforeDisplayParse_Handler($Sender, $Args) {
        $this->renderPost($Sender->EventArguments['CurrentPost']);
    }

    // Adds CSS to the relevant pages
    public function getCSSToAdd() {
        return "/*Every element with class Dice that is preceded by another one.*/
            .DRDice~.DRDice {
                margin-left: 0.5em;
            }

            .DRDiceRoll {
                display: block;

                padding: 1ex 16px;
                margin: 1em 0;
                background: #f3f3f3;
                background: rgba(0, 0, 0, 0.05);
                border-left: 4px solid rgba(0, 0, 0, 0.1);
            }
            .DRNewroll
            {
                background-color: palegreen;
            }
            .DRError
            {
                color:red;
            }";
    }

}
