<?php

class DieRoll {

    public $next = false;
    public $kind;
    public $value;
    function __construct($kind, $value) {
        $this->kind = $kind;
        $this->value = $value;
    }

}

class DieCollection {

    public $first = false;
    public $last = false;

    public function add($kind, $value) {
        $die = new DieRoll($kind, $value);
        if ($this->last) {
            $this->last->next = $die;
            $this->last = $die;
        } else {
            $this->first = $die;
            $this->last = $die;
        }
    }

    public function join($dieCollection) {
        if ($this->last) {
            $this->last->next = $dieCollection->first;
            $this->last = $dieCollection->last;
        }
        /*
         * Somehow sometimes $last is false while there is a $first, I can't find
         * why. The program simply loops until it finds the last element.
         */ else if ($this->first) {
            $this->last = $this->first;
            while ($this->last->next) {
                $this->last = $this->last->next;
            }
            $this->last->next = $dieCollection->first;
            $this->last = $dieCollection->last;
        } else {
            $this->first = $dieCollection->first;
            $this->last = $dieCollection->last;
        }
        return $this;
    }

}

class Diceparser {

    private $total = false;
    private $dieCollection;
    private $upperbound;

    const MAX_ROLLS = 20;
    const MAX_SIDES = 1000;

    private function parseExpression($diceExpr) {
        if (is_numeric($diceExpr)) {
            $this->total = $diceExpr;
            return;
        }
        //If one of these functions returns true, it means they recognized part
        //it and tried to parse it, not necessarely that they were succesful.
        //(1+2)*3,->[3]*[3]->9, [2*3]-[1]+[2*3]-->[6]-[1]+[6]
        if ($this->parseBrackets($diceExpr) || $this->addAndSubstract($diceExpr) || $this->divideAndMultiply($diceExpr) || $this->raiseToPower($diceExpr)) {
            return;
        }
        $this->parseRoll($diceExpr);
    }

    private function parseBrackets($diceExpr) {
        $length = strlen($diceExpr);
        $end = strpos($diceExpr, ')');
        if ($end === false) {
            return false;
        }
        $begin = strrpos($diceExpr, '(', $end - $length);
        if ($begin === false) {
            return false;
        }
        $part1 = substr($diceExpr, 0, $begin);
        $part2 = substr($diceExpr, $begin + 1, $end - $begin - 1);
        $part3 = substr($diceExpr, $end + 1);
        $parser = new DiceParser($part2);
        if ($parser->getResult() === false) {
            return true;
        }
        $parser2 = new DiceParser($part1 . $parser->getResult() . $part3);
        $this->dieCollection = $parser->getDiceRolled()->join($parser2->getDiceRolled());
        $this->total = $parser2->getResult();
        return true;
    }

    private function divideAndMultiply($diceExpr) {
        $parts_expr = preg_split("/([*\/])/", $diceExpr, -1, PREG_SPLIT_DELIM_CAPTURE);
        if(!isset($parts_expr[1]))
            {
            return false;
            }
        $result = new Diceparser($parts_expr[0]);
        if($result===false)
        {
            $this->total=false;
            return true;
        }
        $this->total=$result->getResult();
        $this->dieCollection = $result->getDiceRolled();
        for($i=1;isset($parts_expr[$i]);$i+=2)
        {
            $result = new Diceparser($parts_expr[$i+1]); 
            if($result===false)
            {
                $this->total=false;
                return true;
            }           
            $value=$result->getResult();
            if ($parts_expr[$i] == "/") {
                $this->total /=  $value;
            } else {
                $this->total *= $value;
            }
            $this->dieCollection->join($result->getDiceRolled());
        }
        return true;
    }

    private function addAndSubstract($diceExpr) {
        $parts_expr = preg_split("/([+-])/", $diceExpr, -1, PREG_SPLIT_DELIM_CAPTURE);
        if(!isset($parts_expr[1]))
            {
            return false;
            }
        $result = new Diceparser($parts_expr[0]);
        if($result===false)
        {
            $this->total=false;
            return true;
        }
        $this->total=$result->getResult();
        $this->dieCollection = $result->getDiceRolled();
        for($i=1;isset($parts_expr[$i]);$i+=2)
        {
            $result = new Diceparser($parts_expr[$i+1]); 
            if($result===false)
            {
                $this->total=false;
                return true;
            }           
            $value=$result->getResult();
            if ($parts_expr[$i] == "+") {
                $this->total +=  $value;
            } else {
                $this->total -= $value;
            }
            $this->dieCollection->join($result->getDiceRolled());
        }
        return true;
    }

    private function raiseToPower($diceExpr) {
        $parts_expr=explode('^',$diceExpr);
        if(!isset($parts_expr[1]))
        {
            return false;
        }
        $result = new Diceparser($parts_expr[0]);
        if($result===false)
        {
            $this->total=false;
            return true;
        }
        $this->total=$result->getResult();
        $this->dieCollection = $result->getDiceRolled();
        for($i=1;isset($parts_expr[$i]);$i++)
        {
            $result = new Diceparser($parts_expr[$i]); 
            if($result===false)
            {
                $this->total=false;
                return true;
            }           
            $value=$result->getResult();
            $this->total =  pow($this->total,$value);
            $this->dieCollection->join($result->getDiceRolled());
        }
        return true;
    }
//Function to draw a pseudorandom number, more random than mt_rand
function crypto_rand_secure($min, $max) {
        $range = $max - $min;
        if ($range == 0) return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes, $s)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
}
    function rollDie($param) {
        $random_number = intval($this->crypto_rand_secure(0, $param['d']))+1;
        $this->dieCollection->add($param['d'], $random_number);
        if (isset($param['s'])) {
            if (is_numeric($param['s'])) {
                $mod_success = ">";
                $target_number_for_success = $param['s'] - 1;
            } else {
                $mod_success = substr($param['s'], 0, 1);
                $target_number_for_success = substr($param['s'], 1);
            }
            if (isset($param['f'])) {
                if (is_numeric($param['f'])) {
                    $mod_failure = "<";
                    $target_number_for_failure = intval($param['f']) + 1;
                } else {
                    $mod_failure = substr($param['f'], 0, 1);
                    $target_number_for_failure = substr($param['f'], 1);
                }
            } else {
                $target_number_for_failure = 0;
                $mod_failure = '<';
            }
            $successes = 0;
            if ($mod_success == '>') {
                $successes+=$random_number > $target_number_for_success ? 1 : 0;
            } else if ($mod_success == '<') {
                $successes+=$random_number < $target_number_for_success ? 1 : 0;
            } else {
                return false;
            }
            if ($mod_failure == '>') {
                $successes-=$random_number > $target_number_for_failure ? 1 : 0;
            } else if ($mod_failure == '<') {
                $successes-=$random_number < $target_number_for_failure ? 1 : 0;
            } else {
                return false;
            }
            return $successes;
        } else {
            return $random_number;
        }
    }

    function parseRoll($roll_to_parse) {
        //The roll to be parsed is made lowercase for convenience.
        $parts_to_parse = preg_split("/([ndbsf])/", strtolower($roll_to_parse), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (is_numeric($parts_to_parse[0])) {
            array_unshift($parts_to_parse, "n");
        }
        $param_count = count($parts_to_parse) / 2;
        $param = array();
        /*
         * If no n parameter is given, the first parameter will always be
         * the number of dice to be rolled, the next parameters are saved in
         * an associative array:
         * nX - the number of dice to be rolled
         * dX - the number of sides of the dice
         * bX - the number of best dice to take
         * s>X - every number higher than X counts as 1 success
         * sX - every number higher or equal to X counts as 1 success
         * s<X - every number lower than X counts as 1 success
         * f>X - every number higher than X counts as -1 success
         * f<X - every number lower than X counts as -1 success
         * fX - every number lower or equal to X counts as -1 success
         * 4d6b3s3f1 is equivalent to n4d6b3s>2f<2.
         */
        for ($i = 0; $i < $param_count; $i++) {
            $param[$parts_to_parse[$i * 2]] = $parts_to_parse[$i * 2 + 1];
        }
        if (!(isset($param['n']) && is_numeric($param['n']) && isset($param['d']) && is_numeric($param['d']))) {
            return false;
        }
        if($param['n']>DiceParser::MAX_ROLLS||$param['d']>DiceParser::MAX_SIDES)
            {
            return false;
            }
        $param['b'] = isset($param['b']) ? $param['b'] : $param['n'];
        if ($param['b'] < 1 || $param['b'] > $param['n'] || !is_numeric($param['b'])) {
            return false;
        }
        for ($i = 0; $i < $param['n']; $i++) {
            if (($rolls[] = $this->rollDie($param)) === false) {
                return false;
            }
        }
        //Copy array $rolls to find the highest values but keep $rolls unsorted
        $sortedRolls = $rolls;
        rsort($sortedRolls);
        $this->total = 0;
        for ($i = 0; $i < $param['b']; $i++) {
            $this->total+=$sortedRolls[$i];
        }
        return true;
    }

    function __construct($diceExpr) {
        $this->dieCollection = new DieCollection();
        $this->parseExpression($diceExpr);
    }

    function getResult() {
        return $this->total;
    }

    function getDiceRolled() {
        return $this->dieCollection;
    }

}

?>