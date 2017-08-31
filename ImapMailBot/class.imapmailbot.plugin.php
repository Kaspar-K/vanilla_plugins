<?php

// Define the plugin:
$PluginInfo['ImapMailBot'] = array(
    'Name' => 'ImapMailBot',
    'Description' => 'A bot to monitor a mailbox. Fires EmailRead events.',
    'Version' => '1.0',
    'Author' => "Tom Sassen",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'AuthorUrl' => 'http://yourdomain.com',
    'MobileFriendly' => TRUE,
    'SettingsPermission' => 'Garden.Moderation.Manage',
    'SettingsUrl' => '/settings/imapmailbot',
    'RequiredApplications' => array('Vanilla' => '2.1'));

class ImapMailBot extends Gdn_Plugin {

    public function SettingsController_ImapMailBot_Create($Sender) {
        //This function creates a setting page
        $Sender->Permission('Garden.Settings.Manage');
        $Sender->SetData('Title', T('IMAP Mailbot Settings'));
        $Sender->AddSideMenu('dashboard/settings/plugins');

        //Create array of all categories on the forum
        $categories = Gdn::sql()->select("CategoryID,Name")->from("Category")->where("CategoryID>", 0)->orderBy("CategoryID", 'asc')->get();
        $CategoryID = array();
        while (($row = $categories->nextRow())) {
            $CategoryID[$row->CategoryID] = $row->CategoryID . ": " . T($row->Name);
        }
        //If the category ID is not set, default it to the first category in the list
        if (!c('Plugins.ImapMailBot.CategoryID', false)) {
            saveToConfig('Plugins.ImapMailBot.CategoryID', reset(array_keys($CategoryID)));
        }
        //Create a new ConfigurationModule which will build the settings page for us.
        $Conf = new ConfigurationModule($Sender);
        //If ForumUserID and Username are not set, default them to the system user.
        if (c('Plugins.ImapMailBot.ForumUserID', false) && c('Plugins.ImapMailBot.ForumUsername', false)) {
            
            $forumUserID = c('Plugins.ImapMailBot.ForumUserID');
            $forumUserName = c('Plugins.ImapMailBot.ForumUsername');
        }
        else{
            $forumUserID = c('Plugins.ImapMailBot.ForumUserID',Gdn::userModel()->getSystemUserID());
            $forumUserName = (new UserModel())->getID($forumUserID)->Name;
            saveToConfig('Plugins.ImapMailBot.ForumUserID',$forumUserID);
            saveToConfig('Plugins.ImapMailBot.ForumUsername',$forumUserName);
        }
        $Form = $Conf->Form();
        //If the form has been submitted, do some operation before saving the form
        if ($Form->authenticatedPostBack()) {
            //If the frequency is 0, that means to reset Plugins.ImapMailBot.LastChecked and change the frequency to the old frequency again
            if ($Form->getFormValue("Plugins.ImapMailBot.Frequency") == 0) {
                saveToConfig("Plugins.ImapMailBot.LastChecked", 0);
                $Form->setFormValue("Plugins.ImapMailBot.Frequency", c("Plugins.ImapMailBot.Frequency", 3600));
            }
            $forumUserID = $Form->getFormValue("Plugins.ImapMailBot.ForumUserID");
            if ($forumUserID) {
                //The forum user name in the form might not be complete, so replace it with the correct name
                $forumUserName = (new UserModel())->getID($forumUserID)->Name;
                $Form->setFormValue("Plugins.ImapMailBot.ForumUsername", $forumUserName);
            }
        }
        //Add input fields
        $Conf->Initialize(array(
            'Plugins.ImapMailBot.DiscussionName' => array(
                'Control' => 'textbox',
                'Default' => 'Mail posts',
                'LabelCode' => T('The title of the discussion in which mails will be posted')
            ),
            'Plugins.ImapMailBot.DiscussionBody' => array(
                'Control' => 'textbox',
                'Default' => 'Here mails will be posted',
                'LabelCode' => T('The introduction text of the discussion in which mails will be posted')
            ),
            'Plugins.ImapMailBot.Username' => array(
                'Control' => 'textbox',
                'LabelCode' => T('The username of the email to monitor')
            ),
            'Plugins.ImapMailBot.Password' => array(
                'Control' => 'textbox',
                'LabelCode' => T('The password of the email to monitor. Stored in plaintext! Do not use an important mailbox for this plugin!'),
                'Options' => array('type' => 'password')),
            'Plugins.ImapMailBot.Mailbox' => array('Default' => '{imap.gmail.com:993/ssl/novalidate-cert}INBOX',
                'Control' => 'textbox',
                'LabelCode' => T('The mailbox to monitor, see ') . wrap("http://php.net/manual/en/function.imap-open.php", 'a', array('href' => 'http://php.net/manual/en/function.imap-open.php'))),
            'Plugins.ImapMailBot.AllowedSender' => array('Control' => 'textbox', 'LabelCode' => 'Allowed senders, divided with \',\'. For example: \'john@gmail.com,mysite.nl,claire@hotmail.com\''),
            'Plugins.ImapMailBot.CategoryID' => array(
                'Items' => $CategoryID,
                'LabelCode' => T('The category to post in'),
                'Control' => 'dropdown'
            ),
            'Plugins.ImapMailBot.ForumUsername' => array(
                'Default' => $forumUserName,
                'Control' => 'textbox',
                'LabelCode' => T('The username of the forum poster'),
                'Options' => array('class' => 'AuthorSuggest', 'data-autosuggestlist' => '#Form_Plugins-dot-ImapMailBot-dot-ForumUserID')
            ), 'Plugins.ImapMailBot.ForumUserID' => array(
                'Default' => $forumUserName,
                'Items' => array($forumUserID => $forumUserName),
                'LabelCode' => T('Auto suggestions'),
                'Control' => 'dropdown'
            ),
            'Plugins.ImapMailBot.Frequency' => array(
                'Items' => array(
                    0 => T("Check now!"),
                    3600 => T("Hourly"),
                    3600 * 24 => T("Daily"),
                    3600 * 24 * 3 => T("Every 3 Days"),
                    3600 * 24 * 7 => T("Weekly"),
                    3600 * 24 * 14 => T("Every 2 Weeks")
                ),
                'LabelCode' => T('Update frequency'),
                'Control' => 'dropdown'),
        ));
        $Sender->AddJsFile($this->GetResource('js/author_autocomplete.js', FALSE, FALSE));
        $Conf->RenderAll();
    }

    public function Base_GetAppSettingsMenuItems_Handler($Sender) {
        $Menu = $Sender->EventArguments['SideMenu'];
        $Menu->AddItem('Forum', T('Forum'));
        $Menu->AddLink('Forum', T('ImapMailBot'), '/settings/imapmailbot', 'Garden.Settings.Manage');
    }

    public function __construct() {
        parent::__construct();
    }

    //Is called to obtain necessary parameters to post comments in the correct discussion.
    function getParams() {

        $Now = Gdn_Format::toDateTime();
        $DiscussionID = c("Plugins.ImapMailBot.DiscussionID", false);
        $ForumUserID = c("Plugins.ImapMailBot.ForumUserID", Gdn::userModel()->GetSystemUserID());
        $new_categoryID = c('Plugins.ImapMailBot.CategoryID', val('CategoryID', CategoryModel::DefaultCategory()));
        //If the category changed, create a new topic in the new category
        if ($new_categoryID != c('Plugins.ImapMailBot.OldCategoryID', false)) {
            $DiscussionID = false;
        }
        saveToConfig('Plugins.ImapMailBot.OldCategoryID', $new_categoryID);
        //If there isn't a topic for mails yet, create a new discussion topic
        if (!$DiscussionID) {
            $DiscussionModel = new DiscussionModel();
            $discussion = array(
                'Name' => c('Plugins.ImapMailBot.DiscussionName', 'Mail posts'),
                'Format' => c('Garden.InputFormatter'),
                'CategoryID' => $new_categoryID,
                'Body' => c('Plugins.ImapMailBot.DiscussionBody', 'Here mails will be posted'),
                'InsertUserID' => $ForumUserID,
                'DateInserted' => $Now
            );
            $DiscussionID = $DiscussionModel->save($discussion);
            saveToConfig("Plugins.ImapMailBot.DiscussionID", $DiscussionID);
        }
        return array("DiscussionID" => $DiscussionID, "SystemUserID" => $ForumUserID, "Now" => $Now, "CategoryID" => $CategoryID);
    }

    function startChecking() {
        if (!$this->needsChecking()) {
            return;
        }
        $DiscussionModel = new DiscussionModel();
        $username = c("Plugins.ImapMailBot.Username", false);
        $password = c("Plugins.ImapMailBot.Password", false);
        if (!($username && $password)) {
            return;
        }
        //Open the mailbox
        $mailbox = c('Plugins.ImapMailBot.Mailbox', '{imap.gmail.com:993/ssl/novalidate-cert}INBOX');
        $mbox = imap_open($mailbox, $username, $password);
        $messageUID = c('Plugins.ImapMailBot.LastUIDRead', false);
        //Convert messageUID to message index, (message index changes when mails get deleted).
        //If the last message was deleted, try to find one that's not deleted
        while($messageUID>0)
            {
            $startIndex=imap_msgno($mbox, $messageUID);
            if($startIndex)
                {
                break;
                }
                $messageUID--;
            }
        $startIndex = $startIndex ?  $startIndex+1 : 1;
        $time = time();
        $num_msgs = imap_num_msg($mbox);
        for ($i = $startIndex; $i <= $num_msgs; $i++) {
            //If execution time runs out, just stop reading mails
            if (time() - $time > 10) {
                break;
            }
            $header = imap_headerinfo($mbox, $i);
            $from = $header->from;
            //If there's no sender, or there are multiple senders, or it's from something else than @duivelsei.nl, we shouldn't post
            if (empty($from[0]) || isset($from[1])) {
                continue;
            }
            $allowedSenders = explode(",", str_replace(' ', '', strtolower(c('Plugins.ImapMailBot.AllowedSender', ''))));
            $sender = strtolower($from[0]->mailbox . '@' . $from[0]->host);
            //You can either allow specific senders, or blanket allow entire domains
            if (!(in_array($sender, $allowedSenders) || in_array(strtolower($from[0]->host), $allowedSenders))) {
                //If the email wasn't sent from an allowed adress, treat it as read.
                saveToConfig("Plugins.ImapMailBot.LastUIDRead", imap_uid($mbox, $i));
                continue;
            }
            //If the email was sent from an allowed adress, fire an email read event
            $this->EventArguments['mbox'] = $mbox;
            $this->EventArguments['emailIndex'] = $i;
            $this->fireEvent("EmailRead");
            //Convert message index to UID again and save it
            saveToConfig("Plugins.ImapMailBot.LastUIDRead", imap_uid($mbox, $i));
        }
        $DiscussionModel->UpdateDiscussionCount($this->getParams()['CategoryID']);
        imap_close($mbox);
    }

    function needsChecking() {
        $lastChecked = c('Plugins.ImapMailBot.LastChecked', false);
        if ($lastChecked && time() < $lastChecked + c('Plugins.ImapMailBot.Frequency', 3600)) {
            //If the last check was too short while ago, no check is necessary
                return false;
        }
        SaveToConfig('Plugins.ImapMailBot.LastChecked', time());
        return true;
    }

    function ImapMailBot_EmailRead_handler($Sender, $Args) {
        $params = $this->getParams();
        $mbox = $Sender->EventArguments['mbox'];
        $index = $Sender->EventArguments['emailIndex'];
        $header = imap_headerinfo($mbox, $index);
        //Disable spamcheck and flood check and such
        $commentModel = new CommentModel();
        $commentModel->SpamCheck = false;
        //This gets the raw HTML part of the email
        $body = imap_qprint(imap_fetchbody($mbox, $index, 1.2));
        //We can simply ignore everything outside of the <body></body> part
        $begin = stripos($body, '<body');
        $end = strripos($body, '</body>') ;
        $end = $end?$end + strlen('</body>'):strlen($body);
        $body = substr($body, $begin, $end - $begin);
        //HTML parser converts HTML to BBCode.
        $html="<h1>" . $header->subject . "</h1><br>" . $body;
        $commentBody = (c("Garden.InputFormatter", false) === "BBCode")?((new HTMLParser($html))->getBBCode()):$html;
        $maxCommentLength = c('Vanilla.Comment.MaxLength', 5000);
        $lengthEmail = strlen($commentBody);
        //If the mail is too big, we should split it over multiple posts of approx. the same length
        $numPosts = floor($lengthEmail / $maxCommentLength) + 1;
        //Every post should have approximately the same amount of chars
        $charsPerPost = ceil($lengthEmail / $numPosts * 1.1);
        if($charsPerPost>$maxCommentLength)
        {
            $charsPerPost=$maxCommentLength;
        }
        $i = 0;
        $Now = time();
        while ($i < $lengthEmail) {
            $lengthComment = $charsPerPost;
            //If all characters can fit in a post, fit them all in one post
            if ($i + $maxCommentLength > $lengthEmail) {
                $lengthComment = $lengthEmail - $i;
            }
            //Otherwise, look for a nice clean newline to break up posts, within 20% of the original point to break.
            else if ($i + $lengthComment < $lengthEmail) {
                $breaklineindex = strrpos($commentBody, "\n", $i + $lengthComment - $lengthEmail);
                $lengthComment = ($breaklineindex - $i > $lengthComment * 0.80) ? $breaklineindex - $i : $lengthComment;
            }
            $commentPart = substr($commentBody, $i, $lengthComment);
            $i+=$lengthComment;
            
            $comment = array(
                'DiscussionID' => $params['DiscussionID'],
                'Body' => $commentPart,
                'Format' => c('Garden.InputFormatter'),
                'InsertUserID' => $params['SystemUserID'],
                //Fake a delay of 10 seconds between posts
                'DateInserted' => Gdn_Format::toDateTime($Now+=10)
            );
            $commentID = $commentModel->save($comment);
            $commentModel->save2($commentID, true);
            $commentModel->Validation->Results(TRUE);
        }
    }

    //This event is called whenever a page loads.
    function Base_Render_After() {
        $this->startChecking();
    }

    /**
     *
     */
    public function setup() {
        
    }

}
