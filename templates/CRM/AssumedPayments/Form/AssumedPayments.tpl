{crmScope extensionKey='de.systopia.assumedpayments'}

{$form.open}
  <div class="crm-block crm-form-block">

    {include file="CRM/common/formButtons.tpl" location="top"}

    <table class="form-layout" style="max-width: 500px;">
      {foreach from=$elementNames item=elementName}
        <tr class="crm-assumedpayments-row">
          <td class="label">{$form[$elementName].label}</td>
          <td>
            {$form[$elementName].html}
            {if $form[$elementName].description}
              <div class="description">{$form[$elementName].description}</div>
            {/if}
          </td>
        </tr>
      {/foreach}
    </table>

    {include file="CRM/common/formButtons.tpl" location="bottom"}

  </div>
{$form.close}

{/crmScope}
