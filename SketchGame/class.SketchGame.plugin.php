<?php
// Define the plugin:
$PluginInfo['SketchGame'] = array(
    'Name' => 'SketchGame',
    'Description' => 'A sketching game adapted for Vanilla.Get instructions on proper usage by posting a post containing this command: [pluginexplanation]SketchGame[/pluginexplanation]',
    'Version' => '1.0',
    'Author' => "Tom Sassen",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'MobileFriendly' => TRUE,
    'SettingsUrl' => '/settings/sketchgame',
    'RequiredPlugins' => array('PluginCommandParser' => '1.0')
);

class SketchGame extends Gdn_Plugin {
    
    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex=$Sender->EventArguments['CommandIndex'];
        $commands=[
            "[wsrandom]"=>
            [0=>"If the dictionary plugin is also enabled, start playing with a random hint.",
                'nl'=>"Als de Dictionary plugin ook aanwezig is, start met spelen met een willekeurige hint."
                ],"[wsstartchain]Hint[/wsstartchain]"=>
            [0=>"Start playing with 'Hint' as first hint.",
                'nl'=>"Start met spelen met 'Hint' als de eerste hint."],
            "[wsreveal]"=>
            [0=>"When you had fun, want to end the game and see the whole chain, you can use [wsreveal] to reveal it to all players.",
                'nl'=>"Zodra het leuk geweest is en je de hele keten wil zien, kun je [wsreveal] gebruiken om de keten te laten zien."]];
            $commandIndex->addCommands($commands,$this);
    }

    public function getExplanation() {
        return "A sketching game.<br/>
When a game has started, players need to claim a hint first before they can see it.<br/>
If the hint is an image, they have to give a description. If the hint is a description, they have to sketch a hint.";
    }

    public function getExplanation_nl() {
        return "Een schets spel.<br/>
Zodra een spel gestart is, moeten mensen eerst de hint 'claimen' voordat ze hem te zien krijgen.<br/>
Als de hint een plaatje is, moeten ze een woord invoeren, als de hint een woord is moeten ze een plaatje schetsen.";
        
    }

    const text_type = 0;
    const image_type = 1;

    private $currentPost = false;
    private $currentLinkID = false;

    public function PluginCommandParserPlugin_BeforeSaveParserSetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $this->currentPost = $Sender->EventArguments['CurrentPost'];
        $BBCode->AddRule("wsrandom", [
            'end_tag' => BBCODE_PROHIBIT,
            'method' => Array($this, 'startRandomSketchChain'),]);
        $BBCode->AddRule("wsstartchain", [
            'method' => Array($this, 'startSketchChain'),]);
        $BBCode->AddRule("wslink", [
            'method' => Array($this, 'detectSketchChain'),]);
        $BBCode->AddRule("wsreveal", [
            'method' => Array($this, 'revealSketchChain'),
            'end_tag' => BBCODE_PROHIBIT]);
    }

    function startRandomSketchChain($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (Gdn::pluginManager()->isEnabled("Dictionary")) {
            $randomWord = (new DictionaryPlugin())->getRandomWord();
            return $this->startSketchChain($bbcode, $action, $name, $default, $params, $randomWord);
        }
        return "To use the 'wsrandom' command the Dictionary plugin has to be enabled!";
    }

    function startSketchChain($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        $description = preg_replace("/[^A-Za-z0-9 ]/", '', $content);
        if (strlen($description) < 31) {
            $chain_id = gdn::sql()->insert("WSJMNChains", ['ParentID' => $this->currentPost->ParentID, 'ParentType' => $this->currentPost->ParentType, 'Description' => $description, 'UserID' => gdn::session()->UserID]);
            if ($chain_id) {
                $chain_link_id = gdn::sql()->insert("WSJMNChainLinks", ['ChainID' => $chain_id, 'UserID' => gdn::session()->UserID, 'Content' => $description, 'ContentType' => self::text_type]);
                if ($chain_link_id) {
                    gdn::sql()->update("WSJMNChains", ['LastLinkID' => $chain_link_id], ['ChainID' => $chain_id])->put();
                    return "New chain: [wslink]$chain_link_id" . "[/wslink]";
                }
            }
        }
        return false;
    }

    function detectSketchChain($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $this->currentLinkID = intval($content);
        }
        return false;
    }

    function getChainHTML($chainid) {
        if (isset($this->chainHTML[$chainid])) {
            return $this->chainHTML[$chainid];
        }

        $rows = gdn::sql()->select("l.UserID,l.LinkID,l.Content,l.ContentType,u.Name")->from("WSJMNChainLinks l")->join("User u", "u.UserID=l.UserID")->where(["l.ChainID" => $chainid])->orderBy("l.LinkID")->get();
        $start = $rows->nextRow();
        $post = "";
        if ($start && $start->ContentType == self::text_type) {

            $post.="<span>The chain started with: <strong>" . $start->Content . "</strong></span><div class='Spoiler'>";
            while ($row = $rows->nextRow()) {
                $post.="<br/><strong>" . $row->Name . "</strong> posted: " . $this->getLinkHTML($row->LinkID, false);
            }
        }
        return $this->chainHTML[$chainid] = "$post</div>";
    }

    function revealSketchChain($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return $this->currentLinkID ? true : false;
        }
        $chain = gdn::sql()->select('l.ChainID')->from("WSJMNChainLinks l")->join('WSJMNChains c', "c.ChainID=l.ChainID")->where(["l.LinkID" => $this->currentLinkID, 'c.UserID' => gdn::session()->UserID, 'c.Revealed' => 0])->get()->firstRow();
        if ($chain) {
            gdn::sql()->update("WSJMNChains", ['Revealed' => 1], ['ChainID' => $chain->ChainID])->put();
            return "[Chain revealed!]";
        }
        return "[wsreveal]";
    }

    public function __construct() {
        parent::__construct();
    }

    public function Controller_Index($Sender) {
        $Sender->AddSideMenu('plugin/sketchgame');
    }

    public function PluginController_SketchGame_Create($Sender) {
        $this->Dispatch($Sender, $Sender->RequestArgs);
    }

    public function Controller_Save($Sender) {
        $current_user = gdn::session()->UserID;
        $newlinkid = false;
        if (isset($_POST['key']) && isset($_POST['id']) && is_numeric($_POST['id']) && $_POST['key'] === gdn::session()->transientKey()) {
            $data = isset($_POST['data']) ? json_decode($_POST['data']) : false;
            $linkid = intval($_POST['id']);
            $chain = gdn::sql()->select("c.ParentID,c.ParentType,c.UserID,l.UserID as HintGiver,c.ChainID")->from("WSJMNChains c")->join("WSJMNChainLinks l", "c.ChainID=l.ChainID")->where(['c.LastLinkID' => $linkid, "l.LinkID" => $linkid, 'l.ClaimedBy' => $current_user, 'l.ContentType' => $data ? self::text_type : self::image_type, 'c.Revealed' => 0])->get()->firstRow();
            if ($chain && $chain->HintGiver != $current_user) {
                if ($data && $data->data->type === "image/png") {
                    $imgid = preg_replace("/[^A-Za-z0-9]/", '', $data->data->id);

                    $deletehash = preg_replace("/[^A-Za-z0-9]/", '', $data->data->deletehash);
                    $parentID = $chain->ParentID;
                    $parentType = $chain->ParentType;
                    $pic_id = gdn::sql()->insert("WSJMNImgurImgages", ['LinkID' => $linkid, 'UserID' => gdn::session()->UserID, 'ImgurID' => $imgid, 'DeleteHash' => $deletehash]);
                    gdn::sql()->update("WSJMNChainLinks", ['ClaimedBy' => -1], ['LinkID' => $linkid])->put();
                    $newlinkid = gdn::sql()->insert("WSJMNChainLinks", ['Content' => $pic_id, 'ContentType' => self::image_type, 'UserID' => $current_user, 'ChainID' => $chain->ChainID]);
                } else if (isset($_POST['sentence']) && strlen($_POST['sentence']) < 30) {
                    $content = preg_replace("/[^A-Za-z0-9 ]/", '', $_POST['sentence']);
                    $chain = gdn::sql()->select("c.ParentID,c.ParentType,c.UserID,c.ChainID")->from("WSJMNChains c")->join("WSJMNChainLinks l", "c.ChainID=l.ChainID")->where(["l.LinkID" => $linkid, 'l.ClaimedBy' => $current_user, 'l.ContentType' => self::image_type, 'l.Revealed' => 0])->get()->firstRow();
                    if ($chain && $chain->UserID != $current_user) {
                        $parentID = $chain->ParentID;
                        $parentType = $chain->ParentType;
                        gdn::sql()->update("WSJMNChainLinks", ['ClaimedBy' => -1], ['LinkID' => $linkid])->put();
                        $newlinkid = gdn::sql()->insert("WSJMNChainLinks", ['Content' => $content, 'ContentType' => self::text_type, 'UserID' => $current_user, 'ChainID' => $chain->ChainID]);
                    }
                }
            }
            if ($newlinkid) {
                gdn::sql()->update("WSJMNChains", ['LastLinkID' => $newlinkid], ['ChainID' => $chain->ChainID])->put();
                $comment = array(
                    'Body' => "[wslink]$newlinkid" . "[/wslink]",
                    'Format' => c('Garden.InputFormatter', 'Text'),
                    'InsertUserID' => $current_user,
                    'DateInserted' => Gdn_Format::toDateTime()
                );
                //include relevant files
                new PluginCommandParserPlugin();
                if ($parentType === CurrentPost::DISCUSSION_TYPE) {
                    $comment['DiscussionID'] = $parentID;
                    $commentModel = new CommentModel();
                    $commentID = $commentModel->save($comment);
                    $commentModel->save2($commentID, true);
                } else if ($parentType === CurrentPost::CONVERSATION_TYPE) {
                    $messageModel = new ConversationMessageModel();
                    $comment['ConversationID'] = $parentID;
                    $messageModel->save($comment);
                }
                echo "{\"success\":true}";
                return;
            }
        }
        echo "{\"success\":false}";
    }

    public function Controller_Claim($Sender) {
        $json = new stdClass();
        $json->resultHTML = false;
        if (isset($_POST['linkid']) && is_numeric($_POST['linkid']) && isset($_POST['key']) && $_POST['key'] === gdn::session()->transientKey()) {
            $link_id = intval($_POST['linkid']);
            $unclaim = isset($_POST['unclaim']);
            $currentUser = gdn::session()->UserID;
            if ($unclaim) {
                gdn::sql()->update("WSJMNChainLinks", ['ClaimedBy' => 0], ['LinkID' => $link_id, 'ClaimedBy' => $currentUser])->put();
            } else {
                gdn::sql()->update("WSJMNChainLinks", ['ClaimedBy' => $currentUser], ['LinkID' => $link_id, 'ClaimedBy' => 0])->put();
            }
            $json->resultHTML = $this->getLinkHTML($link_id);
        }
        echo json_encode($json);
    }

    public function SettingsController_SketchGame_Create($Sender) {
        $Sender->permission('Garden.Moderation.Manage');
        if(checkPermission('Garden.Moderation.Manage')){
        $Sender->AddSideMenu('dashboard/settings/plugins');
        $Sender->Title('ImgurUpload');
        $ConfigurationModule = new ConfigurationModule($Sender);
        $ConfigurationModule->RenderAll = True;
        $Schema = array('Plugins.SketchGame.ClientID' => array(
                'LabelCode' => 'Imgur API Client ID',
                'Control' => 'TextBox',
                'Default' => C('Plugins.SketchGame.ClientID', ''),
                'Description' => 'Register for Imgur API access at: <a href="https://api.imgur.com/oauth2/addclient">https://api.imgur.com/oauth2/addclient</a>'
            ), 'Plugins.SketchGame.Timer' => array(
                'Items' => [0 => "1:00", 1 => "2:00", 2 => "4:00", 3 => "8:00", 4 => "16:00"],
                'LabelCode' => T('How long people have to sketch:'),
                'Control' => 'dropdown',
                'Default' => c('Plugins.SketchGame.Timer', 1)
            ),
        );
        $ConfigurationModule->Schema($Schema);
        $ConfigurationModule->Initialize();
        $ConfigurationModule->RenderAll();}
    }

    public function DiscussionController_Render_Before($Sender) {
        $this->addJSAndStyles($Sender);
    }

    public function MessagesController_Render_Before($Sender) {
        $this->addJSAndStyles($Sender);
    }

    public function addJSAndStyles($Sender) {
        $Sender->AddJSFile("sketchgame.js","plugins/sketchgame");
    }
    public function getCSSToAdd(){
        return "
        div.SketchGameCanvas canvas
        {
        background-color:white;
        }
            .rect{display:none;position:absolute;z-index: 10;border-style:solid;pointer-events:none;border-width:1px;}
            canvas{border-style:solid;border-width:1px;}
            .brush
            {
                border-style:solid;pointer-events:none;border-width:1px;
            }
            .WSSketch
            {
                display:block;
                border:solid black 2px;
    }";}
    function getDynamicJSToAdd(){return (gdn::session()->UserID > 0)?"
                    function claim(button, id)
                    {
                        $.post(gdn.url('plugin/sketchgame/claim/'), {'key': '".gdn::session()->transientKey()."', 'linkid': id}, function (data) {
                            $(button.parentElement).html(data.resultHTML);
                        },'json');
                    }
                    function unclaim(button, id)
                    {
                        $.post(gdn.url('plugin/sketchgame/claim/'), {'key': '".gdn::session()->transientKey()."', 'linkid': id, 'unclaim': true}, function (data) {
                            $(button.parentElement).html(data.resultHTML);
                        },'json');
                    }
                    function share(img, id, sketchdiv) {
                        img = img.substring(img.indexOf(',') + 1);
                        var xhttp = new XMLHttpRequest(),
                        fd = new FormData();
                        fd.append('image', img);
                        xhttp.open('POST', 'https://api.imgur.com/3/image.json');
                        xhttp.setRequestHeader('Authorization', 'Client-ID ".c('Plugins.SketchGame.ClientID', 'no ID set')."'); //Get yout Client ID here: http://api.imgur.com/
                        xhttp.onreadystatechange = function () {
                            if (xhttp.status === 200 && xhttp.readyState === 4) {
                                postPicture(xhttp.responseText, id);
                            }
                        };
                        xhttp.send(fd);
                        var em = document.createElement(\"em\");
                        em.innerHTML = \"Uploading image to imgur, please wait...\";
                        sketchdiv.appendChild(em);
                    }
                    function postPicture(imgurResponse, id)
                    {
                        alert(imgurResponse);
                        if (JSON.parse(imgurResponse).success)
                        {
                            $.post(gdn.url('plugin/sketchgame/save/'), {'data': imgurResponse, 'key': '". gdn::session()->transientKey()."', 'id': id}, function (data) {
                                location.reload();
                            });
                        }
                    }
                    function submitShortSentence(textfieldid, id)
                    {
                        var sentence = document.getElementById(\"wstextfield\" + textfieldid).value;
                        $.post(gdn.url('plugin/sketchgame/save/'), {'sentence': sentence, 'key': '". gdn::session()->transientKey()."', 'id': id}, function (data) {
                            location.reload();
                        });
                    }
        ":"
                    function postPicture(imgurResponse, id){}
                    function claim(id){}
                    function submitShortSentence(textfieldid, id){}
                    function share(img, id, sketchdiv){}";
    }

    public function setup() {
        gdn::structure()->
                table('WSJMNImgurImgages')->
                primaryKey('ImageID')->
                column('UserID', 'int')->
                column('LinkID', 'int')->
                column('ImgurID', 'varchar(15)')->
                column('DeleteHash', 'varchar(20)')->set();
        gdn::structure()->
                table('WSJMNChains')->
                primaryKey('ChainID')->
                column('UserID', 'int')->
                column('ParentID', 'int')->
                column('ParentType', 'int')->
                column('Revealed', 'int(1)')->
                column('Description', 'text')->
                column('LastLinkID', 'int')->set();
        gdn::structure()->
                table('WSJMNChainLinks')->
                primaryKey('LinkID')->
                column('ChainID', 'int')->
                column('UserID', 'int')->
                column('Content', 'text')->
                column('ContentType', 'int(1)')->
                column('ClaimedBy', 'int', 0)->set();
    }

    public function formatContent($userid, $content, $type) {
        $username = gdn::userModel()->getID($userid)->Name;
        if ($type == self::text_type) {
            $html = "<p class='WSEntry'><strong>$username</strong> posted: " . htmlspecialchars($content, ENT_QUOTES) . "</p>";
            return $html;
        } else {
            $image = gdn::sql()->select('ImgurID')->from('WSJMNImgurImgages')->where('ImageID', $content)->get()->firstRow();
            if ($image) {
                $link = "http://i.imgur.com/" . preg_replace("/[^A-Za-z0-9]/", '', $image->ImgurID) . ".png";
                $html = "<p class='WSEntry'><strong>$username</strong>: <img class='WSSketch' src='$link'/></p>";
            }
            return $html;
        }
        return false;
    }

    public function getLinkHTML($link_id, $showWholeChain = true) {
        if (isset($this->linkHTML[$link_id])) {
            return $this->linkHTML[$link_id];
        }
        $chain_link = gdn::sql()->select("l.ChainID,l.ClaimedBy,l.Content,l.ContentType,c.Revealed,c.UserID as OP,l.UserID,c.LastLinkID,l.UserID")->from("WSJMNChainLinks l")->join("WSJMNChains c", 'c.ChainID=l.ChainID')->where('l.LinkID', $link_id)->get()->firstRow();

        if ($chain_link) {
            $is_original_poster = $chain_link->OP == gdn::session()->UserID;
            if ($chain_link->UserID == gdn::session()->UserID || $is_original_poster || $chain_link->Revealed) {
                if ($showWholeChain && $is_original_poster) {
                    return $this->linkHTML[$link_id] = $this->getChainHTML($chain_link->ChainID);
                }
                return $this->linkHTML[$link_id] = $this->formatContent($chain_link->UserID, $chain_link->Content, $chain_link->ContentType);
            }
            $claim = intval($chain_link->ClaimedBy);
            if ($claim < 0 || $chain_link->LastLinkID != $link_id || gdn::session()->UserID <1) {
                return $this->linkHTML[$link_id] = "<p>This entry is still hidden.</p>";
            } else if ($claim == 0) {
                return $this->linkHTML[$link_id] = "<div><input type='button' value='Claim this link in the chain!' onclick='claim(this,$link_id)'></div>";
            } else if ($claim == gdn::session()->UserID) {
                $html = $this->formatContent($chain_link->UserID, $chain_link->Content, $chain_link->ContentType);
                if ($chain_link->ContentType === self::text_type) {
                    $power = c('Plugins.SketchGame.Timer', 1);
                    if ($power > 4) {
                        saveToConfig('Plugins.SketchGame.Timer', 4);
                        $power = 4;
                    }
                    $minutes = pow(2, $power);
                    $html.=
                            '<div class="SketchGameCanvas">' .
                            '<div class="rect"></div>' .
                            '<canvas width="400" height="400" data-minutes="' . $minutes . '" data-seconds="0" data-sketchid="' . $link_id . '"></canvas>' .
                            '<p class="smallbrush">Small brush</p><p class="bigbrush">Big brush</p><p class="smalleraser">Small eraser</p>' .
                            '<p class="bigeraser">Big eraser</p>' .
                            '<h1>Time:</h1>' .
                            '<h2 class="time">' . $minutes . ':00</h2>' .
                            '<script>initializeCanvasses();</script>' .
                            '</div>';
                } else {
                    $this->sentenceCounter = isset($this->sentenceCounter) ? $this->sentenceCounter + 1 : 0;
                    $html.="<input id='wstextfield$this->sentenceCounter' type='text' value='Short sentence'><input type='button' value='Submit!' onclick='submitShortSentence($this->sentenceCounter,$link_id)'>";
                }
                if (c('Plugins.SketchGame.Unclaim', false)) {
                    return $this->linkHTML[$link_id] = "<div><input type='button' value='Release your claim on the link in this chain!' onclick='unclaim(this,$link_id)'>$html</div>";
                }
                return $this->linkHTML[$link_id] = $html;
            } else {
                return $this->linkHTML[$link_id] = "This word has been claimed by " . gdn::userModel()->getID($claim)['Name'];
            }
        }
    }

    public function renderChainLink($bbcode, $action, $name, $default, $params, $content) {
        if ($action == BBCODE_CHECK) {
            return true;
        }
        if (is_numeric($content)) {
            $html = $this->getLinkHTML(intval($content));
            return $html;
        }
        return false;
    }

    public function PluginCommandParserPlugin_BeforeDisplaySetup_Handler($Sender, $Args) {
        $BBCode = $Sender->EventArguments['Parser'];
        $Sender->addCSS($this->getCSSToAdd());
        $Sender->addJS($this->getDynamicJSToAdd());
        //Between [cng] and [/cng] is the gameID
        $BBCode->addRule('wslink', Array(
            'method' => Array($this, 'renderChainLink'),
        ));
    }

}
