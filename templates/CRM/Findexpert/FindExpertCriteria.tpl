{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
{* Search criteria form elements - Find Experts *}

{* Set title for search criteria accordion *}
{capture assign=editTitle}{ts}Edit Search Criteria for Expert(s){/ts}{/capture}

{strip}
  <div class="crm-block crm-form-block crm-basic-criteria-form-block">
    <div class="crm-accordion-wrapper crm-case_search-accordion {if $rows}collapsed{/if}">
      <div class="crm-accordion-header crm-master-accordion-header">
        {$editTitle}
      </div><!-- /.crm-accordion-header -->
      <div class="crm-accordion-body">

        {if $form.sector_id}
          <div class="crm-section sector-section">
            <div class="label">
              <label for="sector-select">{ts}Sector(s){/ts}</label>
            </div>
            <div class="content" id="sector-select">
              {$form.sector_id.html}
              {literal}
                <script type="text/javascript">
                cj(function() {
                  cj("select#sector_id").crmasmSelect({
                    respectParents: true
                  });
                });
                </script>
              {/literal}
            </div>
            <div class="clear"></div>
          </div>
        {/if}

        {if $form.expertise_id}
          <div class="crm-section expertise-section">
            <div class="label">
              <label for="expertise-select">{ts}Area(s) of Expertise{/ts}</label>
            </div>
            <div class="content" id="expertise-select">
              <div>To select multiple area's of expertise: Hold CTRL-key</div>
              {$form.expertise_id.html}
            </div>
            <div class="content" id="deselect-all-expertise" style="cursor: pointer;"><a>Deselect all area(s) of expertise</a></div>
            <div class="clear"></div>
          </div>
        {/if}

        {if $form.generic_id}
          <div class="crm-section generic-section">
            <div class="label">
              <label for="generic-select">{ts}Generic Skill(s){/ts}</label>
            </div>
            <div class="content" id="generic-select">
              {$form.generic_id.html}
              {literal}
                <script type="text/javascript">
                  cj("select#generic_id").crmasmSelect({
                    respectParents: true
                  });
                </script>
              {/literal}
            </div>
            <div class="clear"></div>
          </div>
        {/if}

        {if $form.language_id}
          <div class="crm-section language-section">
            <div class="label">
              <label for="language-select">{ts}Language(s){/ts}</label>
            </div>
            <div class="content" id="language-select">
              {$form.language_id.html}
              {literal}
                <script type="text/javascript">
                  cj("select#language_id").crmasmSelect({
                    respectParents: true
                  });
                </script>
              {/literal}
            </div>
            <div class="clear"></div>
          </div>
        {/if}

        {if $form.overall_string}
          <div class="crm-section overall-section">
            <div class="messages status no-popup help">
              <div class="icon inform-icon"></div>
              {ts}You can search the expert data on a string.
              The default behaviour is it will search for everything you enter in this field. So if you enter <em>ecological farm</em> it will search for that complete text string.<br />
              If you want to search for <em>ecological</em> OR <em>farm</em> you should put brackets around your search string and separate the words with comma's. The field should now have the input <em>(ecological, farm)</em>.</br>
              If you want to search for <em>ecological</em> AND <em>farm</em> as separate words you should put curly brackets around your search string and separate the words with comma's. The field should now have the input <em>{literal}{ecological, farm}{/literal}</em>.
              {/ts}
            </div>
            <div class="label">
              <label for="overall-string">{$form.overall_string.label}</label>
            </div>
            <div class="content" id="overall-string">
              {$form.overall_string.html}
            </div>
            <div class="clear"></div>
            <div class="label">
              <label for="ignore_cases">{$form.ignore_cases.label}</label>
            </div>
            <div class="content" id="ignore-cases">
              {$form.ignore_cases.html}
            </div>
            <div class="clear"></div>
          </div>
        {/if}

        {if $form.countries_visited}
          <div class="crm-section countries_visited-section">
            <div class="label">
              <label for="countries_visited-select">{ts}Countries visited in Work History{/ts}</label>
            </div>
            <div class="content" id="countries_visited-select">
              {$form.countries_visited.html}
              {literal}
                <script type="text/javascript">
                  cj("select#countries_visited").crmasmSelect({
                    respectParents: true
                  });
                </script>
              {/literal}
            </div>
            <div class="clear"></div>
          </div>
        {/if}

        <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl"}</div>
      </div><!-- /.crm-accordion-body -->
    </div><!-- /.crm-accordion-wrapper -->
  </div><!-- /.crm-form-block -->
{/strip}
{literal}
  <script type="text/javascript">
    var objAreaOfExpertisesByParent = {/literal}{$areas_of_expertise_list}{literal};
    var expertise_id_select = cj('select#expertise_id');
    var allAreaOfExpertiseOptions = expertise_id_select.children("option").clone();

    function update_aoe_list(){
      var selectedSectors = cj("select#sector_id").val();
      expertise_id_select.empty();
      expertise_id_select.append(allAreaOfExpertiseOptions.clone());

      expertise_id_select.children("option").hide();

      if(selectedSectors == null || typeof selectedSectors === 'undefined'){
        expertise_id_select.children("option").show();
      } else {
        for (var i = 0; i < selectedSectors.length; i++) {
          var sector_id = selectedSectors[i];

          if(objAreaOfExpertisesByParent[sector_id].length > 0){
            for (var j = 0; j < objAreaOfExpertisesByParent[sector_id].length; j++) {
              var area_id = objAreaOfExpertisesByParent[sector_id][j].id;
              expertise_id_select.children("option[value="+area_id+"]").show();
            }
          }
        }
      }
    }

    cj(document).ready(function(){
      cj('select#sector_id').change(function(){
        update_aoe_list();
      });

      cj('.crmasmListItemRemove').click(function(){
        cj(this).parent().remove();
        update_aoe_list();
      });
      cj('#deselect-all-expertise').click(function(){
        cj('select#expertise_id').val([]);
      });

      cj('.crm-accordion-header').click(function(){
        update_aoe_list();
      });
    });

    cj(function() {
      cj().crmAccordions();
    });
  </script>
{/literal}


