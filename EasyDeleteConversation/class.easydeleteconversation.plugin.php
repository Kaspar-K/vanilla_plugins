<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

if (!defined('APPLICATION'))
    die();

$PluginInfo['EasyDeleteConversation'] = array(
    'Name' => 'EasyDeleteConversation',
    'Description' => 'More convenient message deletion.',
    'Version' => '1.1',
    'Author' => "Tom",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'RequiredApplications' => array('Vanilla' => '>=2'),
    'MobileFriendly' => TRUE
);

class EasyDeleteConversation extends Gdn_Plugin {

    public function __construct() {
        parent::__construct();
    }
    public function MessagesController_BeforeConversationMeta_handler($Sender)
            {
        ?><p class="deleteConvoButton" onclick="deleteConversation('<?=$Sender->EventArguments['Conversation']->ConversationID;?>');">X</p><?php
            }

    public function MessagesController_beforeMessagesAll_handler() {
        ?>
        <style>
            .deleteConvoButton
            {
                color:black;
                background-color: white;
                position: absolute; right: 15px; top: 30%; 
                border:solid black 2px; padding-left: 3px;padding-right: 3px;cursor:pointer;
            }
        </style>
        <script>function deleteConversation(messageID)
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
