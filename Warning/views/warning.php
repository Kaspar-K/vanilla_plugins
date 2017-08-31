<?php if (!defined('APPLICATION')) exit(); ?>
<script language="javascript">
   jQuery(document).ready(function($) {
      $('#Form_ReasonText').focus(function() {
         $('#Form_Reason2').attr('checked', 'checked');
      });
   });
</script>

<h1><?php echo $this->Data('Title'); ?></h1>
<div class="Wrap">
<?php
echo $this->Form->Open();
echo $this->Form->Errors();
?>

<div class="Warning">
   <?php
   echo FormatString(T('Warning.AboutTo','You are about to warn {User.UserID,user}.'), $this->Data);
   ?>
</div>

<?php
echo 
    '<div class="P"><b>'. T('Warning.WarningType','Warning Type') .'</b></div>',
    '<div class="Warnings">',
    $this->Form->RadioList('Warning', $this->Data['WarnLevel']),
    '</div>',
    '<div class="P"><b>'. T('Warning.Reason','Reason') .'</b></div>',
    $this->Form->TextBox('Reason', array('MultiLine' => TRUE));
echo 
    '<div class="Buttons P">',
    Anchor('Ban','user/ban/'.$this->Data['User']->UserID,'Button Popup'),
    ' ',
    C('EnabledPlugins.TempBan') ? Anchor('Temp Ban','user/tempban/'.$this->Data['User']->UserID,'Button Popup').' ':'',
    $this->Form->Button(T('Warning.Warn','Warn'), array('class'=>'Button WarnButton')), '</div>';
echo $this->Form->Close();
?>
</div>
