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
        if($Sender->EventArguments['Object']->InsertUserID==gdn::session()->UserID){
            $src=url("uploads/editor");
            for($i=strpos($Body,$src);$i!==false;$i=strpos($Body,$src,$i+strlen($src)))
            {
                $oldLength=strlen($Body);
                $start=strripos($Body, "<img", $i-$oldLength);
                $end_of_path=strpos($Body,'"',$i);
                $end=strpos($Body,">",$end_of_path);
                if($start!==false&&$end_of_path&&$end)
                {
                    $image_part=substr($Body,$start,$end+1);
                    $image_data=$this->getImageDataFromImagePath(substr($image_part,0,$end_of_path-$start));
                    if($image_data->InsertUserID==gdn::session()->UserID){
                        $toReplace="<div class='allowToDelete'>".$image_part."</div>";
                        $Body=substr_replace($Body, $toReplace, $start, strlen($image_part));
                        $i=$i+strlen($Body)-$oldLength;
                    }
                }
            }
        }
    }
    public function getImageDataFromImagePath($path)
    {
        $url=url("uploads/");
        $begin=strpos($path,$url);
        if($begin===false)
            {
            return false;
            }
        $media=new Gdn_Model('Media');
        $path_to_image=substr($path,$begin+strlen($url));
        echo $path_to_image;
        $image=$media->getWhere(["Path"=>$path_to_image])->nextRow();        
        return $image;
     }
    // hook
    public function DiscussionController_Render_Before($Sender) {
        $Sender->AddJsFile('remove_functions.js', 'plugins/removeuploadedimages');
        $Sender->AddCssFile('remove_css.css', 'plugins/removeuploadedimages');
    }
    public function plugincontroller_removeupload_create($Sender,$Args)
    {
        $image=$this->getImageDataFromImagePath($_POST['source']);
            if($image&&$image->InsertUserID==gdn::session()->UserID)
            {
                $ForeignTable=$image->ForeignTable;
                $ForeignID=$image->ForeignID;
                if($this->deleteEditorUploads($image->MediaID)){
                    if(strtolower($ForeignTable)=="comment")
                    {
                        deleteFromComment();
                    }
                    echo "success!";
                }
            }
    }
    protected function deleteEditorUploads($MediaID, $ForeignID = '', $ForeignType = '') {
        // Save data to database using model with media table
        $Model = new Gdn_Model('Media');
        $Media = (array)$Model->getID($MediaID);

        $IsOwner = (!empty($Media['InsertUserID']) && Gdn::session()->UserID == $Media['InsertUserID']);
        // @todo Per-category edit permission would be better, but this global is far simpler to check here.
        // However, this currently matches the permission check in views/attachments.php so keep that in sync.
        $CanDelete = ($IsOwner || Gdn::session()->checkPermission('Garden.Moderation.Manage'));
        if ($Media && $CanDelete) {
            try {
                if ($Model->delete($MediaID)) {
                   // unlink the images.
                    $path = PATH_UPLOADS.'/'.$Media['Path'];
                    $thumbPath = PATH_UPLOADS.'/'.$Media['ThumbPath'];

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
        return false;
    }
    
}

