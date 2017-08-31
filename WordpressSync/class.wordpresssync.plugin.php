<?php

define("WPSYNC_DISCUSSION_CREATED", 1);
define("WPSYNC_DISCUSSION_EXISTS", 2);
define("WPSYNC_MISSING_PARAM", 3);
/*
  Plugin Name: WordpressSync
  Description: Plugin to create Vanilla Discussion every time a Wordpress post is created (requires official Vanilla Wordpress plugin!)
  Version:     0.0
  Author:      Caylus
  Author URI:  https://open.vanillaforums.com/profile/Caylus
  License:     GPL2
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
if (defined('ABSPATH')) {
    require_once 'wordpress_side.php';
    return;
}
if (!defined("VANILLA_CONSTANTS")) {
    die('No script kiddies please!');
}
$PluginInfo['WordpressSync'] = array(
    'Name' => 'WordpressSync',
    'Description' => 'Plugin to create Vanilla Discussion every time a Wordpress post is created.',
    'Version' => '0.0',
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'MobileFriendly' => true
);

class WordpressSync extends Gdn_Plugin {

    public function __construct() {
        parent::__construct();
    }

    public function output($status) {
        $output['status'] = $status;
        echo json_encode($output);
    }

    public function plugincontroller_wordpresssync_create($args) {
        $postid = $args[0];
        $categoryid = $args[1];
        //This is the URL to your wordpress website
        $wordpress_url = c("Plugins.WordpressSync.WordpressURL", false);
        if (!($wordpress_url && $postid)) {
            $this->output(WPSYNC_MISSING_PARAM);
            return;
        }
        $DiscussionModel = new DiscussionModel();
        if ($DiscussionModel->getForeignID($postid)) {
            $this->output(WPSYNC_DISCUSSION_EXISTS);
            return;
        }
        if ($wordpress_url[strlen($wordpress_url) - 1] !== '/') {
            $wordpress_url.='/';
        }
        $post = json_decode(file_get_contents($wordpress_url . "wp-json/wp/v2/posts/" . $postid));
        if ($post && $post->id) {
            $this->output($this->postDiscussion($categoryid, $post, $DiscussionModel));
        }
    }

    function postDiscussion($categoryid, $post, $DiscussionModel) {
        $DiscussionModel->SpamCheck = FALSE;
        $foreignkey = $post->id;
        $Attributes['ForeignUrl'] = $post->guid->rendered;
        $date = $post->date;
        $title = $post->title->rendered;
        $content = $post->content->rendered;
        $DiscussionData = array(
            'Name' => $title,
            'Body' => $content,
            'Type' => "page",
            'Format' => 'Html',
            'ForeignID' => $foreignkey,
            'Attributes' => serialize($Attributes)
        );
        if (!is_numeric($categoryid)) {
            $result = gdn::sql()->select("CategoryID")->from("Category")->where("Name", $categoryid)->get();
            $categoryid = ($row = $result->nextRow()) ? $row->CategoryID : 0;
        }
        if ($categoryid) {
            $DiscussionData['CategoryID'] = $categoryid;
        }
        $StoryPublished = date("Y-m-d H:i:s", strtotime($date));
        $DiscussionData[$DiscussionModel->DateInserted] = $StoryPublished;
        $DiscussionData[$DiscussionModel->InsertUserID] = 1;
        $DiscussionModel->CategoryID = $categoryid;
        $DiscussionModel->UpdateCategoryID = $categoryid;
        $this->discussionToCreate = $DiscussionModel;
        $DiscussionModel->save($DiscussionData);
        $this->discussionToCreate = false;
        return VANILLA_DISCUSSION_CREATED;
    }

    public function discussionModel_beforeSaveDiscussion_handler($Sender, $Args) {
        if (!($this->discussionToCreate && $this->discussionToCreate === $Sender)) {
            return;
        }

        $results = $Sender->validationResults();
        unset($results['CategoryID']);

        $Sender->Validation->reset();
        $CategoryID = val('CategoryID', $Sender->FormPostValues);
        if ($CategoryID > 0) {
            $Category = CategoryModel::categories($CategoryID);
            $user = new UserModel();
            gdn::session()->checkPermission($user);
            if ($Category && !$this->checkCategoryPermission($user->getID($Sender->FormPostValues[$Sender->InsertUserID]), $Sender->FormPostValues[$Sender->InsertUserID], 'Vanilla.Discussions.Add', val('PermissionCategoryID', $Category))) {
                $this->Validation->addValidationResult('CategoryID', 'You do not have permission to post in this category');
            }
        }

        foreach ($results as $fieldName => $errorCodes) {
            foreach ($errorCodes as $errorCode) {
                $Sender->Validation->addValidationResult($fieldName, $errorCode);
            }
        }
    }

    public function checkCategoryPermission($User, $Permission, $FullMatch = true, $JunctionTable = '', $JunctionID = '') {
        if (is_object($User)) {
            if ($User->Banned || GetValue('Deleted', $User)) {
                return false;
            } elseif ($User->Admin) {
                return true;
            }
        }

        // Allow wildcard permission checks (e.g. 'any' Category)
        if ($JunctionID == 'any') {
            $JunctionID = '';
        }

        $Permissions = Gdn_Format::unserialize($User->Permissions);
        if ($JunctionTable && !c('Garden.Permissions.Disabled.' . $JunctionTable)) {
            // Junction permission ($Permissions[PermissionName] = array(JunctionIDs))
            if (is_array($Permission)) {
                $Pass = false;
                foreach ($Permission as $PermissionName) {
                    if ($this->checkPermission($PermissionName, false, $JunctionTable, $JunctionID)) {
                        if (!$FullMatch) {
                            return true;
                        }
                        $Pass = true;
                    } else {
                        if ($FullMatch) {
                            return false;
                        }
                    }
                }
                return $Pass;
            } else {
                if ($JunctionID !== '') {
                    $Result = array_key_exists($Permission, $Permissions) && is_array($Permissions[$Permission]) && in_array($JunctionID, $Permissions[$Permission]);
                } else {
                    $Result = array_key_exists($Permission, $Permissions) && is_array($Permissions[$Permission]) && count($Permissions[$Permission]);
                }
                return $Result;
            }
        } else {
            // Non-junction permission ($Permissions = array(PermissionNames))
            if (is_array($Permission)) {
                return arrayInArray($Permission, $Permissions, $FullMatch);
            } else {
                return in_array($Permission, $Permissions) || array_key_exists($Permission, $Permissions);
            }
        }
    }

}
