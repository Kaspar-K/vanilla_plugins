<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo T($this->Data['Title']); ?></h1>
<div class="Info">
   <?php echo T('Configure the AdSense plugin.'); ?>
</div>
<div class="FilterMenu">
     <?php
$AdFormatText = array
(
    'Image/Text Ad, Leaderboard (728x90)',
    'Image/Text Ad, Banner (468x60)',
    'Image/Text Ad, Half Banner (234x60)',
    'Link Unit, up to 5 links (728x15)',
    'Link Unit, up to 4 links (728x15)',
    'Link Unit, up to 5 links (468x15)',
    'Link Unit, up to 4 links (468x15)'
);
$AdFormatValue = array
(
    '728x90_as',
    '468x60_as',
    '234x60_as',
    '728x15_0ads_al_s',
    '728x15_0ads_al',
    '468x15_0ads_al_s',
    '468x15_0ads_al',
);
$AdFormatOptions = ArrayCombine($AdFormatValue, $AdFormatText);
$ColorAttrs = array('size' => '6', 'maxlength' => '6', 'style' => 'width:60px');

echo '<p style="color:#000 !important">'.T('Note that the 728-wide formats will not completely fit in a comment using the default Vanilla themes. You will need to alter your theme to fit these ads.').'</p>';
echo $this->Form->Open() . $this->Form->Errors();
?>
<table class="AltColumns">
    <tbody>
        <tr>
            <td><?php echo $this->Form->Label(T('AdSense ID'), 'Plugins.AdSense.google_ad_client'); ?></td>
            <td><?php echo $this->Form->Input('Plugins.AdSense.google_ad_client'); ?></td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('Ad Slot'), 'Plugins.AdSense.google_ad_slot'); ?></td>
            <td><?php echo $this->Form->Input('Plugins.AdSense.google_ad_slot'); ?></td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('Ad Channel'), 'Plugins.AdSense.google_ad_channel'); ?></td>
            <td><?php echo $this->Form->Input('Plugins.AdSense.google_ad_channel'); ?></td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('Ad Format'), 'Plugins.AdSense.google_ad_format'); ?></td>
            <td><?php echo $this->Form->DropDown('Plugins.AdSense.google_ad_format', $AdFormatOptions); ?></td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('URL Color'), 'Plugins.AdSense.google_color_url'); ?></td>
            <td>
                #<?php echo $this->Form->Input('Plugins.AdSense.google_color_url','text',$ColorAttrs); ?>
                <div id="Form_Plugins-dot-AdSense-dot-google_color_url-picked" class="ASPicked"></div>
            </td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('Text Color'), 'Plugins.AdSense.google_color_text'); ?></td>
            <td>
                #<?php echo $this->Form->Input('Plugins.AdSense.google_color_text','text',$ColorAttrs); ?>
                <div id="Form_Plugins-dot-AdSense-dot-google_color_text-picked" class="ASPicked"></div>
            </td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('Link Color'), 'Plugins.AdSense.google_color_link'); ?></td>
            <td>
                #<?php echo $this->Form->Input('Plugins.AdSense.google_color_link','text',$ColorAttrs); ?>
                <div id="Form_Plugins-dot-AdSense-dot-google_color_link-picked" class="ASPicked"></div>
            </td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('Background Color'), 'Plugins.AdSense.google_color_bg'); ?></td>
            <td>
                #<?php echo $this->Form->Input('Plugins.AdSense.google_color_bg','text',$ColorAttrs); ?>
                <div id="Form_Plugins-dot-AdSense-dot-google_color_bg-picked" class="ASPicked"></div>
            </td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('Border Color'), 'Plugins.AdSense.google_color_border'); ?></td>
            <td>
                #<?php echo $this->Form->Input('Plugins.AdSense.google_color_border','text',$ColorAttrs); ?>
                <div id="Form_Plugins-dot-AdSense-dot-google_color_border-picked" class="ASPicked"></div>
            </td>
        </tr>
        <tr>
            <td><?php echo $this->Form->Label(T('Centered Ads?'), 'Plugins.AdSense.CenteredAds'); ?></td>
            <td><?php echo $this->Form->CheckBox('Plugins.AdSense.CenteredAds'); ?></td>
        </tr>
        <tr>
            <td colspan="2"><?php echo $this->Form->Close('Save'); ?></td>
        </tr>
    </tbody>
</table>
</div>
