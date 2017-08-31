<?php
if (!defined('APPLICATION')) {
    exit();
}

$PluginInfo['Dictionary'] = array(
    'Name' => 'Dictionary',
    'Description' => 'A plugin to enable games to look up words.',
    'Version' => '1.0',
    'Author' => "Tom Sassen",
    'AuthorEmail' => 'tom.sassen@hotmail.com',
    'MobileFriendly' => TRUE,
    'RequiredApplications' => array('Vanilla' => '2.1'),
    'SettingsUrl' => '/settings/dictionary'
);

class DictionaryPlugin extends Gdn_Plugin {

    public function getAllDictionaries() {
        $dictionaries = [];
        $result = gdn::sql()->select("Dictionary,DictionaryID")->from("DPDictionariesNames")->get();
        while ($row = $result->nextRow()) {
            $dictionaries[$row->DictionaryID] = $row->Dictionary;
        }
        return $dictionaries;
    }

    public function SettingsController_Dictionary_Create($Sender) {
        $Sender->permission('Garden.Moderation.Manage');
        if (checkPermission('Garden.Moderation.Manage')) {
            $Sender->Title($this->GetPluginKey('Name'));
            $Sender->SetData('Description', $this->GetPluginKey('Description'));
            $this->AddCss();
            $Sender->AddJsFile($this->GetResource('js/dictionary_functions.js', FALSE, FALSE));
            $Sender->addSideMenu('dashboard/settings');
            $Sender->Render($this->GetView('dictionary_settings.php'));
        }
    }

    public function Base_GetAppSettingsMenuItems_Handler($Sender) {
        $Menu = $Sender->EventArguments['SideMenu'];
        $Menu->AddItem('Forum', T('Forum'));
        $Menu->AddLink('Forum', T('Dictionary'), '/settings/dictionary', 'Garden.Settings.Manage');
    }

    function __construct() {
        parent::__construct();
    }

    public function getRandomWord($specificLibrary = false) {
        $query = gdn::sql()->select("NumWords,Dictionary")->from("DPDictionariesNames");
        if ($specificLibrary) {
            foreach ($specificLibrary as $library) {
                $query->orWhere("Dictionary", $library);
            }
        }
        $dictionaries = $query->get();
        $total = 0;
        $drawPile = [];
        while (($row = $dictionaries->nextRow())) {
            if (gdn::structure()->tableExists($this->getDictionaryPath($row->Dictionary))) {
                $total+=$row->NumWords;
                $drawPile[$total] = $row->Dictionary;
            } else {
                gdn::sql()->delete("DPDictionariesNames", ['Dictionary' => $row->Dictionary]);
            }
        }
        if ($total < 1) {
            return false;
        }
        for ($i = 0; $i < 100; $i++) {
            $wordIndex = $this->crypto_rand_secure(0, $total - 1);
            $words_lower = 0;
            foreach ($drawPile as $words_upper => $name) {
                if ($wordIndex < $words_upper) {
                    $result = gdn::sql()->select("Word")->from($this->getDictionaryPath($name))->where("WordIndex", $wordIndex - $words_lower + 1)->get()->firstRow();

                    if ($result) {
                        return $result->Word;
                    }
                    continue;
                }
                $words_lower = $words_upper;
            }
        }
        return false;
    }

    //Function to draw a pseudorandom number, more random than mt_rand
    function crypto_rand_secure($min, $max) {
        $range = $max - $min;
        if ($range == 0)
            return $min; // not so random...
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

    public function addNewWordsFromFile($unsafe_name, $unsafe_content) {
        $extensionIndex = strrpos($unsafe_name, '.');
        if ($extensionIndex) {
            $unsafe_name = substr($unsafe_name, 0, $extensionIndex);
        }
        $safe_name = preg_replace("/[^a-z0-9]/", '', strtolower($unsafe_name));
        $unsafe_newWords = preg_split("/[.\n, ]/", strtolower($unsafe_content));
        $this->addNewWords($unsafe_newWords, $safe_name);
        Gdn::sql()->replace("DPDictionariesNames", ['Dictionary' => $safe_name, 'NumWords' => gdn::sql()->getCount($this->getDictionaryPath($safe_name)), 'UserID' => Gdn::session()->UserID], ['Dictionary' => $safe_name],true);
    }

    public function PluginController_Dictionary_Create($Sender, $Args) {
        if (!gdn::session()->checkPermission("Garden.Settings.Manage")) {
            echo "{\"error\":\"no permission\"}";
            exit();
        }
        if ($Args[0] == "random") {
            echo $this->getRandomWord();
        }
        if ($Args[0] == "upload") {
            if (isset($_POST['name']) && isset($_POST['content'])) {
                $this->addNewWordsFromFile($_POST['name'], $_POST['content']);
            }
        }
        if ($Args[0] == "download") {
            if (is_numeric($Args[1])) {
                $dictionary = gdn::sql()->select("Dictionary")->from("DPDictionariesNames")->where("DictionaryID", intval($Args[1]))->get()->firstRow();
                if ($dictionary) {
                    header("Content-type: text/csv");
                    header("Cache-Control: no-store, no-cache");
                    header('Content-Disposition: attachment; filename="' . $dictionary->Dictionary . '.csv"');
                    $words = gdn::sql()->select("Word")->from($this->getDictionaryPath($dictionary->Dictionary))->get();
                    if ($row = $words->nextRow()) {
                        echo $row->Word;
                    }
                    while ($row = $words->nextRow()) {
                        echo "\n" . $row->Word;
                    }
                    exit();
                }
            }
        }
        if ($Args[0] == "remove") {
            if (isset($_POST['DictionaryID']) && is_numeric($_POST['DictionaryID'])) {
                $dic_id = intVal($_POST['DictionaryID']);
                $dictionary = gdn::sql()->select("Dictionary")->from("DPDictionariesNames")->where("DictionaryID", $dic_id)->get()->firstRow();
                if ($dictionary) {
                    gdn::sql()->delete("DPDictionariesNames", ["DictionaryID" => $dic_id]);
                    Gdn::structure()->table($this->getDictionaryPath($dictionary->Dictionary))->drop();
                }
            }
        }
    }

    public function getDictionaryPath($dictionary) {
        return "dpdictionaries_$dictionary";
    }

    public function setup() {
        Gdn::structure()->Table("DPDictionariesNames")
                ->primaryKey("DictionaryID")
                ->column("Dictionary", 'varchar(255)', false, 'unique')
                ->column("NumWords", 'int')
                ->column("UserID", 'int')->set();
        $default_dictionaries = scandir(__DIR__ . '/dictionaries');
        foreach ($default_dictionaries as $dictionary_to_add) {
            if (stripos($dictionary_to_add, ".csv")) {
                $this->addNewWordsFromFile($dictionary_to_add, file_get_contents(__DIR__ . '/dictionaries/' . $dictionary_to_add));
            }
        }
    }

    function addNewWords($unsafe_newWords, $dictionary) {
        $table_name = $this->getDictionaryPath($dictionary);
        Gdn::structure()
                ->Table($table_name)
                ->primaryKey("WordIndex")
                ->column("Word", 'varchar(20)', false, 'unique')->set();
        $existing_words = [];
        $all_words_result = gdn::sql()->select("Word")->from($table_name)->get();
        while ($row = $all_words_result->nextRow()) {
            $existing_words[$row->Word] = true;
        }
        foreach ($unsafe_newWords as $unsafe_word) {
            //Check if it's a safe word to store (only alphanumeric characters)
            if ($this->isOkayWord($unsafe_word) && !isset($existing_words[$unsafe_word])) {
                $existing_words[$unsafe_word] = true;
                //If there isn't a word yet in the database, add it to the database
                Gdn::sql()->replace($table_name, ['Word' => $unsafe_word],['Word'=>$unsafe_word],true);
            }
        }
    }

    function isOkayWord($word) {
        $length = strlen($word);
        return (!preg_match('/[^a-z0-9]/', $word)) && $length > 3 && $length < 20;
    }

    public function AddCss() {
        ?><style>.DictionaryList
            {
                width:100%;
            }
            .DictionaryItem
            {
                margin:10px;
                width:90%;
                border:solid burlywood;
                background-color: beige;
                display:list-item;
            }
            .DictionaryItem p
            {
                display: inline;
            }
            .DictionaryButtons
            {
                float:right;
            }
            .DictionaryButtons p
            {

                cursor:pointer;
                border: 1px solid black;
                margin:2px;
            }
            .DictionaryDescription
            {
                width:70%;
            }

        </style><?php
    }

}
