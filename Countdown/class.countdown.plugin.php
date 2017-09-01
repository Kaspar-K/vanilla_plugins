<?php

if (!defined('APPLICATION'))
    exit();

// Define the plugin:
$PluginInfo['Countdown'] = array(
    'Name' => 'Countdown',
    'Description' => 'Add a countdown to a specific time and date to a comment. Pick from different display types.',
    'Version' => '1.3.0',
    'Author' => "Matt Sephton",
    'AuthorEmail' => 'matt@gingerbeardman.com',
    'AuthorUrl' => 'http://www.vanillaforums.org/profile/matt',
    'License' => 'GPL v2',
    'SettingsUrl' => '/settings/countdown',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'RequiredApplications' => array('Vanilla' => '>=2')
);

class Countdown extends Gdn_Plugin {

    // settings
    public function SettingsController_Countdown_Create($Sender, $Args = array()) {
        $Sender->Permission('Garden.Settings.Manage');
        $Sender->SetData('Title', T('Countdown'));

        $tzlist[] = DateTimeZone::listIdentifiers();
        $timezones = array_combine($tzlist[0], $tzlist[0]);

        $Cf = new ConfigurationModule($Sender);
        $Cf->Initialize(array(
            'Plugins.Countdown.Tag' => array('Description' => 'The following text will be replaced with the countdown widget', 'Control' => 'TextBox', 'Default' => '[COUNTDOWN]'),
            'Plugins.Countdown.Time' => array('Description' => 'Accepts most English textual date and time descriptions, see <a href="http://php.net/manual/en/function.strtotime.php">strtotime</a>', 'Control' => 'TextBox', 'Default' => '00:00:00 19 August 2012'),
            'Plugins.Countdown.Timezone' => array('Control' => 'DropDown', 'Items' => $timezones, 'Default' => 'UTC'),
            'Plugins.Countdown.Digits' => array('Control' => 'DropDown', 'Items' => array('digits' => 'Original', 'digits_transparent' => 'Original Transparent', 'digits_inverted' => 'Original Transparent Inverted', 'small_transparent' => 'Small Transparent', 'small_inverted' => 'Small Transparent Inverted', 'digits2' => 'LED', 'digits2_blue' => 'LED Blue', 'digits2_green' => 'LED Green', 'digits2_orange' => 'LED Orange', 'digits2_purple' => 'LED Purple', 'digits2_red' => 'LED Red', 'digits2_yellow' => 'LED Yellow'))
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

        // get seconds
        $CountdownTime = strtotime($string);
        $Now = time();

        // calc diff or set to zero if in the past
        if ($CountdownTime < $Now) {
            return 0;
        } else {
            return $CountdownTime - $Now;
        }
    }

    // replacement logic
    public function DoReplacement($Text) {
        $number_replacements_allowed = c('Plugins.Countdown.NumReplacementsPerPost', 10);

        // timezone
        $CountdownTimezone = (C('Plugins.Countdown.Timezone')) ? C('Plugins.Countdown.Timezone') : 'UTC';
        date_default_timezone_set($CountdownTimezone);

        $CountdownTag = C('Plugins.Countdown.Tag', '[COUNTDOWN]');

                $offset = strlen("$CountdownTag(");
        $begin = strpos($Text, "$CountdownTag(");
        while (($number_replacements_allowed === true || $number_replacements_allowed-- > 0) && $begin !== false) {
            $end = strpos($Text, ")", $begin + $offset);
            if ($end === false) {
                break;
            }
            $string = substr($Text, $begin + $offset, $end - $begin - $offset);
            $time = $this->getTimeFromString($string);
            $CountdownHTML = "<div data-countdown='$time'></div>";
            $Text = substr_replace($Text, $CountdownHTML, $begin, $end - $begin + 1);
            $charCountDifference = strlen($CountdownHTML) - $end + $begin;
            $begin = strpos($Text, "$CountdownTag(", $end + $charCountDifference);
        }
        // time
        $CountdownTime = (C('Plugins.Countdown.Time')) ? C('Plugins.Countdown.Time') : '00:00:00 19 August 2012';
        $time = $this->getTimeFromString($CountdownTime);
        $CountdownHTML = "<div data-countdown='$time'></div>";
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

        return $Text;
    }

    // hook
    public function DiscussionController_Render_Before($Sender) {
        $this->_CountdownSetup($Sender);
    }

    // setup
    private function _CountdownSetup($Sender) {
        $Sender->AddJsFile('flipclock.min.js', 'plugins/Countdown');
        $Sender->AddJsFile('countdown.js', 'plugins/Countdown');
        $Sender->AddCssFile('flipclock.css', 'plugins/Countdown');
    }

    public function Setup() {
        return TRUE;
    }

    function formatSeconds($secs) {
        $result['s'] = $this->numberPad($secs % 60);
        $result['m'] = $this->numberPad(floor($secs / 60) % 60);
        $result['h'] = $this->numberPad(floor($secs / 60 / 60) % 24);
        $result['d'] = $this->numberPad(floor($secs / 60 / 60 / 24));

        return $result;
    }

    function numberPad($number) {
        return sprintf("%02d", $number);
    }

}
