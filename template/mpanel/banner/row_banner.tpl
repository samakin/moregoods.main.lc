<td>{$aRow.id}</td>
<td>{$aRow.name}</td>
<td>{$aRow.link}</td>
<td>{include file='addon/mpanel/image.tpl' aRow=$aRow sWidth=30}</td>
<td>{include file='mpanel/banner/image.tpl' aRow=$aRow sWidth=30}</td>
<td>{include file='addon/mpanel/visible.tpl' aRow=$aRow}</td>
<td>{$aRow.id_brand}</td>
<td>{$aRow.id_brand_group}</td>
<td>{$aRow.sort}</td>
<td nowrap>
{include file='addon/mpanel/base_row_action.tpl' sBaseAction=$sBaseAction}
</td>

