<?php

if (!defined('APPLICATION'))
    exit();

// Define the plugin:
$PluginInfo['CountdownTimer'] = array(
    'Name' => 'CountdownTimer',
    'Description' => 'Add a countdown to a specific time and date to a comment. Based on a plugin written by Matt Sephton.',
    'Version' => '1.3.0',
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'License' => 'GPL v2',
    'SettingsUrl' => '/settings/countdown',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'RequiredApplications' => array('Vanilla' => '>=2.2')
);

class CountdownTimer extends Gdn_Plugin {

    // settings
    public function SettingsController_Countdown_Create($Sender, $Args = array()) {
        $Sender->Permission('Garden.Settings.Manage');
        $Sender->SetData('Title', T('Countdown'));

        $tzlist[] = DateTimeZone::listIdentifiers();
        $timezones = array_combine($tzlist[0], $tzlist[0]);

        $Cf = new ConfigurationModule($Sender);
        $Cf->Initialize(array(
            'Plugins.CountdownTimer.Tag' => array('Description' => 'The following text will be replaced with the countdown widget', 'Control' => 'TextBox', 'Default' => '[COUNTDOWN]'),
            'Plugins.CountdownTimer.Time' => array('Description' => 'Accepts most English textual date and time descriptions, see <a href="http://php.net/manual/en/function.strtotime.php">strtotime</a>', 'Control' => 'TextBox', 'Default' => '00:00:00 19 August 2012'),
            'Plugins.CountdownTimer.Timezone' => array('Control' => 'DropDown', 'Items' => $timezones, 'Default' => 'UTC')
        ));

        $Sender->AddSideMenu('dashboard/settings/plugins');
        $Cf->RenderAll();
    }

    // replace in comment
    public function Base_AfterCommentFormat_Handler($Sender) {
        $Object = $Sender->EventArguments['Object'];
        $Object->FormatBody = $this->DoReplacement($Object->FormatBody);
        $Sender->EventArguments['Object'] = $Object;
    }

    public function getTimeFromString($string) {

        try {
            $date = new DateTime($string, new DateTimeZone(c('Plugins.CountdownTimer.Timezone', 'UTC')));
            // get seconds
            $CountdownTime = $date->format('U');
            return $CountdownTime;
        } catch (Exception $e) {
            echo $e->getMessage();
            exit(1);
        }
        return 0;
    }

    // replacement logic
    public function DoReplacement($Text) {
        $number_replacements_allowed = c('Plugins.CountdownTimer.NumReplacementsPerPost', 10);

        $CountdownTag = C('Plugins.CountdownTimer.Tag', '[COUNTDOWN]');

        $this->replaceCustomCountdowns($Text, $CountdownTag, $number_replacements_allowed);
        $this->replaceGeneralCountdowns($Text, $CountdownTag, $number_replacements_allowed);

        return $Text;
    }

    // hook
    public function DiscussionController_Render_Before($Sender) {
        $this->_CountdownSetup($Sender);
    }

    // setup
    private function _CountdownSetup($Sender) {
        $Sender->AddJsFile('flipclock.min.js', 'plugins/CountdownTimer');
        $Sender->AddJsFile('countdown.js', 'plugins/CountdownTimer');
        $Sender->AddCssFile('flipclock.css', 'plugins/CountdownTimer');
    }

    public function replaceCustomCountdowns(&$Text, $CountdownTag, &$number_replacements_allowed) {

        $offset = strlen("$CountdownTag(");
        $begin = strpos($Text, "$CountdownTag(");
        while (($number_replacements_allowed === true || $number_replacements_allowed-- > 0) && $begin !== false) {
            $end = strpos($Text, ")", $begin + $offset);
            if ($end === false) {
                break;
            }
            $string = substr($Text, $begin + $offset, $end - $begin - $offset);
            $time = $this->getTimeFromString($string);
            $CountdownHTML = getCountdownHTML($time);
            $Text = substr_replace($Text, $CountdownHTML, $begin, $end - $begin + 1);
            $charCountDifference = strlen($CountdownHTML) - $end + $begin;
            $begin = strpos($Text, "$CountdownTag(", $end + $charCountDifference);
        }
    }

    public function getCountdownHTML($time) {

        $CountdownHTML = "<div data-countdown='$time'></div>";
        return $CountdownHTML;
    }

    public function replaceGeneralCountdowns(&$Text, $CountdownTag, &$number_replacements_allowed) {
        // time
        $CountdownTime = (C('Plugins.CountdownTimer.Time')) ? C('Plugins.CountdownTimer.Time') : '00:00:00 19 August 2012';
        $time = $this->getTimeFromString($CountdownTime);
        $CountdownHTML = getCountdownHTML($time);
        if ($number_replacements_allowed === true) {
            return str_replace($CountdownTag, $CountdownHTML, $Text);
        }
        $length_to_replace = strlen($CountdownTag);
        for ($i = 0; $i < $number_replacements_allowed; $i++) {
            $begin = strpos($Text, $CountdownTag);
            if ($begin === false) {
                break;
            } else {
                $Text = substr_replace($Text, $CountdownHTML, $begin, $length_to_replace);
            }
        }
    }

}
