<?
function SqlEcVtCall($aData)
{
	$sWhere.=$aData['where'];

	if ($aData['id']) {
		$sWhere.=" and r.id='".$aData['id']."'";
	}

	$sSql="select r.* 
			from ec_vt r
			where 1=1
				".$sWhere."
			group by r.id";

	return $sSql;
}
?>