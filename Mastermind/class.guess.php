<?php
class Guess {

    public static function saveCodeGuess($codeid, $guesstext) {
        //Get the text of the master code from the database.
        $result = Gdn::sql()->select("Code,Guessed")->from("MMCodes")->where("CodeID", $codeid)->get()->firstRow();
        if (!$result) {
            return false;
        }
        if($result->Guessed)
            {
            return false;
            }
        $code = (new Code($result->Code))->code;
        $numpins = count($code);
        $guess = (new Code($guesstext))->code;
        if($guess===false)
        {
            return false;
        }
        //If guess doesn't have enough pins, add holes at the end.
        while(count($guess)<$numpins)
        {
            $guess[]=Code::getIndexFromColorName('x');
        }
        //If guess had more pins than the master code, return false.
        if ($numpins < 1 || count($guess)>$numpins) {
            return false;
        }
        $codeColorCount = [];
        $guessColorCount = [];
        $maxColor = 0;
        $correct = 0;
        for ($i = 0; $i < $numpins; $i++) {
            $c = $code[$i];
            $g = $guess[$i];
            $maxColor = $c > $maxColor ? $c : $maxColor;
            $maxColor = $g > $maxColor ? $g : $maxColor;
            //Count the number of pins of each color in the codes
            $codeColorCount[$c] = empty($codeColorCount[$c]) ? 1 : $codeColorCount[$c] + 1;
            $guessColorCount[$g] = empty($guessColorCount[$g]) ? 1 : $guessColorCount[$g] + 1;
            //Count the number of pins that are positioned correctly.
            if ($c == $g) {
                $correct++;
            }
        }
        //The number of pins that are wrongly positioned =
        //the sum of pins of the same color - number of pins that are correctly positioned 
        $positionWrong = -1 * $correct;
        for ($i = 0; $i <= $maxColor; $i++) {
            $c = (empty($codeColorCount[$i])) ? 0 : $codeColorCount[$i];
            $g = (empty($guessColorCount[$i]) ? 0 : $guessColorCount[$i]);
            $positionWrong+=($c < $g) ? $c : $g;
        }
        if($correct==$numpins)
            {
            Gdn::sql()->update("MMCodes")->set("Guessed", 1)->where("CodeID",$codeid)->put();
            }
        //Add the guess result to the database.
        $newrow = ['CodeID' => $codeid,
            'CodeGuessed' => ((new Code($guess))->code_to_text()), 'NumRed' => $positionWrong, 'NumWhite' => $correct];
        return Gdn::sql()->insert("MMGuesses", $newrow);
    }

}