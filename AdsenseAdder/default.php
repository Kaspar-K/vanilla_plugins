<?php if (!defined('APPLICATION')) die();

$PluginInfo['AdsenseAdder'] = array(
   'Name' => 'AdsenseAdder',
   'Description' => 'Adds Google AdSense code to the first comment of each thread. You must have an AdSense publisher ID for this plugin to work. Based on https://open.vanillaforums.com/get/adsense-plugin-1.6, fixed for Vanilla 2.3',
   'Version' => '1.7',
   'Author' => 'ithcy',
   'AuthorEmail' => 'vanilla@rel2.com',
   'AuthorUrl' => 'http://rel2.com',
   'SettingsUrl' => '/dashboard/settings/adsense',
   'SettingsPermission' => 'Garden.Settings.Manage',
    'RequiredApplications' => array('Vanilla' => '>=2.3')
);

class AdsenseAdderPlugin extends Gdn_Plugin
{
   public $CommentCounter = 0;
   public $ASConfig = array('ad_client', 'ad_slot', 'ad_format', 'ad_channel', 'color_url', 'color_text', 'color_link', 'color_bg', 'color_border');
   public function Base_GetAppSettingsMenuItems_Handler($Sender)
   {
      // add settings link to side menu in dashboard
      $Menu = $Sender->EventArguments['SideMenu'];
      $Menu->AddLink('Add-ons', 'AdSense', 'settings/adsense', 'Garden.Settings.Manage');
   }

   public function SettingsController_BeforeRenderAsset_Handler($Sender)
   {
      // if plugin is enabled but AdSense ID has not been set, show a warning on the dashboard.
      if ($Sender->EventArguments['AssetName'] == 'Content' &&
         Gdn::Config('EnabledPlugins.AdSense') !== '' &&
         Gdn::Config('Plugins.AdSense.google_ad_client') == '')
      {
         $WebRoot = Gdn::Request()->Domain() . '/' . Gdn::Request()->WebRoot();
         echo '<div class="Messages Errors TestAddonErrors"><ul><li><a style="color:#fff" href="' . $WebRoot . '/dashboard/settings/adsense">' . T('You have not set your AdSense ID. Click here to set it.') . '</a></li></ul></div>';
      }
   }

   public function DiscussionController_afterDiscussionBody_Handler($Sender)
   {

      // only want to show the bar on appropriate pages and when AdSense ID is set
      if (Gdn::Config('Plugins.AdSense.google_ad_client') == '' ||
         $Sender->ControllerName == 'settingscontroller')
      {
         return;
      }

      $User =& Gdn::Session()->User;
      $ASParam = array();
      foreach($this->ASConfig as $k)    
      {
         $ASParam[$k] = Gdn::Config('Plugins.AdSense.google_' . $k, NULL);
      }

      // try to guess width x height from google_ad_format string
      if (preg_match("/^(\d+)x(\d+).+$/", $ASParam['ad_format'], $wh))
      {
          $ASParam['ad_width'] = $wh[1];
          $ASParam['ad_height'] = $wh[2];
      }
      else
      {
          $ASParam['ad_width'] = 468;
          $ASParam['ad_height'] = 15;
          $ASParam['ad_format'] = '468x15_as';
      }
      
      $Inline = '';
      if (Gdn::Config('Plugins.AdSense.CenteredAds'))
          $Inline .= '<div style="text-align:center !important">' . "\n";
      $Inline .= '<script type="text/javascript"><!--' . "\n";
      foreach($ASParam as $k => $v)
      {
          $Inline .= 'google_' . $k . ' = "'. $v . '";' . "\n";
      }
      $Inline .= "\n" . '//-->
</script>
<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></script>' . "\n";
      if (Gdn::Config('Plugins.AdSense.CenteredAds'))
          $Inline .= "</div>\n";
      echo $Inline;
   }

   public function SettingsController_AdSense_Create($Sender)
   {
      // show AdSense settings form (just the AdSense ID right now)

      $Sender->Permission('Garden.Settings.Manage');
      $Sender->Title('AdSense');
      $Sender->AddSideMenu('settings/adsense');
      $Sender->Form = new Gdn_Form();
      $Validation = new Gdn_Validation();
      $ConfigModelFields = array();
      foreach($this->ASConfig as $k)
      {
          $ConfigModelFields[] = 'Plugins.AdSense.google_' . $k;
      }
      $ConfigModelFields[] = 'Plugins.AdSense.CenteredAds';
      $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
      $ConfigurationModel->SetField($ConfigModelFields);
      
      $Sender->AddJsFile('colorpicker.js', "plugins/AdsenseAdder");
      $Sender->AddJsFile('init_colorpicker.js', "plugins/AdsenseAdder");
      $Sender->AddCssFile('colorpicker.css', "plugins/AdsenseAdder");
      $Sender->AddCssFile('init_colorpicker.css', "plugins/AdsenseAdder");
      $Sender->Form->SetModel($ConfigurationModel);
            
      if ($Sender->Form->AuthenticatedPostBack() === FALSE)
      {
         $Sender->Form->SetData($ConfigurationModel->Data);
      }
      else
      {
         $Data = $Sender->Form->FormValues();
         $ConfigurationModel->Validation->ApplyRule('Plugins.AdSense.google_ad_client', array('Required', 'String'));
         if ($Sender->Form->Save() !== FALSE)
         {
            $Sender->StatusMessage = T('Your settings have been saved. Enter a discussion to see your AdSense ads.');
         }
      }
      $Sender->Render($this->GetView('settings.php'));
   }

   public function Setup()
   {
      SaveToConfig('Plugins.AdSense.google_ad_format', '468x15as');
      SaveToConfig('Plugins.AdSense.google_ad_slot', '');
      SaveToConfig('Plugins.AdSense.google_ad_type', 'text');
      SaveToConfig('Plugins.AdSense.google_ad_channel', '');
      SaveToConfig('Plugins.AdSense.google_color_border', 'FFFFFF');
      SaveToConfig('Plugins.AdSense.google_color_bg', 'FFFFFF');
      SaveToConfig('Plugins.AdSense.google_color_link', '0000FF');
      SaveToConfig('Plugins.AdSense.google_color_text', '000000');
      SaveToConfig('Plugins.AdSense.google_color_url', '008000');
      SaveToConfig('Plugins.AdSense.CenteredAds', TRUE);
   }
}

