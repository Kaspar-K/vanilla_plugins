<?php

if (!defined('APPLICATION'))
    die();

$PluginInfo['RemoveUploadedImages'] = array(
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'EventDescription' => 'Plugin to remove images you uploaded.',
    'HasLocale' => true,
    'MobileFriendly' => TRUE,
    'Name' => 'RemoveUploadedImages',
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
        if ($Sender->EventArguments['Object']->InsertUserID == gdn::session()->UserID) {
            $this->addDivToAllImagesOfUser($Body);
        }
    }

    public function addDivToAllImagesOfUser(&$Body) {
        $src = url("uploads/editor");
        for ($i = strpos($Body, $src); $i !== false; $i = strpos($Body, $src, $i + strlen($src))) {
            $oldLength = strlen($Body);
            $start = strripos($Body, "<img", $i - $oldLength);
            $end = strpos($Body, ">", $i);
            if ($start !== false && $end) {
                $image_part = substr($Body, $start, $end + 1);
                $image_data = $this->getImageDataFromImagePath($image_part);
                if ($image_data->InsertUserID == gdn::session()->UserID) {
                    $toReplace = "<div class='allowToDelete'>" . $image_part . "</div>";
                    $Body = substr_replace($Body, $toReplace, $start, strlen($image_part));
                    $i = $i + strlen($Body) - $oldLength;
                }
            }
        }
    }

    public function getImageDataFromImagePath($path) {
        $url = url("uploads/");
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
        $Sender->AddJsFile('remove_functions.js', 'plugins/removeuploadedimages');
        $Sender->AddCssFile('remove_css.css', 'plugins/removeuploadedimages');
    }

    public function plugincontroller_removeupload_create($Sender, $Args) {
        $image = $this->getImageDataFromImagePath($_POST['source']);
        if ($image && $image->InsertUserID == gdn::session()->UserID) {
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

}
