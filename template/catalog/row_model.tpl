<td><a href="{if $aRow.seourl}{if $oLanguage->getConstant('global:url_is_lower',0)}{$aRow.seourl|@lower}{else}{$aRow.seourl}{/if}{else}{if $oLanguage->getConstant('global:url_is_lower',0)}/cars/{$aRow.cat_name|@lower}/{$aRow.mod_id|@lower}{else}/cars/{$aRow.cat_name}/{$aRow.mod_id}{/if}{/if}">{$aRow.name}</a></td>
<td>{$aRow.month_start}.{$aRow.year_start} - {$aRow.month_end}.{$aRow.year_end}</td>
