<?php

class Code {

    public $code;

    function __construct($code) {
        if (is_array($code)) {
            $this->code = $code;
        } else {
            $this->code = $this->text_to_code($code);
        }
    }
    //Lookup table to convert a color ID to a color name
    public static function getColorNameFromNumber($number)
            {
        $lookup = ["red","yellow","orange","green", "blue" , "black", "pink","white","gray"];
        return $lookup[$number];
            }

    public static function saveMasterCode($body,$currentPost,$random=false) {
        $code = new static($body);
        $code_to_save = $code->code_to_text($code->code);
        if ($code_to_save) {
            return Gdn::sql()->insert("MMCodes", ['ParentID'=>$currentPost->ParentID,'ParentType'=>$currentPost->ParentType,'PostID' => $currentPost->OwnID,'Type'=>$currentPost->Type, "Code" => $code_to_save,"Random"=>$random]);
        }
        return false;
    }
    //Lookup table to convert a color name to a color ID (can both be English or Dutch)
    public static function getIndexFromColorName($name)
            {
        $lookup=t("mastermind.".$name,"");
        if($lookup&&is_numeric($lookup)&&$lookup<7)
        {
            return $lookup;
        }
        $default_lookup = array("x" => 8, "red" => 0, "yellow" => 1, "orange" => 2, "green" => 3, "blue" => 4, "black" => 5, "pink" => 6, "white" => 7);
        if(isset($default_lookup[$name]))
            {
            return $default_lookup[$name];
            }
        return false;
            }

    public function text_to_code($text = false) {
        if (!$text) {
            $text = $this->code;
        }
        $code = [];
        $parts = explode(" ", $text);
        foreach ($parts as $single_part) {
            if (is_numeric($single_part)) {
                $code[] = intval($single_part);
            } else if (Code::getIndexFromColorName($single_part)!==false) {
                $code[] = Code::getIndexFromColorName($single_part);
            }
            //If the part is not a color index and also not a color name, something went wrong.
            else {
                return false;
            }
        }
        return $code;
    }

    public function code_to_text($code = -1) {
        if ($code === -1) {
            $code = $this->code;
        }
        if ($code === false) {
            return false;
        }
        $text = "";
        foreach ($code as $pin) {
            $text.=' ' . $pin;
        }
        return substr($text, 1);
    }

}
