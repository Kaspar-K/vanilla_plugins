<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

if (!defined('APPLICATION'))
    die();

$PluginInfo['EasyDeleteConversation'] = array(
    'Author' => "Caylus",
    'AuthorUrl' => 'https://open.vanillaforums.com/profile/Caylus',
    'Description' => 'More convenient message deletion.',
    'License' => 'GPL v2',
    'MobileFriendly' => TRUE,
    'Name' => 'EasyDeleteConversation',
    'RequiredApplications' => array('Vanilla' => '>=2'),
    'Version' => '1.1'
);

class EasyDeleteConversation extends Gdn_Plugin {

    public function __construct() {
        parent::__construct();
    }

    public function MessagesController_BeforeConversationMeta_handler($Sender) {
        ?><p class="deleteConvoButton" onclick="deleteConversation('<?= $Sender->EventArguments['Conversation']->ConversationID; ?>');">X</p><?php
    }

    public function MessagesController_beforeMessagesAll_handler($Sender) {
        $Sender->AddCssFile('style.css', 'plugins/EasyDeleteConversation');
        ?><script>function deleteConversation(messageID)
                    {
                        if (confirm("Are you sure you wish to delete this message?")) {
                            $.get(gdn.url('messages/clear/' + messageID + '/<?= gdn::session()->transientKey(); ?>'), function (data) {
                                location.reload();
                            }
                            );
                        }
                    }</script><?php
    }

}
