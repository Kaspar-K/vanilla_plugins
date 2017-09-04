<?php

class CommandIndex {

    public function __construct() {
        
    }

    public function addCommands($commands, $class) {
        $className = $class->getPluginName();
        if (empty($this->$className)) {
            $this->$className = new stdClass();
        }
        foreach ($commands as $command => $explanation) {
            $this->$className->$command = $explanation;
        }
    }

}

class CurrentPost {

    public $ParentID;
    public $OwnID;
    public $Format;
    public $Body;
    public $Type;

    const COMMENT_TYPE = 1;
    const DISCUSSION_TYPE = 2;
    const MESSAGE_TYPE = 3;
    const CONVERSATION_TYPE = 4;

    public function __construct($OwnID, $ParentID, $Format, $Body, $Type) {
        $this->ParentID = $ParentID;
        $this->OwnID = $OwnID;
        $this->Format = $Format;
        $this->Body = $Body;
        switch ($Type) {
            case "Comment":
                $this->Type = self::COMMENT_TYPE;
                $this->ParentType = self::DISCUSSION_TYPE;
                break;
            case "Discussion":
                $this->Type = self::DISCUSSION_TYPE;
                $this->ParentType = self::DISCUSSION_TYPE;
                break;
            case "Message":
                $this->Type = self::MESSAGE_TYPE;
                $this->ParentType = self::CONVERSATION_TYPE;
                break;
            default:
                $this->Type = 0;
                $this->ParentType = 0;
                break;
        }
    }

    public function currentUserStartedThread() {
        if (isset($this->ownsPost)) {
            return $this->ownsPost;
        }
        $this->ownsPost = false;
        if ($this->currentPost->ParentType === CurrentPost::CONVERSATION_TYPE) {
            $conversation = (new ConversationModel())->getID($this->currentPost->ParentID);
            $this->ownsPost = $conversation && $conversation->InsertUserID == gdn::session()->UserID;
        } else if
        ($this->currentPost->ParentType === CurrentPost::DISCUSSION_TYPE) {
            $discussion = (new DiscussionModel())->getID($this->currentPost->ParentID);
            $this->ownsPost = $discussion && $discussion->InsertUserID == gdn::session()->UserID;
        }
        return $this->ownsPost;
    }

}