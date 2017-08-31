<?php if (!defined('APPLICATION')) exit(); ?>
<?php
?><h1><?php echo T($this->Data['Title']); ?></h1>
<div class="Info">
    <?php echo T($this->Data['Description']); ?>
</div>
<div class="TimeLineList">
<?php
$timelines=gdn::sql()->select("TimeLineID,NumEvents,TimeLine")->from("TGTimeLinesNames")->get();
while(($row=$timelines->nextRow()))
{
    echo "<div class='TimeLineItem'><p class='TimeLineDescription'>$row->TimeLine: $row->NumEvents word(s)</p><div class='TimeLineButtons'><p onclick='window.open(gdn.url(\"plugin/timeline/download/\"+$row->TimeLineID))'>Download timeline</p><p onclick='removeTimeLine(\"$row->TimeLineID\",\"$row->TimeLine\")'>Remove Item</p></div></div>";
}
?>
</div>
<p>Upload new timeline:</p>
<input type="file" id="file">
<p>New timeline name:</p>
<input type="text" id="name">
<input type="button" onclick="addNewTimeLine()" value="Upload the timeline!">
