<?php if (!defined('APPLICATION')) exit(); ?>
<?php
?><h1><?php echo T($this->Data['Title']); ?></h1>
<div class="Info">
    <?php echo T($this->Data['Description']); ?>
</div>
<div class="DictionaryList">
<?php
$dictionaries=gdn::sql()->select("DictionaryID,NumWords,Dictionary")->from("Dictionaries")->get();
while(($row=$dictionaries->nextRow()))
{
    echo "<div class='DictionaryItem'><p class='DictionaryDescription'>$row->Dictionary: $row->NumWords word(s)</p><div class='DictionaryButtons'><p onclick='window.open(gdn.url(\"plugin/dictionary/download/\"+$row->DictionaryID))'>".
            t("Download dictionary")."</p><p onclick='removeDictionary(\"$row->DictionaryID\",\"$row->Dictionary\")'>".
            t("Remove Item")."</p></div></div>";
        
}
?>
</div>
<p><?php=t("Upload new dictionary");?>:</p>
<input type="file" id="file">
<p><?php=t("New dictionary name");?>:</p>
<input type="text" id="name">
<input type="button" onclick="addNewDictionary()" value="<?php=t("Upload new dictionary");?>!">
