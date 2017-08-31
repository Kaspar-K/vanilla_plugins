<?php
if (!defined('APPLICATION'))
    exit();
define('MAGPIE_DIR', 'plugins/MagpieFeeds/magpierss/');
require_once(MAGPIE_DIR . 'rss_fetch.inc');

// Define the plugin:
$PluginInfo['MagpieFeeds'] = array(
    'Name' => 'Magpie Feeds',
    'Description' => "Automatically creates new discussions based on content imported from supplied RSS feeds.",
    'Version' => '1.1',
    'HasLocale' => TRUE,
    'Author' => "VrijVlinder",
    'MobileFriendly' => TRUE,
);

class MagpieFeedsPlugin extends Gdn_Plugin {

    protected $FeedList = NULL;
    protected $RawFeedList = NULL;

//    protected $Poster = NULL;

    /**
     * Set up appmenu link
     */
    public function Base_GetAppSettingsMenuItems_Handler($Sender) {
        $Menu = $Sender->EventArguments['SideMenu'];
        $Menu->AddItem('Forum', T('Forum'));
        $Menu->AddLink('Forum', T('Magpie Feeds'), 'plugin/magpiefeeds', 'Garden.Settings.Manage');
    }

    public function DiscussionsController_AfterInitialize_Handler($Sender) {
        if ($this->CheckFeeds(false)) {
            $this->CheckFeeds(true);
        }
    }

    public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
        $this->addDiscussionCSS();
    }

    public function PluginController_MagpieFeeds_Create($Sender) {
        if (gdn::session()->checkPermission("Garden.Settings.Manage")) {
        $this->Dispatch($Sender, $Sender->RequestArgs);
        
        }
    }

    /**
     * Handle toggling of the MagpieFeeds.Enabled setting
     *
     * This method handles the internally re-dispatched call generated when a user clicks
     * the 'Enable' or 'Disable' button within the dashboard settings page for MagpieFeeds.
     */
    public function Controller_Toggle($Sender) {
        $Sender->Permission('Garden.Settings.Manage');

        // Handle Enabled/Disabled toggling
        $this->AutoToggle($Sender);
    }

    public function Controller_CheckFeeds($Sender) {
        $this->CheckFeeds();
        // Redirect('/plugin/magpiefeeds/');
        $this->Controller_Index($Sender);
    }

    public function CheckFeeds($AutoImport = TRUE) {
        $NeedToPoll = 0;
        foreach ($this->GetFeeds() as $FeedURL => $FeedData) {
            // Check feed here
            $LastImport = GetValue('LastImport', $FeedData) == 'never' ? 0 : strtotime(GetValue('LastImport', $FeedData));
            $Historical = (bool) GetValue('Historical', $FeedData, FALSE);
            $Delay = GetValue('Refresh', $FeedData);
            $DelayStr = '+' . str_replace(array(
                        'h',
                        'd',
                        'w'
                            ), array(
                        'hours',
                        'days',
                        'weeks'
                            ), $Delay);
            $DelayMinTime = strtotime($DelayStr, $LastImport);
            if (
                    (($LastImport && time() > $DelayMinTime)) || // We've imported before, and this article was published since then

                    (!$LastImport && (time() > $DelayMinTime || $Historical))   // We've not imported before, and this is either a new article,
            // or its old and we're allowed to import old articles
            ) {
                if ($AutoImport) {
                    $NeedToPoll = $NeedToPoll | 1;
                    $this->PollFeed($FeedURL, $LastImport);
                } else {
                    return TRUE;
                }
            }
        }
        return (bool) $NeedToPoll;
    }

    public function Controller_Index($Sender) {
        $Sender->Title($this->GetPluginKey('Name'));
        $Sender->AddSideMenu('plugin/magpiefeeds');
        $Sender->SetData('Description', $this->GetPluginKey('Description'));
        $this->addSettingsCSS();

        // $Categories = CategoryModel::Categories();
        //$Sender->SetData('Categories', $Categories);

        $Sender->SetData('Feeds', $this->GetFeeds());
        $Sender->Render($this->GetView('magpiefeeds.php'));
    }


    public function Controller_AddFeed($Sender) {

        // $Categories = CategoryModel::Categories();
        //$Sender->SetData('Categories', $Categories);
        // Do add feed stuff here;
        if ($Sender->Form->AuthenticatedPostback()) {

            // Grab posted values and merge with defaults
            $FormPostValues = $Sender->Form->FormValues();
            $Defaults = array(
                'MagpieFeeds.FeedOption.Historical' => '1',
                'MagpieFeeds.FeedOption.Refresh' => '1d',
                'MagpieFeeds.FeedOption.UserID' => '1',
                'MagpieFeeds.FeedOption.CategoryID' => '1'
            );
            $FormPostValues = array_merge($Defaults, $FormPostValues);

            try {
                $FeedURL = GetValue('MagpieFeeds.FeedURL', $FormPostValues, NULL);
                if (empty($FeedURL))
                    throw new Exception("You must supply a non-empty Feed URL");

                if (!$FeedURL)
                    throw new Exception("You must supply a valid Feed URL");

                if ($this->HaveFeed($FeedURL))
                    throw new Exception("The Feed URL you supplied is already part of an Active Feed");

                //$CategoryID = GetValue('Category', $FormPostValues);
                //if (!array_key_exists($CategoryID, $Categories))
                // throw new Exception("You need to select a Category");

                $RSSData = $this->getRSS($FeedURL);
                $Channel = $RSSData->channel['title'];

                if (!$Channel)
                    throw new Exception("The Feed URL you supplied is not an RSS stream");
                $this->AddFeed($FeedURL, array(
                    'Historical' => $FormPostValues['MagpieFeeds.FeedOption.Historical'],
                    'Refresh' => $FormPostValues['MagpieFeeds.FeedOption.Refresh'],
                    'LastImport' => "never",
                    'UserID' => $FormPostValues['MagpieFeeds.FeedOption.UserID'],
                    'CategoryID' => $FormPostValues['MagpieFeeds.FeedOption.CategoryID']
                ));

                $Sender->StatusMessage = sprintf(T("Feed has been added"), $FeedURL);
                $Sender->Form->ClearInputs();
            } catch (Exception $e) {
                $Sender->Form->AddError(T($e->getMessage()));
            }
        }

        // Redirect('/plugin/magpiefeeds/');
        $this->Controller_Index($Sender);
    }


    public function Controller_DeleteFeed($Sender) {

        if (gdn::session()->checkPermission("Garden.Settings.Manage")) {
            if (isset($_POST['feedkeyToDelete'])) {
                $FeedURL = self::DecodeFeedKey($_POST['feedkeyToDelete']);
                if ($this->HaveFeed($FeedURL)) {
                    $this->RemoveFeed($FeedURL);
                    $Sender->StatusMessage = sprintf(T("Feed has been removed"), $FeedURL);
                }
            }
        }

        // Redirect('/plugin/magpiefeeds/');
        $this->Controller_Index($Sender);
    }

    protected function GetFeeds($Raw = FALSE, $Regen = FALSE) {
        if (is_null($this->FeedList) || $Regen) {
            $FeedArray = C('Plugins.MagpieFeeds.Feeds', array());
            $this->FeedList = array();
            $this->RawFeedList = array();

            foreach ($FeedArray as $FeedKey => $FeedItem) {
                $RealFeedURL = self::DecodeFeedKey($FeedKey);
                $this->RawFeedList[$FeedKey] = $this->FeedList[$RealFeedURL] = $FeedItem;
            }
        }

        return ($Raw) ? $this->RawFeedList : $this->FeedList;
    }

    
    
    public function getRSS($Url)
            {
        $source=file_get_contents($Url);
        $magpierss=new MagpieRSS($source);
        return $magpierss;
            }

    protected function PollFeed($FeedURL, $LastImportDate) {
        $RSSData = $this->getRSS($FeedURL);
        $FeedKey = self::EncodeFeedKey($FeedURL);
        $Channel = $RSSData->channel['title'];
        $Cat = C('Plugins.MagpieFeeds.Feeds.' . $FeedKey . '.CategoryID');
        $CategoryID = $Cat;

        $DiscussionModel = new DiscussionModel();
        $DiscussionModel->SpamCheck = FALSE;

        foreach ($RSSData->items as $Item) {

            $UserID = C('Plugins.MagpieFeeds.Feeds.' . $FeedKey . '.UserID');
            $InsertUserID = $UserID;
            $StrPubDate = $Item['pubdate'];
            if (!is_null($StrPubDate)) {
                $PubDate = strtotime($StrPubDate);

                // Story is older than last import date. Do not import.
                if ($PubDate < $LastImportDate)
                    continue;
            } else {
                $PubDate = time();
            }
            if ($PubDate > time()) {
                continue;
            }
            $StoryTitle = $Item['title'];
            if (!isset($Item['tagline']))
                $Item['tagline'] = "";
            $content_tags_tocheck = ['summary','description','content', 'atom_content'];
            $actual_content = "";
            foreach ($content_tags_tocheck as $content_tag) {
                if (isset($Item[$content_tag])) {
                    if(is_array($Item[$content_tag]))
                        {
                        $actual_content=serialize($Item[$content_tag]);
                        }
                    else{$actual_content = $Item[$content_tag];}
                    break;
                }
            }
            $StoryBody = $actual_content .'<br/><a href="'.$Item['link'].'">click here for the whole story</a>';
            $StoryPublished = date("Y-m-d H:i:s", $PubDate);

            $ParsedStoryBody = $StoryBody;

            $DiscussionData = array(
                'Name' => $StoryTitle,
                'Body' => $ParsedStoryBody,
                'CategoryID' => $CategoryID,
                'Format' => 'Html'
            );




            $DiscussionData[$DiscussionModel->DateInserted] = $StoryPublished;
            $DiscussionData[$DiscussionModel->InsertUserID] = $InsertUserID;
            $DiscussionModel->CategoryID = $CategoryID;
            $DiscussionModel->UpdateCategoryID = $CategoryID;

            /* Decide in which category to post... */


            $DiscussionData[$DiscussionModel->CategoryID] = $CategoryID;
            $DiscussionData[$DiscussionModel->DateUpdated] = $StoryPublished;
            $DiscussionData[$DiscussionModel->UpdateUserID] = $InsertUserID;
            $DiscussionData[$DiscussionModel->UpdateCategoryID] = $CategoryID;
            $InsertID = $DiscussionModel->Save($DiscussionData);
            $DiscussionModel->Validation->Results(TRUE);
        }

        $this->UpdateFeed($FeedURL, 'LastImport', date('Y-m-d H:i:s', time()));
    }

    public function ReplaceBadURLs($Matches) {
        $MatchedURL = $Matches[0];
        $FixedURL = array_pop($Trash = explode("/*", $MatchedURL));
        return 'href="' . $FixedURL . '"';
    }

    protected function AddFeed($FeedURL, $FeedOptions) {
        $FeedKey = self::EncodeFeedKey($FeedURL);
        $Feeds = $this->GetFeeds(TRUE);
        $Feeds[$FeedKey] = $FeedOptions;
        SaveToConfig('Plugins.MagpieFeeds.Feeds', $Feeds);

        // regenerate the internal feed list
        $this->GetFeeds(TRUE, TRUE);
    }

    protected function UpdateFeed($FeedURL, $FeedOptionKey, $FeedOptionValue) {
        $FeedKey = self::EncodeFeedKey($FeedURL);
        $Feeds = $this->GetFeeds(TRUE);
        $Feeds[$FeedKey][$FeedOptionKey] = $FeedOptionValue;
        SaveToConfig('Plugins.MagpieFeeds.Feeds', $Feeds);

        // regenerate the internal feed list
        $this->GetFeeds(TRUE, TRUE);
    }

    protected function RemoveFeed($FeedURL) {
        $FeedKey = self::EncodeFeedKey($FeedURL);
        $Feeds = $this->GetFeeds(TRUE);
        unset($Feeds[$FeedKey][$FeedKey]);
        RemoveFromConfig('Plugins.MagpieFeeds.Feeds.' . $FeedKey);

        // regenerate the internal feed list
        $this->GetFeeds(TRUE, TRUE);
    }

    protected function GetFeed($FeedURL, $PreEncoded = FALSE) {
        $FeedKey = (!$PreEncoded) ? self::EncodeFeedKey($FeedURL) : $FeedURL;
        $Feeds = $this->GetFeeds(TRUE);

        if (array_key_exists($FeedKey, $Feeds))
            return $Feeds[$FeedKey];

        return NULL;
    }

    protected function HaveFeed($FeedURL) {
        $Feeds = $this->GetFeeds();
        if (array_key_exists($FeedURL, $Feeds))
            return TRUE;
        return FALSE;
    }

    public static function EncodeFeedKey($Key) {
        return str_replace('=', '_', base64_encode($Key));
    }

    public static function DecodeFeedKey($Key) {
        return base64_decode(str_replace('_', '=', $Key));
    }

    public function Setup() {
        // Nothing to do here!
    }

    public function Structure() {
        // Nothing to do here!
    }

    public function addSettingsCSS() {
        ?><style>
.RemoveFeedLink
{
    cursor:pointer;
    color:blue;
    text-decoration: underline;
}
            div.AddFeed {

            }

            #Content div.AddFeed ul li div.Info{
                margin-top: 0px;
            }

            div.ActiveFeeds {
                padding: 20px;
            }

            div.ActiveFeeds div.FeedItem {
                border-bottom: 1px solid #e0e0e0;
                margin: 10px 20px 0px 20px;
                padding-bottom: 10px;
                position: relative;
            }

            div.ActiveFeeds div.FeedItem div.FeedItemURL {
                font-weight: bold;
            }

            div.ActiveFeeds div.FeedItem div.FeedItemInfo {
                color: #555555;
                font-size: 90%;
            }

            div.ActiveFeeds div.FeedItem div.FeedItemInfo span {
                margin-right: 10px;
            }

            div.ActiveFeeds div.FeedItem div.DeleteFeed {
                position: absolute;
                top: 0px;
                right: 0px;
                display: block!important;
            }

        </style><?php
    }

    public function addDiscussionCSS() {
        ?><style>
            div.Comment div.AutoFeedDiscussion img {
                float: left;
                padding: 0px 15px 15px 0px;
                border: 0px;
            }

            div.Comment div.AutoFeedDiscussion {
                overflow: hidden;
            }</style><?php
    }

}