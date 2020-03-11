{* Template for "FindExpert" custom search component. *}
{assign var="showBlock" value="'searchForm'"}
{assign var="hideBlock" value="'searchForm_show','searchForm_hide'"}

<div class="crm-form-block crm-search-form-block">
  <div id="searchForm">
    {include file="CRM/Findexpert/FindExpertCriteria.tpl"}
  </div>
</div>

{if $rowsEmpty}
  {include file="CRM/Contact/Form/Search/Custom/EmptyResults.tpl"}
{/if}

{if $summary}
  {$summary.summary}: {$summary.total}
{/if}

{if $rows}
  {* Search request has returned 1 or more matching rows. Display results and collapse the search criteria fieldset. *}
  {assign var="showBlock" value="'searchForm_show'"}
  {assign var="hideBlock" value="'searchForm'"}

  <fieldset>

    {* This section handles form elements for action task select and submit *}
    {include file="CRM/Contact/Form/Search/ResultTasks.tpl"}

    {* This section displays the rows along and includes the paging controls *}
    <p>

      {include file="CRM/common/pager.tpl" location="top"}

      {include file="CRM/common/pagerAToZ.tpl"}

      {strip}
    <table class="selector" summary="{ts}Search results listings.{/ts}">
      <thead class="sticky">
      <th scope="col" title="Select All Rows">{$form.toggleSelect.html}</th>
      {foreach from=$columnHeaders item=header}
        <th scope="col">
          {if $header.sort}
            {assign var='key' value=$header.sort}
            {$sort->_response.$key.link}
          {else}
            {$header.name}
          {/if}
        </th>
      {/foreach}
      <th>&nbsp;</th>
      </thead>

      {counter start=0 skip=1 print=false}
      {foreach from=$rows item=row}
        <tr id='rowid{$row.contact_id}' class="{cycle values="odd-row,even-row"}">
          {assign var=cbName value=$row.checkbox}
          <td>{$form.$cbName.html}</td>
          {foreach from=$columnHeaders item=header}
            {assign var=fName value=$header.sort}
            {if $fName eq 'sort_name'}
              <td><a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}">{$row.sort_name}</a></td>
            {else}
              <td>{$row.$fName}</td>
            {/if}
          {/foreach}
          <td>
            <span>
              <a href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$row.contact_id`"}" class="action-item action-item-first" title="View Contact Details">View</a>
            </span>
          </td>
        </tr>
      {/foreach}
    </table>
    {/strip}

    <script type="text/javascript">
      {* this function is called to change the color of selected row(s) *}
      var fname = "{$form.formName}";
      on_load_init_checkboxes(fname);
    </script>

    {include file="CRM/common/pager.tpl" location="bottom"}

    </p>
  </fieldset>
  {* END Actions/Results section *}
{/if}



