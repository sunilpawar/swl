<div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">

  {if $smarty.get.state eq 'done'}
    <div class="help">
      {ts}Sync completed with result counts as:{/ts}<br/> 
      <!--<tr><td>{ts}Civi Blocked{/ts}:</td><td>{$stats.Blocked}&nbsp; (no-email / opted-out / do-not-email / on-hold)</td></tr>-->
      {foreach from=$stats item=group}
      {assign var="groups" value=$group.stats.group_id|@implode:','}
      <h2>{$group.name}</h2>
      <table class="form-layout-compressed bold">
      <tr><td>{ts}Contacts on CiviCRM{/ts}:</td><td>{$group.stats.c_count}</td></tr>
      <tr><td>{ts}Contacts on SWL (originally){/ts}:</td><td>{$group.stats.hs_count}</td></tr>
      <tr><td>{ts}Contacts that were in sync already{/ts}:</td><td>{$group.stats.in_sync}</td></tr>
      <tr><td>{ts}Contacts Subscribed or updated at SWL{/ts}:</td><td>{math equation="x - y" x=$group.stats.c_count y=$group.stats.error_count}</td></tr>
      <tr><td>{ts}Contacts Unsubscribed from SWL{/ts}:</td><td>{$group.stats.removed}</td></tr>
      </table>
      {/foreach}
    </div>
  {/if}
  
  <div class="crm-block crm-form-block crm-campaignmonitor-sync-form-block">
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
  </div>
  
</div>
