<?php

if (!defined('APPLICATION')) {
    exit();
}
$PluginInfo['AdvancedBBCodeEditor'] = array(
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'Description' => 'Implements more options for the advanced editor. If you have the PluginCommandParser plugin, instructions on proper usage can be retrieved by posting a post containing this command: [pluginexplanation]AdvancedBBCodeEditor[/pluginexplanation]',
    'HasLocale' => TRUE,
    'MobileFriendly' => TRUE,
    'Name' => 'AdvancedBBCodeEditor',
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('editor' => '1.0'),
    'Version' => '1.0'
);

class AdvancedBBCodeEditor extends Gdn_Plugin {

    function __construct() {
        if (!t('AdvancedBBCodeEditor.Explanation', "")) {
            require_once __DIR__ . '/locale/en.php';
        }
    }

    public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex = $Sender->EventArguments['CommandIndex'];
        $commands = [
            "ctrl-shift-1" => t("Add very small text."),
            "ctrl-shift-2" => t("Add small text."),
            "ctrl-shift-4" => t("Add large text."),
            "ctrl-shift-5" => t("Add very large text."),
            "ctrl-shift-b" => t("Add (b)lue text."),
            "ctrl-shift-r" => t("Add (r)ed text."),
            "ctrl-shift-g" => t("Add (g)reen text."),
            "ctrl-shift-y" => t("Add (y)ellow text."),
            "ctrl-shift-o" => t("Add (o)range text.")];
        $commandIndex->addCommands($commands, $this);
    }

    public function editor_active() {
        return (c('Garden.InputFormatter', false) == 'BBCode') && Gdn::pluginManager()->isEnabled("editor");
    }

    public function EditorPlugin_toolbarConfig_handler($sender) {
        if (!$this->editor_active()) {
            return;
        }
        $MoreFormats = array(
            'underline' => array(
                'text' => t('Underline', 'Underline'),
                'command' => 'underline',
                'value' => 'underline',
                'class' => 'underline',
                'sort' => 6
            ),
            'size-separator' => array(
                'text' => '',
                'command' => '',
                'value' => '',
                'class' => 'dd-separator',
                'html_tag' => 'div',
                'sort' => 5
            ),
            'xx-large' => array(
                'text' => t('Extra Extra Large', 'XX-Large'),
                'command' => 'xx-large',
                'value' => 5,
                'class' => 'size',
                'sort' => 4
            ),
            'large' => array(
                'text' => t('Large', 'Large'),
                'command' => 'large',
                'value' => 4,
                'class' => 'size',
                'sort' => 4
            ),
            'small' => array(
                'text' => t('Small', 'Small'),
                'command' => 'small',
                'value' => 2,
                'class' => 'size',
                'sort' => 4
            ),
            'xx-small' => array(
                'text' => t('Extra Extra Small', 'XX-Small'),
                'command' => 'xx-small',
                'value' => 1,
                'class' => 'size',
                'sort' => 4
            )
        );
        $fontColorList = $fontColorList = array(
            'black',
            'white',
            'gray',
            'red',
            'green',
            'purple',
            'yellow',
            'blue',
            'orange'
            , 'olive',
            'navy',
            'lime',
            'silver',
            'maroon'
        );
        $Formats = & $sender->EventArguments['format'];
        $Formats = array_merge($Formats, $MoreFormats);
        $sender->EventArguments['format'] = $Formats;
        $sender->EventArguments['actions']['color'] = true;
        $sender->EventArguments['colors'] = $fontColorList;
    }

    public function base_render_before($Sender) {
        //echo gdn::session()->transientKey();
        if (!$this->editor_active()) {
            return;
        }
        $Sender->addJsFile('advancedbbcodeeditor.js', 'plugins/AdvancedBBCodeEditor');
    }

}

?>