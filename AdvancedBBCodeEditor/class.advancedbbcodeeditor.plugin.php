<?php

if (!defined('APPLICATION')) {
    exit();
}
$PluginInfo['AdvancedBBCodeEditor'] = array(
    'Name' => 'AdvancedBBCodeEditor',
    'Description' => 'Implements more options for the advanced editor. Get instructions on proper usage by posting a post containing this command: [pluginexplanation]AdvancedBBCodeEditor[/pluginexplanation]',
    'Version' => '1.0',
    'Author' => "Tom Sassen",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'MobileFriendly' => TRUE,
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'RequiredPlugins' => array('editor' => '1.0')
);

class AdvancedBBCodeEditor extends Gdn_Plugin {

    function __construct() {
        
    }

    public function getExplanation() {
        return "A plugin to add BBCode menu options & hotkeys.<br/>"
                . "\nThere are a few different hotkeys:";
    }
    public function getExplanation_nl() {
        return "Een plugin om extra BBCode opties snel toe te kunnen voegen.<br/>"
                . "\nEr zijn nu verschillende hotkeys:";
    }
public function PluginCommandParserPlugin_AvailableCommandsSetup_Handler($Sender, $Args) {
        $commandIndex=$Sender->EventArguments['CommandIndex'];
        $commands=[
            "ctrl-shift-1"=>
            [0=>"Add very small text.",
                'nl'=>"Voeg erg kleine tekst toe."
                ],
            "ctrl-shift-2"=>
            [0=>"Add small text.",
                'nl'=>"Voeg kleine tekst toe."
                ],
            "ctrl-shift-4"=>
            [0=>"Add large text.",
                'nl'=>"Voeg grote tekst toe."
                ],
            "ctrl-shift-5"=>
            [0=>"Add very large text.",
                'nl'=>"Voeg erg grote tekst toe."
                ],
            "ctrl-shift-b"=>
            [0=>"Add (b)lue text.",
                'nl'=>"Voeg (b)lauwe tekst toe."
                ],
            "ctrl-shift-r"=>
            [0=>"Add (r)ed text.",
                'nl'=>"Voeg (r)ode tekst toe."
                ],
            "ctrl-shift-g"=>
            [0=>"Add (g)reen text.",
                'nl'=>"Voeg (g)roene tekst toe."
                ],
            "ctrl-shift-y"=>
            [0=>"Add (y)ellow text.",
                'nl'=>"Voeg gele (yellow) tekst toe."
                ],
            "ctrl-shift-o"=>
            [0=>"Add (o)range text.",
                'nl'=>"Voeg (o)ranje tekst toe."
                ]];
            $commandIndex->addCommands($commands,$this);
    }
    public function editor_active()
            {
        return (c('Garden.InputFormatter', false) == 'BBCode')&&Gdn::pluginManager()->isEnabled("editor");
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