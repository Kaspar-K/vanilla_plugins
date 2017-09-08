<?php
if (!defined('APPLICATION')) {
    exit();
}

$PluginInfo['MoarNotificationOptions'] = array(
    'Name' => 'MoarNotificationOptions',
    'Description' => 'Adds Facebook notification system. To add your own extended notification system, simply create a plugin with a "moarnotificationoptionsPlugin_CollectNotificationHandlers_handler($Sender){$Sender->addInstance($this);}" function.',
    'Version' => '1.0',
    'Author' => "Tom Sassen",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'MobileFriendly' => TRUE,
    'RequiredApplications' => array('Vanilla' => '2.1')
);

class moarnotificationoptionsPlugin extends Gdn_Plugin {

    private $instances=[];

    public function addInstance($Object) {
        if(method_exists($Object, "notify")){
            $name=method_exists($Object, "getName")?$Object->getName():(method_exists($Object, "getPluginName")?$Object->getPluginName():false);
            if($name){
                $this->instances[$name] = $Object;
            }
        }
    }

    public function __construct() {
        parent::__construct();
        $this->fireEvent("CollectNotificationHandlers");
    }

    public function moarnotificationoptionsPlugin_CollectNotificationHandlers_handler($Sender) {
        if (c("Plugins.MoarNotificationOptions.FacebookNotifications", true)) {
            $Sender->addInstance($this);
        }
    }

    public function notificationscontroller_Render_Before() {
        $this->resetStatus();
    }

    public function resetStatus($name = false) {
        if ($name) {
            if (!gdn::session()->getAttribute("$name.Notifications.Uptodate", false)) {
                gdn::userModel()->saveAttribute(gdn::session()->UserID, "$name.Notifications.Uptodate", true);
            }
            return;
        }
        foreach ($this->instances as $name=>$instance) {
            $this->resetStatus($name);
        }
    }

    public function activityModel_beforeCheckPreference_handler($Sender, $Args) {
// Check if user wants to be notified of such events.
        $shouldSchedule = false;
        foreach ($this->instances as $name=>$instance) {
            if ($Sender->notificationPreference(ActivityModel::getActivityType($Args['Data']['ActivityType']), $Args['Data']['NotifyUserID'], $name)) {
                $shouldSchedule = true;
                break;
            }
        }
        if ($shouldSchedule) {

            ActivityModel::$Queue[$Args['Data']['NotifyUserID']][$Args['Data']['ActivityType']] = [
                $Args['Data'],
                $Args['Options']
            ];
        }
    }

    public function activityModel_beforeSave_handler($Sender, $Args) {
        $notifyUserID = $Args['Activity']['NotifyUserID'];
        foreach ($this->instances as $name=>$instance) {
            if (gdn::userModel()->getAttribute($notifyUserID, "$name.Notifications.Uptodate", true) &&
                    $Sender->notificationPreference(ActivityModel::getActivityType($Args['Data']['ActivityType']), $Args['Data']['NotifyUserID'], $name)) {
// Result will be an "Activity Status" (see class ActivityModel).
                $result = $instance->notify($Args['Activity']);
                $Args['Activity'][$name] = $result;
            }
        }
    }

    /**
     * Extend notifications screen to show additional notification provider.
     *
     * @param ProfileController $Sender The calling controller.
     *
     * @return void.
     */
    public function profileController_afterPreferencesDefined_handler($Sender) {
        foreach ($this->instances as $name=>$instance) {
// Add new column to notification preferences.
            foreach ($Sender->Preferences as $preferenceGroup => $preferences) {
                foreach ($preferences as $preference_name => $description) {
                    $nameParts = explode('.', $preference_name);
                    $Sender->Preferences[$preferenceGroup]["$name." . $nameParts[1]] = $description;
                }
            }
        }
    }

    /**
     * Run when plugin is enabled.
     *
     * @return void.
     */
    public function setup() {
        $this->structure();
    }

    /**
     * Change db structure.
     *
     * @return void.
     */
    public function structure() {
        // New column for sent status of push messages.
        Gdn::structure()
                ->table('Activity')
                ->column('Facebook', 'tinyint(1)', 0)
                ->set();
    }

    public function getName() {
        return "Facebook";
    }

    /*
     * Break out IFrame Facebook puts you in
     */

    function plugincontroller_redirect_create() {
        ExtendedNotificationHandler::resetStatus();
        header("X-Frame-Options: ALLOW-FROM https://facebook.com");
        ?>U wordt doorgestuurd naar het Duivelsei forum. Een ogenblik geduld alstublieft.<script>window.parent.location.href = "<?= Gdn_Url::webRoot(true); ?>";</script><?php
    }

    /**
     * Send notification with custom notification provider.
     *
     * This function must return one of the "Activity Status" codes defined
     * in ActivityModel.
     * SENT_OK    = successful delivered
     * SENT_ERROR = repeat delivery
     * SENT_FAIL  = fatal error
     *
     * @param object $activity Activity object.
     *
     * @return integer One of the SENT_... constants of ActivityModel.
     */
    private function notify($activity) {
        $notifyUserID = $activity['NotifyUserID'];
        try {
            $app_access = $this->getAppAccessToken();
            $notification_url = $this->getNotificationURL($notifyUserID);
            if ($app_access && $notification_url) {
                $parameters = ["access_token" => $app_access,
                    "href" => "",
                    "template" => strip_tags($activity['Story']) . ". There might be more notifications on the forum."];
                $this->sendToFacebook($notification_url, $parameters);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            return ActivityModel::SENT_ERROR;
        }
        gdn::userModel()->saveAttribute($notifyUserID, "Facebook.Notifications.Uptodate", false);
        return ActivityModel::SENT_OK;
    }

    private function getAppAccessToken() {
        $app_access = c('Plugins.Facebook.AppAccessToken', false);
        if ($app_access === false) {
            $appid = c('Plugins.Facebook.ApplicationID', false);
            $appsecret = c('Plugins.Facebook.Secret', false);
            if ($appid && $appsecret) {
                $app_access = file_get_contents("https://graph.facebook.com/oauth/access_token?client_id=$appid&client_secret=$appsecret&grant_type=client_credentials");
                $app_access = substr($app_access, strpos($app_access, '=') + 1);
                saveToConfig('Plugins.Facebook.AppAccessToken', $app_access);
            }
        }
        return $app_access;
    }

    private function getNotificationURL($notifyUserID) {
        $user_access_token = val('AccessToken', Gdn::userModel()->getAttribute($notifyUserID, "Facebook", false), false);
        if (!$user_access_token) {
            return false;
        }
        $response = file_get_contents("https://graph.facebook.com/me?fields=id&access_token=$user_access_token");
        $userid = json_decode($response)->id;
        if ($userid) {
            return "https://graph.facebook.com/$userid/notifications";
        } else {
            saveToConfig("error", $response);
        }
        return false;
    }

    private function sendToFacebook($notification_url, $parameters) {
        $ch = curl_init();
//set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $notification_url);
        curl_setopt($ch, CURLOPT_POST, count($parameters));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

//execute post
        curl_exec($ch);

//close connection
        curl_close($ch);
    }

}
