<?php

if (!defined('APPLICATION'))
    die();

$PluginInfo['Remove uploaded attachments'] = array(
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'Description' => 'Allow members to remove their own attachments, even if they can\'t edit their posts anymore .'.
    'If the removed attachment was an image, it\'ll be replaced by a generic "this image was removed by the user" image.',
    'HasLocale' => true,
    'MobileFriendly' => TRUE,
    'Name' => 'RemoveUploadedImages',
    'RegisterPermissions' => ['Plugins.Attachments.Upload.Manage' => 'Garden.Moderation.Manage'],
    'RequiredApplications' => array('Vanilla' => '>=2'),
    'RequiredPlugins' => array('editor' => '1.0'),
    'Version' => '1.0'
);

class RemoveUploadedImagesPlugin extends Gdn_Plugin {

    public function DiscussionController_AfterCommentFormat_handler($Sender) {
        $Type = $Sender->EventArguments['Type'];
        if ($Type === 'Discussion') {
            $Body = &$Sender->EventArguments['Discussion']->FormatBody;
        } else {
            $Body = &$Sender->EventArguments['Object']->FormatBody;
        }
        if ($this->checkAllowed($Sender->EventArguments['Object']->InsertUserID)) {
            $Body.="<div class='allowDeletionInPost'></div>";
        }
    }

    public function getImageDataFromImagePath($path) {
        $url = "uploads/";
        $begin = strpos($path, $url);
        $end = ($end = strpos($path, '"', $begin)) ? $end : strlen($path);
        if ($begin === false) {
            return false;
        }
        $media = new Gdn_Model('Media');
        $path_to_image = substr($path, $begin + strlen($url), $end - $begin - strlen($url));
        $image = $media->getWhere(["Path" => $path_to_image])->nextRow();
        return $image;
    }

    public function DiscussionController_Render_Before($Sender) {
        $Sender->AddJsFile('remove_functions.js', 'plugins/RemoveUploadedImages');
        $Sender->AddCssFile('remove_css.css', 'plugins/RemoveUploadedImages');
        echo "<script>delete_button_html='". str_replace("'","\\'",t('Delete', '&times'))."';</script>";
    }

    public function plugincontroller_removeupload_create($Sender, $Args) {
        $image = $this->getImageDataFromImagePath($_POST['source']);
        if ($image && $this->checkAllowed($image->InsertUserID)) {
            $ForeignTable = $image->ForeignTable;
            $ForeignID = $image->ForeignID;
            $Path = $image->Path;
            if ($this->deleteEditorUploads($image)) {
                if (strtolower($ForeignTable) == "comment") {
                    $this->deleteFromComment($ForeignID, $Path);
                }
                if (strtolower($ForeignTable) == "discussion") {
                    $this->deleteFromDiscussion($ForeignID, $Path);
                }
                echo json_encode(["status" => "success"]);
                return;
            }
        }
        echo json_encode(["status" => "failed"]);
    }

    protected function deleteEditorUploads($Media) {
        // Save data to database using model with media table
        $Model = new Gdn_Model('Media');
        $MediaID = $Media->MediaID;
        try {
            if ($Model->delete($MediaID)) {
                // unlink the images.
                $path = PATH_UPLOADS . '/' . $Media->Path;
                $thumbPath = PATH_UPLOADS . '/' . $Media->ThumbPath;

                if (file_exists($path)) {
                    unlink($path);
                }

                if (file_exists($thumbPath)) {
                    unlink($thumbPath);
                }
            }
        } catch (Exception $e) {
            die($e->getMessage());
            return false;
        }
        return true;
    }

    public function deleteFromComment($CommentID, $Path) {
        $commentModel = new CommentModel();
        $Body = str_replace('uploads/' . $Path, $this->getPluginFolder(false) . "/images/removed.png", $commentModel->getID($CommentID)->Body);
        $FormPostValues = ['Body' => $Body, 'CommentID' => $CommentID];
        $commentModel->save($FormPostValues);
    }

    public function deleteFromDiscussion($DiscussionID, $Path) {
        $discussionModel = new DiscussionModel();
        $Body = str_replace('uploads/' . $Path, $this->getPluginFolder(false) . "/images/removed.png", $discussionModel->getID($DiscussionID)->Body);
        $FormPostValues = ['Body' => $Body, 'DiscussionID' => $DiscussionID];
        $discussionModel->save($FormPostValues);
    }
    public function checkAllowed($InsertUserID) {
        if ($InsertUserID == gdn::session()->UserID) {
            return true;
        }
        if (!isset($this->cachedRemoveAllAttachmentsPermission)) {
            $this->cachedRemoveAllAttachmentsPermission = gdn::session()->checkPermission("Plugins.RemoveUploadedImages.RemoveAllAttachments");
        }
        return $this->cachedRemoveAllAttachmentsPermission;
    }

}
