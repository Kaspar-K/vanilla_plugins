<?php if (!defined('APPLICATION')) exit();

// Define the plugin:
$PluginInfo['Warning'] = array(
   'Name' => 'Warning',
   'Description' => 'Allows an admin/moderator to warn a user, with the warnings Mild, Medium, Severe, and then escalate to a ban. Shows the history of warnings.',
   'Version' => '0.1.4b',
   'Author' => "Paul Thomas",
   'RequiredApplications' => array('Vanilla' => '2.1'),
   'MobileFriendly' => TRUE,
   'AuthorEmail' => 'dt01pqt_pt@yahoo.com',
   'AuthorUrl' => 'http://vanillaforums.org/profile/x00'
);

class Warning extends Gdn_Plugin {
    
    protected $WarnLevel = array(
        'None',
        'Mild',
        'Medium',
        'Severe',
    );
    
    public function MenuOptions(&$Options, $UserID, $Key = NULL, $Label = NULL){
        if(Gdn::Session()->CheckPermission('Garden.Moderation.Manage')){
            $Options[$Key] = array(
                'Label' => Sprite('WarningSprite').' '.($Label ? $Label: T('Warning.GiveWarning','Give Warning')),
                'Text' => Sprite('WarningSprite').' '.($Label ? $Label: T('Warning.GiveWarning','Give Warning')),
                'Url' => '/user/Warning/' . intval($UserID), 
                'CssClass' => 'Popup',
                'Class' => 'Popup'
            );
        }
    }

    public function ProfileController_BeforeProfileOptions_Handler($Sender, $Args){
        $this->MenuOptions($Args['ProfileOptions'],$Sender->User->UserID);
    }
    
    public function DiscussionController_DiscussionOptions_Handler($Sender, $Args){
        $this->MenuOptions($Args['DiscussionOptions'],$Args['Discussion']->InsertUserID, 'Warning');
    }
    
    public function DiscussionController_CommentOptions_Handler($Sender, $Args){
        $this->MenuOptions($Args['CommentOptions'],$Args['Comment']->InsertUserID, 'Warning');
    }
    
    public function AddWarnLevel($Sender, &$Args){
        if(Gdn::Session()->CheckPermission('Garden.Moderation.Manage') || (Gdn::Session()->Isvalid() && GetValueR('Author.UserID', $Args) == Gdn::Session()->User->UserID)){
            $WarnLevel = GetValueR('Author.Attributes.WarnLevel', $Args);
            if($WarnLevel && $WarnLevel != 'None'){
                echo "<span class=\"WarnLevel WarnLevel{$WarnLevel}\">".T('Warning.Level.'.$WarnLevel, $WarnLevel).'</span>';
            }
        }
    }
    
    public function DiscussionController_AuthorInfo_Handler($Sender, $Args){
        $this->AddWarnLevel($Sender, $Args);
    }
    
    public function ProfileController_AfterUserInfo_Handler($Sender) {

        if(Gdn::Session()->CheckPermission('Garden.Moderation.Manage') 
          || $Sender->User->UserID == Gdn::Session()->User->UserID){
            $Warnings = Gdn::UserModel()->GetMeta($Sender->User->UserID, 'Warnings.%', 'Warnings.', array());
            krsort($Warnings);
            $History = False;
            echo '<div class="Warnings">';
            echo Wrap(T('Warning.Warnings','Warnings'), 'h2', array('class' => 'H'));
                foreach($Warnings As $Date => $Warning){
                    $Warning = Gdn_Format::Unserialize($Warning);
                    $Reason = '';
                    
                    if(is_array($Warning)){
                        $Reason = $Warning['Reason'];
                        $Warning = $Warning['Type'];
                    }
                    
                    if($History && $Warning != 'None'){
                        $WarningClass = "{$Warning} Historical";
                    }else{
                        $WarningClass = $Warning;
                    }
                    if(!$History && $Warning == 'None'){
                        echo '<div class="NoWarning">'.T('Warning.NoWarnings','There are no current warnings for this user. ').'</div>';
                    }
                    echo '<div class="Warn '.$WarningClass.'">'.T('Warning.Level.'.$Warning, $Warning).'<span class="WarningDate">'.Gdn_Format::Date($Date).'</span></div>';
                    if($Reason){
                        echo '<div class="WarningReason '.$WarningClass.'">'.Gdn_Format::Text($Reason).'</div>';
                    }
                    $History = True;
                }
               
            if(count($Warnings)==0)
                echo '<div class="NoWarning">'.T('Warning.NoWarnings','There are no current warnings for this user. ').'</div>';
                
            if(count($Warnings) > 1)
                echo '<a class="WarningTogggleHistory" href="#">'.T('Warning.ToggleHistory','Toggle History').'</a>';
            echo '</div>';
        }
    }

    public function UserController_Warning_Create($Sender, $Args) {
        $Sender->Permission('Garden.Moderation.Manage');
        $UserID = (int) GetValue('0',$Args);
        $User = Gdn::UserModel()->GetID($UserID);
        if (!$User) {
            throw NotFoundException($User);
        }
        
        if ($Sender->Form->AuthenticatedPostBack()) {

            $Type = $Sender->Form->GetValue('Warning');
            $Reason = $Sender->Form->GetValue('Reason');

            if(empty($Type) || !in_array($Type, $this->WarnLevel)){
                $Sender->Form->AddError('ValidateRequired', 'Warn Level');
            }
          
            if(empty($Reason)){
                $Sender->Form->AddError('ValidateRequired', 'Reason');
            }
            
            if ($Sender->Form->ErrorCount() == 0) {
                Gdn::UserModel()->SetMeta($UserID, array('Warnings.'.time() => Gdn_Format::Serialize(array('Type' => $Type, 'Reason' => $Reason))));
                Gdn::UserModel()->SaveAttribute($UserID, 'WarnLevel', $Type);
                
                
                // get those notification sent
                $this->SaveActivity($User, $Type, $Reason);
                
                
                // Redirect after a successful save.
                if ($Sender->Request->Get('Target')) {
                   $Sender->RedirectUrl = $Sender->Request->Get('Target');
                } else {
                   $Sender->RedirectUrl = Url(UserUrl($User));
                }
            }
        }

        $Sender->SetData('User', $User);
        $Sender->SetData('WarnLevel', array_combine($this->WarnLevel, array_map(array($this, 'WarnLevelFormat'),$this->WarnLevel)));
        $Sender->AddSideMenu();
        $Sender->Title(T('Warning.Warn','Warn'));
        $Sender->View = $this->ThemeView('warning');
        $Sender->Render();

    }
    
    public function WarnLevelFormat($Level){
        return T('Warning.Level.'.$Level, $Level);
    }
    
    public function SaveActivity($User, $Level, $Reason){
        
        $UserID = $User->UserID;
        $UserName = $User->Name;
      
        $HeadlineMods = $Level && $Level != 'None' ? T('Warning.HeadlineMods') : T('Warning.HeadlineModsClear');
        $HeadlineUser = $Level && $Level != 'None' ? T('Warning.HeadlineUser') : T('Warning.HeadlineUserClear');
        
        $StoryMod   =   $Level && $Level != 'None' ? T('Warning.NotifyModsMsg') : T('Warning.NotifyModsClearMsg');
        $StoryUser  =   $Level && $Level != 'None' ? T('Warning.NotifyUserMsg') : T('Warning.NotifyUserClearMsg');
        
        
        $ActivityModel = new ActivityModel();
        $Activity = array(
            'ActivityType'      => 'Warn',
            'ActivityUserID'    => Gdn::Session()->UserID,
            'RegardingUserID'   => $UserID,
            'NotifyUserID'      => ActivityModel::NOTIFY_MODS,
            'Story'             => $StoryMod,
            'RecordType'        => 'Warn',
            // ensure notification and email get sent out.
            'Notified'          => ActivityModel::SENT_PENDING, 
            'Emailed'           => ActivityModel::SENT_PENDING,
            'Data'              => array('Level' => $Level),
            'Level'             => $Level,
            'Name'              => $UserName,
            'Reason'            => $Reason,
            'HeadlineFormat'    => $HeadlineMods,
            'Route'             => 'profile/'.$UserID.'/'.rawurlencode($UserName)
        );
        
        $UserActivity = $Activity;
        $Activity['Story']              = FormatString($UserActivity['Story'],$Activity);
        $Activity['HeadlineFormat']     = FormatString($Activity['HeadlineFormat'], $Activity);
        $UserActivity['NotifyUserID']   = $UserID;
        $UserActivity['Story']          = FormatString($StoryUser,$Activity);
        $UserActivity['HeadlineFormat'] = FormatString($HeadlineUser, $Activity);
        
        // Mod Activity
        $ActivityModel->Save($Activity, FALSE, array('Force' => TRUE));
        
        // User Activity
        $ActivityModel->Save($UserActivity, FALSE, array('Force' => TRUE));
    }

    public function GetResources($Sender){
        if(Gdn::Session()->CheckPermission('Garden.Moderation.Manage')){
            $Sender->AddJsFile($this->GetResource('js/warning.js', FALSE, FALSE));
        }
        $Sender->AddCssFile($this->GetResource('design/warning.css', FALSE, FALSE));
    }

    public function UserController_Render_Before($Sender){
        $this->GetResources($Sender);
    }
    
    public function ProfileController_Render_Before($Sender){
        $this->GetResources($Sender);
    }
    
    public function DiscussionController_Render_Before($Sender){
        $this->GetResources($Sender);
    }


    public function ThemeView($View){
        $ThemeViewLoc = CombinePaths(array(
            PATH_THEMES, Gdn::Controller()->Theme, 'views', $this->GetPluginFolder()
        ));

        if(file_exists($ThemeViewLoc.DS.$View.'.php')){
            $View=$ThemeViewLoc.DS.$View.'.php';
        }else{
            $View=$this->GetView($View.'.php');
        }

        return $View;
    }

    public function Base_BeforeDispatch_Handler($Sender){
        if(C('Plugins.Warning.Version')!=$this->PluginInfo['Version'])
            $this->Structure();
    }

    public function Setup(){
        $this->Structure();
    }

    public function Structure(){
        
        if (Gdn::SQL()->GetWhere('ActivityType', array('Name' => 'Warn'))->NumRows() == 0)
            Gdn::SQL()->Insert('ActivityType', array('AllowComments' => '0', 'Name' => 'Warn', 'FullHeadline' => '%1$s has been warned.', 'ProfileHeadline' => '%1$s has been warned.', 'Notify' => '1', 'Public' => '0'));

        SaveToConfig('Plugins.Warning.Version', $this->PluginInfo['Version']);
    }
}
