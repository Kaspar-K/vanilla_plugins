<?php if (!defined('APPLICATION')) exit(); ?>
<?php
?><script>
function removeFeed(feedkey)
{
    if(confirm("Are you sure you want to delete this feed?")){
        $.post(gdn.url('plugin/magpiefeeds/deletefeed'),{ feedkeyToDelete: feedkey}, function (data) {
            location.reload();
        }
        );
    }
}
jQuery(document).ready(function ($) {
    // Enable multicomplete on selected inputs
    jQuery('.AuthorSuggest').on('keyup',function () {
            var $author = $(this);
            if($author.val().length<1){
                return;
            }
            $.get(gdn.url('/user/tagsearch') + "<?=c('Garden.RewriteUrls',false)?'?':'&';?>q=" + $author.val(), function (data) {
                $($author.data('autosuggestlist'))
                        .find('option')
                        .remove()
                        .end();
                for (var i = 0; i < data.length && i<10; i++) {
                    $($author.data('autosuggestlist')).append($("<option></option>")
                            .attr("value", data[i].id)
                            .text(data[i].name));
                }
            }, "json");
        });
});</script><h1><?php echo T($this->Data['Title']); ?></h1>
<div class="Info">
    <?php echo T($this->Data['Description']); ?>
</div>
<div class="FilterMenu">
    <?php
    echo Anchor(
            T($this->Plugin->IsEnabled() ? 'Disable' : 'Enable'), $this->Plugin->AutoTogglePath(), 'SmallButton'
    );
    ?>
</div>
<?php if (!$this->Plugin->IsEnabled()) return; ?>

<h3>Add a Feed</h3>
<div class="AddFeed">
    <?php
    if ($dirh) {
        while (($dirElement = readdir($dirh)) !== false) {
            
        }
        closedir($dirh);
    }
    echo $this->Form->Open(array(
        'action' => Url('plugin/magpiefeeds/addfeed')
    ));
    echo $this->Form->Errors();

    $Refreshments = array(
        "1h" => T("Hourly"),
        "1d" => T("Daily"),
        "3d" => T("Every 3 Days"),
        "1w" => T("Weekly"),
        "2w" => T("Every 2 Weeks")
    );
    $sql = Gdn::Sql();
    $categories = $sql->select("CategoryID,Name")->from("Category")->where("CategoryID>", 0)->orderBy("CategoryID", 'asc')->get();
    $CategoryNameByID = array();
    while (($row = $categories->nextRow())) {
        $CategoryNameByID[$row->CategoryID] = $row->CategoryID . ": " . T($row->Name);
    }
    ?>
    <ul>
        <li>
            <div class="Info">Add a new Auto Discussion Feed</div>
            <?php
            echo $this->Form->Label('Feed URL', 'MagpieFeeds.FeedURL');
            echo $this->Form->TextBox('MagpieFeeds.FeedURL', array('class' => 'InputBox'));
            ?></li>
        <li><?php
            echo $this->Form->CheckBox('MagpieFeeds.FeedOption.Historical', T('Import Older Posts'), array('value' => '1'));
            ?></li>

        <li>
            <?php
            echo $this->Form->Label('Minimum Polling Frequency', 'MagpieFeeds.FeedOption.Refresh');
            echo $this->Form->DropDown('MagpieFeeds.FeedOption.Refresh', $Refreshments, array('value' => "1d"));
            ?></li>
        <li><?php
            ?></li><li><?php
            echo $this->Form->Label('Username:', 'MagpieFeeds.FeedOption.UserID');
            $systemuserID = Gdn::userModel()->getSystemUserID();
            $userName = (new UserModel())->getID($systemuserID)->Name;
            echo '<input data-autosuggestlist="#Form_MagpieFeeds-dot-FeedOption-dot-UserID" type="text" value="' . htmlspecialchars($userName) . '" class="AuthorSuggest"/>';
            echo $this->Form->DropDown('MagpieFeeds.FeedOption.UserID', [$systemuserID => $userName], ['value' => $userName]);
            // echo $this->Form->CategoryDropDown('Category');
            ?>
        </li><li><?php
            echo $this->Form->Label('Category', 'MagpieFeeds.FeedOption.CategoryID');
            echo $this->Form->DropDown('MagpieFeeds.FeedOption.CategoryID', $CategoryNameByID, array('value' => "1"));
            // echo $this->Form->CategoryDropDown('Category');
            ?>
        </li>
    </ul>
    <?php
    echo $this->Form->Close("Add Feed");
    ?>
</div>

<h3><?php echo T('Active Feeds'); ?></h3>
<a href="<?php echo Url('/plugin/magpiefeeds/checkfeeds'); ?>">Check Feed Now</a>
<p><span>This will trigger the feed to be polled and it will take you to a blank page, this is normal because you are not supposed to see the data. Just close the window and go back to the forum to see if the feed was posted. </span></p>
<div class="ActiveFeeds">
    <?php
    $NumFeeds = count($this->Data['Feeds']);
    if (!$NumFeeds) {
        echo T("You have no active auto feeds at this time.");
    } else {
        echo "<div>" . $NumFeeds . " " . Plural($NumFeeds, "Active Feed", "Active Feeds") . "</div>\n";
        foreach ($this->Data['Feeds'] as $FeedURL => $FeedItem) {
            $LastUpdate = $FeedItem['LastImport'];
            $Frequency = val('Refresh', $FeedItem, T('Unknown'));
            $UserID = val('UserID', $FeedItem, T('Unknown'));
            $CategoryID = val('CategoryID', $FeedItem, T('Unknown'));
            $CategoryLabel=val($CategoryID,$CategoryNameByID,T('Unknown'));
            ?>
            <div class="FeedItem">
                <div class="DeleteFeed">
                    <p class="RemoveFeedLink" onclick="removeFeed('<?php echo MagpieFeedsPlugin::EncodeFeedKey($FeedURL); ?>');">Delete this Feed</a>
                </div>
                <div class="FeedContent">
                    <div class="FeedItemURL"><?php echo Anchor($FeedURL, $FeedURL); ?></div>
                    <div class="FeedItemInfo">
                        <span>Updated: <?php echo $LastUpdate; ?></span>
                        <span>Refresh: <?php echo $Frequency; ?></span>
                        <span>AuthorID: <?php echo $UserID; ?></span>
                        <span>Category: <?php echo $CategoryLabel; ?></span>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    ?>
</div>
<script type="text/javascript">
    jQuery(document).ready(function ($) {

        // Show drafts delete button on hover
        // Show options on each row (if present)
        $('div.ActiveFeeds div.FeedItem').each(function(i, obj) {
            var row = obj;
            var del = $(row).find('div.DeleteFeed');
            $(del).hide();
            $(row).hover(function () {
                $(del).show();
                $(row).addClass('Active');
            }, function () {
                if (!$(del).find('div.FeedItem').hasClass('ActiveFeed'))
                    $(del).hide();

                $(row).removeClass('ActiveFeed');
            });
        });

    });
</script>