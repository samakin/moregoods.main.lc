<br>
<div class="ms-news-list">
    {foreach from=$aItem item=aRow}
    {include file=$sDataTemplate}
    {/foreach}
</div>
<div class="ms-stepper">
    {$sStepper}
    <div class="clear"></div>
</div>