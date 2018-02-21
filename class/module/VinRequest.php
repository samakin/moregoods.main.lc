<?

/**
 * @author Mikhail Starovoyt
 * @author Alexander Belogura
 * @version 4.5.2
 */

class VinRequest extends Base
{
	//-----------------------------------------------------------------------------------------------
	public function __construct()
	{
		Base::$bXajaxPresent=true;
		Base::$aData['template']['bWidthLimit']=true;

		Resource::Get()->Add('/js/vin_request.js',5);
	}
	//-----------------------------------------------------------------------------------------------
	public function Index()
	{
		if (Base::GetConstant('vin_request:only_for_registered',1)) {
			Auth::NeedAuth();
			if (strpos(Auth::$aUser['login'],Auth::$aUser['id'])) {
				Base::Redirect("/?action=user_login");
				return ;
			}
		}

		$iNumInsertedItems = 30;
		if (Base::$aRequest['is_post']) {
			Base::$aRequest['vin'] = Cart::ParseVinCode( Base::$aRequest['vin'] );
			if ((!Base::$aRequest['mobile'] && !Auth::$aUser['id']) || !Base::$aRequest['vin']
			|| (!Base::$aRequest['model'] && Auth::$aUser['type_']!='manager')
			|| !Base::$aRequest['azpDescript1']
			|| (Base::GetConstant('vin_request:has_capcha',0) && !Capcha::CheckMathematic())
			) {
				$sError = "Please, fill the required fields";
				Form::ShowError($sError);
				Base::$aRequest['action']='vin_request_add';
				if(Base::$aRequest['RowCount']>0)
				for ($i = 1; $i <= Base::$aRequest['RowCount']; $i++) {
					$azp[$i]['name']=Base::$aRequest['azpDescript'.$i];
					$azp[$i]['cnt']=Base::$aRequest['azpCnt'.$i];
				}
				Base::$tpl->assign('azp',$azp);
				Base::$tpl->assign('aData',Base::$aRequest);
			}
			else {

				if (Base::$aRequest['additional']) $sAdditional=implode(',',Base::$aRequest['additional']);
				//[----- INSERT -----------------------------------------------------]
				for ($i=1; $i <= $iNumInsertedItems; $i++) {
					if (Base::$aRequest['azpDescript'.$i]) {
						$aPartList[]=array('i'=>$i
						,'i_visible'=>'1'
						,'name'=>base64_encode(Base::$aRequest['azpDescript'.$i])
						,'number'=>Base::$aRequest['azpCnt'.$i]);
					}
				}
				if ((Auth::$aUser['id'] && Auth::$aUser['type_']!='manager') || Base::$aRequest['id_customer_for']) {
					$iIdRegisteredUser= Auth::$aUser['id'];
					if (Base::$aRequest['id_customer_for']) $iIdRegisteredUser=Base::$aRequest['id_customer_for'];

					$iIdManagerFixed=Base::$db->GetOne("select id_manager_fixed from vin_request where 1=1
						and id_user='".$iIdRegisteredUser."' order by id desc");

					if (!$iIdManagerFixed && Auth::$aUser['id_referer_manager'] ) {
						$iIdManagerFixed= Auth::$aUser['id_referer_manager'];
					}
				}
				else {
					$aRegisteredUser=Auth::AutoCreateUser();
					$iIdRegisteredUser=$aRegisteredUser['id'];

					$oUser=new User();
				}

				$oImageProcess=new ImageProcess();
				$aImage=$oImageProcess->GetUploadedImage('passport_image',1,'/imgbank/Image/passport_image/',Auth::$aUser['id']
				,Base::GetConstant('passport_image:big_width',800),Base::GetConstant('passport_image:small_width',150),true);

				if ($aImage[1]) {
					$aPassportImage=array(
					'id_user'=>Auth::$aUser['id'],
					'name'=>$aImage[1]['name'],
					'name_small'=>$aImage[1]['name_small'],
					);
					Db::AutoExecute('passport_image',$aPassportImage);
				}

				$sPartArray=serialize($aPartList);
				if (Base::$aRequest['mobile']) $sMobile=Base::$aRequest['operator'].Base::$aRequest['mobile'];
				Base::$aRequest=String::FilterRequestData(Base::$aRequest);
				
				Base::$aRequest['name_make']=Db::GetOne("select title from cat where id='".Base::$aRequest['marka']."'");
				Base::$aRequest['name_model'] = Db::GetOne("select name from cat_model where tof_mod_id='".Base::$aRequest['model']."'");
				
				$sQuery="insert into vin_request(id_user,id_manager_fixed,vin,country_producer,engine
						,month,year
						,volume,body,description,kpp,additional, customer_comment, part_array
						,mobile
						,wheel,utable,engine_number,engine_code,engine_volume,kpp_number
						,passport_image_name, passport_image_name_small,id_make,id_model
						)
        			        values('".$iIdRegisteredUser."','$iIdManagerFixed','".Base::$aRequest['vin']."','".Base::$aRequest['country_producer']."'
        			        ,'".Base::$aRequest['engine']."','".Base::$aRequest['Month']."','".Base::$aRequest['Year']."'
        			        ,'".Base::$aRequest['volume']."'
        			        ,'".Base::$aRequest['body']."','".Base::$aRequest['description']."'
        			        ,'".Base::$aRequest['kpp']."'
        			        ,'$sAdditional','".Base::$aRequest['customer_comment']."','$sPartArray'
        			        ,'".$sMobile."'
							,'".Base::$aRequest['wheel']."','".Base::$aRequest['utable']."','".Base::$aRequest['engine_number']."'
							,'".Base::$aRequest['engine_code']."','".Base::$aRequest['engine_volume']."'
							,'".Base::$aRequest['kpp_number']."'
							,'".$aPassportImage['name']."','".$aPassportImage['name_small']."'
							,'".Base::$aRequest['marka']."','".Base::$aRequest['model']."'
        			        )";
				//[----- END INSERT -------------------------------------------------]

				Db::Execute($sQuery);
				$iVinId=Db::InsertId();
				// For Garage
				if (Base::GetConstant("garage:is_available",0)=="1"){
					$sQuery="select * from user_auto where id_user='".$iIdRegisteredUser."' and
						vin='".Base::$aRequest['vin']."'";
					$aGarage=Db::GetAll($sQuery);
					
					if (!$aGarage){
						Base::$aRequest['id_make'] = Base::$aRequest['marka'];
						Base::$aRequest['id_model'] = Base::$aRequest['model'];
						$iIs_abs = (array_search('ABS',Base::$aRequest['additional']) !== false ? 1 : 0);
						$iIs_conditioner = (array_search('Conditioner',Base::$aRequest['additional']) !== false ? 1 : 0);
						$iIs_hyd_weel = (array_search('Hydromultiplier',Base::$aRequest['additional']) !== false ? 1 : 0);
						$sQuery="insert into user_auto (id_user,post,modified,id_make,id_model,vin,country_producer,
							engine,month,year,volume,body,customer_comment,kpp,wheel,is_abs,is_hyd_weel,is_conditioner)
							values
							('".$iIdRegisteredUser."',UNIX_TIMESTAMP(),UNIX_TIMESTAMP(),'".Base::$aRequest['id_make']."'
							,'".Base::$aRequest['id_model']."','".Base::$aRequest['vin']."'
							,'".Base::$aRequest['country_producer']."'
							,'".Base::$aRequest['engine']."','".Base::$aRequest['Month']."'
							,'".Base::$aRequest['Year']."','".Base::$aRequest['volume']."'
							,'".Base::$aRequest['body']."','".Base::$aRequest['customer_comment']."'
							,'".Base::$aRequest['kpp']."','".Base::$aRequest['wheel']."'
							,'".$iIs_abs."','".$iIs_hyd_weel."','".$iIs_conditioner."')";
						Db::Execute($sQuery);
					}
				}
				// END For Garage
				if (Auth::$aUser) $aUser=Auth::$aUser;
				else $aUser=$aRegisteredUser;
				if (Base::GetConstant("manager:enable_vin_notification_on_email","1")) {
					$aSmartyTemplate=String::GetSmartyTemplate('manager_mail_vin', array(
							'aVin'=>array(
									"id_vin_request"=>$iVinId,
									"id_user"=>$iIdRegisteredUser,
									"id_manager"=>$iIdManagerFixed,
									"marka"=>Base::$aRequest['name_make'],
									"vin"=>Base::$aRequest['vin'],
									"model"=>Base::$aRequest['name_model'],
									"country_producer"=>Base::$aRequest['country_producer'],
									"engine"=>Base::$aRequest['engine'],
									"Month"=>Base::$aRequest['Month'],
									"Year"=>Base::$aRequest['Year'],
									"volume"=>Base::$aRequest['volume'],
									"body"=>Base::$aRequest['body'],
									"description"=>Base::$aRequest['description'],
									"kpp"=>Base::$aRequest['kpp'],
									"additional"=>$sAdditional,
									"customer_comment"=>Base::$aRequest['customer_comment'],
									"parts"=>$sPartArray,
									"mobile"=>$sMobile,
									"wheel"=>Base::$aRequest['wheel'],
									"utable"=>Base::$aRequest['utable'],
									"engine_number"=>Base::$aRequest['engine_number'],
									"engine_code"=>Base::$aRequest['engine_code'],
									"engine_volume"=>Base::$aRequest['engine_volume'],
									"kpp_number"=>Base::$aRequest['kpp_number'],
									"name"=>$aPassportImage['name'],
									"name_small"=>$aPassportImage['name_small'],
					),
							'aUser'=>$aUser,
					));
					Mail::AddDelayed($aUser['manager_email'].", ".Base::GetConstant('manager:email_recievers','info@moregoods.com.ua')
					,$aSmartyTemplate['name']." ".$iVinId,
					$aSmartyTemplate['parsed_text'],'',"info",false);
				}
				
				if (Auth::$aUser['type_']=='manager') Base::Redirect("/?action=vin_request_manager");
				Base::Redirect("/?action=vin_request&is_post_request=1");
			}
		}

		if (Base::$aRequest['action']=='vin_request_add' || Base::$aRequest['action']=='vin_request_copy'
		|| Base::$aRequest['action']=='vin_request_add_from_garage') {
			if (Base::$aRequest['action']=='vin_request_copy') {
				if (Auth::$aUser['type_']=='customer') $sVinWhere.=" and vr.id_user='".Auth::$aUser['id']."'";

				$aVinRequest=Db::GetRow(Base::GetSql('VinRequest',array(
				'where'=> " and vr.id='".Base::$aRequest['id']."' ".$sVinWhere)));
				Base::$tpl->assign('aData',$aVinRequest);
			}
			// For making VIN from Garage
			if (Base::$aRequest['action']=='vin_request_add_from_garage'){
				if (Base::GetConstant("garage:is_available",0)=="1"){
					$sQuery="select * from user_auto where id_user='".Auth::$aUser['id']."' and
						id='".Base::$aRequest['car_id']."'";
					$aVinRequest=Db::GetRow($sQuery);
					
					$aVinRequest['marka_title'] = Db::GetOne("select title from cat where id='".$aVinRequest['id_make']."'");
					$aVinRequest['model_title'] = Db::GetOne("select name from cat_model where tof_mod_id='".$aVinRequest['id_model']."'");
					$aVinRequest['date'] = $aVinRequest['year'].'-01-01';
					$aVinRequest['Month'] = $aVinRequest['month'];
					$aVinRequest['id_own_auto'] = $aVinRequest['id'];
					$aVinRequest['marka'] = $aVinRequest['id_make'];
					$aVinRequest['model'] = $aVinRequest['id_model'];
					
 					Base::$tpl->assign('aData',$aVinRequest);
 					
 					if ($aVinRequest['id_make']) {
	 					$aModelAsoc=TecdocDb::GetModelAssoc(
	 							array(
	 									"id_make"=>$aVinRequest['id_make'],
	 									"sOrder"=>" order by name"
	 							)
	 					);
	 					Base::$tpl->assign('aModel',array(""=>Language::getMessage('choose model'))+$aModelAsoc);
 					}
				}
			}
			// END For making VIN from Garage

			Base::$aMessageJavascript = array(
			"vin_request_ok_auth_user"=> Language::GetMessage("Our manager contact with you soon"),
			"vin_request_ok_user"=>Language::GetMessage("You passed partial registration on a site. After a dispatch your wiens of"
			." query it is necessary to fill the profayl contact information, that a manager could operatively  contact with you"),
			"vin_request_error_message"=> Language::GetMessage("vin_request_error_message"),
			"vin_request_17symbol"=> Language::GetMessage("vin_have_no_17_symbols"),
			"vin_request_model_empty"=> Language::GetMessage("model_and_series_empty"),
			"vin_request_spareparts_empty"=> Language::GetMessage("-Перечень запчастей не заполнен"),
			"vin_request_phoneformat_error"=> Language::GetMessage("wrong_phone_format"),
			"vin_request_kpp_empty"=> Language::GetMessage("kpp_number_empty"),
			"vin_request_podkapot_empty"=> Language::GetMessage("-Номер с подкапотной таблички не заполнен"),
			"vin_request_engine_number_empty"=> Language::GetMessage("-Номер двигателя не заполнен"),
			"vin_request_engine_kod_empty"=> Language::GetMessage("-Код двигателя не заполнен"),
			"vin_request_parts_list_empty"=> Language::GetMessage("В запросе должна быть хотя бы одна строка"),
			"vin_request_less_100lines"=> Language::GetMessage("В одном запросе может быть не более 100 строк"),
			"vin_request_region_empty"=> Language::GetMessage("region_empty"),
			);

			$aVinMarka=array('' => Language::getMessage('select brand')) + $this->GetMarka();

			$aVinBody = $this->Get_aTypeBody();
			/*
			$aVinBody=array(
			Language::GetMessage('sedan'),
			Language::GetMessage('hetch'),
			Language::GetMessage('universal'),
			Language::GetMessage('jeep'),
			Language::GetMessage('kupe'),
			Language::GetMessage('cabriolet'),
			Language::GetMessage('minivan'),
			Language::GetMessage('microbus'),
			);
			*/
			$aVinKpp=$this->Get_aTypeKpp();
			/*
			array(
			Language::GetMessage('Automat'),
			Language::GetMessage('Mechanics'),
			Language::GetMessage('Variator'),
			Language::GetMessage('Robotic'),
			);
			*/
			$aVinWheel=$this->Get_aTypeWheel();
			/*
			array(
			Language::GetMessage('Leftside'),
			Language::GetMessage('Rightside'),
			);
			*/
			
			$sVinOperator=Base::GetConstant('vin_request:phone_prefix','+7095,+7343,+7391,+7411,+7424,+7501,+7812,+7831,+7843'
			.',+7865,+7901,+7902,+7903,+7904,+7905,+7906,+7908,+7909,+7910,+7911,+7912,+7913,+7914,+7915,+7916,+7917,+7918,+7919'
			.',+7920,+7921,+7922,+7923,+7924,+7926,+7927,+7928,+7929,+7950,+7960,+7961,+7962');
			$aVinOperator=preg_split("/[\s,;]+/",$sVinOperator);

			$aVinMonth=$this->Get_Months();

			if (Base::$aRequest['marka']) {
				//opti
				/*Base::$tpl->assign('aModel',array(""=>Language::getMessage('choose model'))+Db::GetAssoc("Assoc/OptiCatModel",array(
				"id_make"=>Base::$aRequest['marka'],
				"check_visible"=>1,
				"sOrder"=>" order by name "
				)));*/
				$aModelAsoc=TecdocDb::GetModelAssoc(
						array(
								"id_make"=>Base::$aRequest['marka'],
								"sOrder"=>" order by name"
						)
				);
				Base::$tpl->assign('aModel',array(""=>Language::getMessage('choose model'))+$aModelAsoc);
			}
			Base::$tpl->assign('aVinMarka',$aVinMarka);
			Base::$tpl->assign('aVinBody',$aVinBody);
			Base::$tpl->assign('aVinKpp',$aVinKpp);
			Base::$tpl->assign('aVinWheel',$aVinWheel);
			Base::$tpl->assign('aVinOperator',$aVinOperator);
			Base::$tpl->assign('aManagerHasCustomer',array(''=>'')+Db::GetAssoc(Base::GetSql('Assoc/ManagerHasCustomer')));
			Base::$tpl->assign('aVinMonth',$aVinMonth);

			if (Base::GetConstant('vin_request:has_capcha',0)) {
				$oCpacha= new Capcha();
				Base::$tpl->assign('sCapcha',$oCpacha->GetMathematic());
			}

			$sUserName = "<span style='color:#ff0000;'>".Language::getMessage("unregistered")."</span>";
			if (Auth::$aUser['type_']=='customer') $sUserName=Auth::$aUser['login'];
			if (Auth::$aUser['type_']=='manager' && Base::$aRequest['login_customer_for']){
				$sUserName=Base::$aRequest['login_customer_for'];
			}
			
			if ($aListOwnAuto = OwnAuto::GetListOwnAuto()) {
				$aListOwnAuto = array('' => Language::getMessage('Select_own_auto')) + $aListOwnAuto;
				Base::$tpl->assign('aListOwnAuto',$aListOwnAuto);
			}
			
			$aData=array(
			'sHeader'=>"method=post enctype='multipart/form-data' onsubmit=\"return mvr.CheckForm(this);\"",
			'sTitle'=>"",
			'sContent'=>Base::$tpl->fetch('vin_request/customer/form_vin_request.tpl'),
			'sSubmitButton'=>'Send',
			'sSubmitAction'=>'vin_request',
			'sError'=>$sError,
			);
			$oForm=new Form($aData);

			Base::$tpl->assign('sForm',$oForm->GetForm());
			Base::$tpl->assign('sVinHeader',Language::GetMessage('Vin Request Form for -')." ".$sUserName);
			Base::$sText.=Base::$tpl->fetch('vin_request/customer/vin_request_add.tpl');

			return;
		}

		if (Base::$aRequest['action']=='vin_request_delete') {
			Db::Execute("delete from vin_request where id='".Base::$aRequest['id']."'
				and order_status in ('new','refused') ".Auth::$sWhere);
		}

		/** For unregistered vin_requests */
		if (!Auth::$aUser['id']) {
			if (Base::$aRequest['is_post_request'] != 1){
				Auth::NeedAuth();
			}

			$aSmartyTemplate=String::GetSmartyTemplate('unregistered_vin_request', $aData);
			Base::$sText.=$aSmartyTemplate['parsed_text'];
			return;
		}
		//----------------------------------

		Auth::NeedAuth('customer');

		$oTable=new Table();
		$oTable->sSql="select v.*,cat.title as marka from vin_request v 
				inner join cat on cat.id = v.id_make
				where 1=1 ".Auth::$sWhere;
		$oTable->aOrdered="order by post_date desc";
		$oTable->aColumn=array(
		'id'=>array('sTitle'=>'#'),
		'order_status'=>array('sTitle'=>'Order Status'),
		'vin'=>array('sTitle'=>'VIN'),
		'post'=>array('sTitle'=>'Post'),
		'order_status'=>array('sTitle'=>'Status'),
		'marka'=>array('sTitle'=>'Marka'),
		'manager_comment'=>array('sTitle'=>'Manager Comment'),
		'action'=>array(),
		);
		$oTable->sDataTemplate='vin_request/customer/row_vin_request.tpl';
		$oTable->sButtonTemplate='vin_request/customer/button_vin_request.tpl';

		Base::$sText.=$oTable->getTable("Vin requests",'vin_request');
	}
	//-----------------------------------------------------------------------------------------------
	public function Preview()
	{
		Auth::NeedAuth('customer');
		Base::$aTopPageTemplate=array('panel/tab_customer_cart.tpl'=>'vin_request');

		$sHilightColor = 'A52A2A';
		Base::$tpl->assign_by_ref("oCatalog", new Catalog());

		Base::$aData['template']['sPageTitle']=Language::getMessage("Preview Vin request");

		$aVinRequest=Db::GetRow(Base::GetSql('VinRequest',array(
		'where'=> " and vr.id='".Base::$aRequest['id']."' and vr.id_user='".Auth::$aUser['id']."'")));

		if (!$aVinRequest) Base::Redirect('/?action=vin_request');

		Base::$tpl->assign('aData',$aVinRequest);

		$aPartList=unserialize($aVinRequest['part_array']);
		if ($aPartList) {
			foreach ($aPartList as $key => $value) {
				$aPartList[$key]['name']=base64_decode($value[name]);
				$aHilight = Db::GetRow("SELECT COUNT(*) as hilight_item FROM `cart`
					WHERE (`code` = '{$aPartList[$key]['code']}' OR `code` = '{$aPartList[$key]['user_input_code']}')
						AND `type_` = 'cart' AND `id_user` = {$_SESSION['user']['id']}");
				if($aHilight) {
					if(is_array($aHilight)) {
						$aPartList[$key]['hilight_it'] = array_shift($aHilight);
					}
				}
				unset($aHilight);
			}
		}

		Base::$tpl->assign('aPartList',$aPartList);
		Base::$tpl->assign('sHilightColor',$sHilightColor);
		if ($aPartList) foreach ($aPartList as $value) {
			//if ($value['i_visible']) $dSubtotal+=$value['price']*$value['number'];
			$aMultipleCode[]=$value['code'];
		}

		Base::$tpl->assign('iShowRealCodes',Base::GetConstant('vin_request:show_real_codes',0));

		Base::$tpl->assign('sMultipleCode',implode(',',$aMultipleCode));

		Base::$sText.=Base::$tpl->fetch('vin_request/customer/vin_request_preview.tpl');
	}
	//-----------------------------------------------------------------------------------------------
	public function Manager()
	{
		Form::Message();
		Auth::NeedAuth('manager');
		Base::$aTopPageTemplate=array('panel/tab_manager_cart.tpl'=>'vin_request');

		// ######### Edit #########
		if ( Base::$aRequest['action']=='vin_request_manager_edit') {

			if (Base::$aRequest['id']) {
				Base::$db->Execute("update vin_request set is_viewed='1' where id='".Base::$aRequest['id']."' ");
			}
			Form::BeforeReturn('vin_request_manager');

			$aVinRequest=Db::GetRow(Base::GetSql('VinRequest',array(
			'id'=>Base::$aRequest['id'],
			//'id_in'=>$this->GetVinIdList(),
			)));

			if (!$aVinRequest) Base::Redirect('/?action=vin_request_manager');
			if ($aVinRequest['order_status']=='new') {
				Db::Execute("update vin_request set order_status='work' $sSet
					where id='".Base::$aRequest['id']."'");
			}
			Base::$tpl->assign('aData',$aVinRequest);
			Base::$tpl->assign('aDeliveryTypeAssoc',array("0"=>Language::GetMessage("Choose"))+Db::GetAssoc("Assoc/DeliveryType"));

			$aData=array(
			'sHeader'=>"method=post enctype='multipart/form-data'",
			'sTitle'=>"VIN Request Preview",
			'sAdditionalTitle'=>" # ".Base::$aRequest['id'],
			'sContent'=>Base::$tpl->fetch('vin_request/manager/form_vin_request.tpl'),
			'bShowBottomForm'=>false,
			'sError'=>$sError,
			);
			$oForm=new Form($aData);
			Base::$sText.=$oForm->getForm();

			$aPartList=unserialize($aVinRequest['part_array']);
			if ($aPartList) foreach ($aPartList as $key => $value)
			$aPartList[$key]['name']=base64_decode($value[name]);

			Base::$tpl->assign('aPartList',$aPartList);
			if ($aPartList) {
				foreach ($aPartList as $value) {
					$dSubtotal+=floatval($value['number'])*floatval($value['price']);
				}
			}
			Base::$tpl->assign('dSubtotal',$dSubtotal);
			Base::$tpl->assign('iRowCount',count($aPartList));

			//Base::$tpl->assign('aManagerLogin',  Db::GetAssoc(Base::GetSql('vin_request/manager/LoginAssoc')) );

			Base::$sText.=Base::$tpl->fetch('vin_request/manager/form_vin_request_part_list.tpl');
			return;
		}

		$aCountVinRequest=Db::GetAssoc("select id_user, count(id) from vin_request group by id_user ");
		Base::$tpl->assign("aCountVinRequest",$aCountVinRequest);

		// ######### List #########
		$aData=array(
		'sHeader'=>"method=get",
		//'sTitle'=>"Search vin requests",
		'sContent'=>Base::$tpl->fetch('vin_request/manager/form_vin_request_search.tpl'),
		'sSubmitButton'=>'Search',
		'sSubmitAction'=>'vin_request_manager',
		'sReturnButton'=>'Clear',
		'bIsPost'=>0,
		'sError'=>$sError,
		);
		$oForm=new Form($aData);

		Base::$sText.=$oForm->getForm();

		// --- search ---
		if (Base::$aRequest['search']['is_viewed']) $sWhere.=" and vr.is_viewed ='0'";
		if (Base::$aRequest['search']['id']) $sWhere.=" and vr.id = '".Base::$aRequest['search']['id']."'";
		if (Base::$aRequest['search']['login']) $sWhere.=" and u.login ='".Base::$aRequest['search']['login']."'";
		if (Base::$aRequest['search']['ip']) $sWhere.=" and u.ip ='".Base::$aRequest['search']['ip']."'";
		if (Base::$aRequest['search']['is_remember']) $sWhere.=" and vr.is_remember ='1'";
		if (Base::$aRequest['search']['phone']) $sWhere.=" and uc.phone like '%".Base::$aRequest['search']['phone']."%'";
		if (Base::$aRequest['search']['email']) $sWhere.=" and u.email like '%".Base::$aRequest['search']['email']."%'";
		if (Base::$aRequest['search']['order_status']) $sWhere.=" and vr.order_status = '"
		.Base::$aRequest['search']['order_status']."'";
		if (Base::$aRequest['search']['marka']) $sWhere.=" and vr.marka = '".Base::$aRequest['search']['marka']."'
			and vr.order_status!='new'";
		// --------------

		$oTable=new Table();
		$oTable->sSql=Base::GetSql('VinRequest',array(
		'where'=>$sWhere,
		));

		$oTable->aOrdered="order by vr.id desc";
		$oTable->iRowPerPage=20;
		$oTable->aColumn=array(
		'id'=>array('sTitle'=>'#'),
		'order_status'=>array('sTitle'=>'Order Status'),
		'id_user'=>array('sTitle'=>'Customer/Phone'),
		'vin'=>array('sTitle'=>'VIN'),
		'post'=>array('sTitle'=>'Post Date'),
		'order_status'=>array('sTitle'=>'Status'),
		'name_marka'=>array('sTitle'=>'Marka'),
		'manager_comment'=>array('sTitle'=>'Manager Comment/Remember'),
		'action'=>array(),
		);
		$oTable->sDataTemplate='vin_request/manager/row_vin_request.tpl';

		Base::$sText.=$oTable->GetTable("Vin requests from customers");
	}
	//-----------------------------------------------------------------------------------------------
	public function ManagerSave($bRedirect=true)
	{
		Auth::NeedAuth('manager');

		if (Base::$aRequest['is_post']) {
			$aUserInputCode = array();

			if (Base::$aRequest['data']['change_login'] && Base::$aRequest['data']['current_login']) {
				Db::Execute("update user inner join user_customer on user.id=user_customer.id_user
				set user.login='".Base::$aRequest['data']['change_login']."'
				, user.email='".Base::$aRequest['data']['email']."'
				, user_customer.phone='".Base::$aRequest['data']['phone']."'
				where user.login='".Base::$aRequest['data']['current_login']."'");
			} else {
				Db::Execute(" update user inner join user_customer on user.id=user_customer.id_user
				set user.email='".Base::$aRequest['data']['email']."'
				, user_customer.phone='".Base::$aRequest['data']['phone']."'
				where user.login='".Base::$aRequest['data']['current_login']."'"
				);
			}

			//[----- UPDATE -----------------------------------------------------]
			if (Base::$aRequest['part']) {
				$j = 0;
				foreach(Base::$aRequest['part'] as $value) {
					++ $j;
					if(
					$value['user_input_code'] &&
					(strripos($value['code'], "ZZZ_") !== false)
					)
					{
						$aUserInputCode[$j] = $value['user_input_code'];
					} else {
						$aUserInputCode[$j] = $value['code'];
					}
					$aCode[]="'" . Catalog::StripCode( $value['code'] ) . "'";
				}
				//$aCrosHash=Db::GetAssoc("select cp.code, cp.* from cat_part as cp where code in (".implode(',',$aCode).")");
			}

			$aUser=Db::GetRow(Base::GetSql('Customer', array('login'=>Base::$aRequest['data']['current_login'])));
			foreach ($aUserInputCode as $iKey=>$sValue)
			{
				$s=Base::GetSql('Catalog/Price',array(
				'aCode'=>array($sValue)
				,'customer_discount'=>Discount::CustomerDiscount($aUser)
				));
				$aPriceAll=Db::GetAll($s);
				if ($aPriceAll) {
					$dMinPrice=0;
					foreach ($aPriceAll as $aValue)
					{
						if (floatval($aValue['price'])>0 && ($aValue['price']<$dMinPrice || !$dMinPrice))
						$dMinPrice=$aValue['price'];
					}
					$aPrice[$iKey]=$dMinPrice;
				}
				else $aPrice[$iKey]=0;
			}

			$iCountError = 0;
			for ($i=1;$i<=100;$i++) {
				if (Base::$aRequest['part'][$i]) {

					if (Base::$aRequest['part'][$i]['number']<=0) Base::$aRequest['part'][$i]['number']=1;

					if (substr(Base::$aRequest['part'][$i]['code'],0,4)=='ZZZ_') {
						$sCode=Base::$aRequest['part'][$i]['code'];

					} else {
						$aPriceCodeRequest=Db::GetRow(Base::GetSql('Catalog/Price'
						,array(
						'aCode'=>array(Base::$aRequest['part'][$i]['code']),
						'where'=>" and p.price>0",
						)));
						if ($aPriceCodeRequest && !Base::$aRequest['part'][$i]['code_visible'] &&
						Base::GetConstant('vin_request:hide_code',0) )
						{
							$sCode='ZZZ_'.$aPriceCodeRequest['id'];
						} else {
							$sCode=Catalog::StripCode(Base::$aRequest['part'][$i]['code']);
						}
					}

					$aPartList[] = array(
					'i'=>$i,
					'name'=>base64_encode(Base::$aRequest['part'][$i]['name']),
					'marka'=>Base::$aRequest['part'][$i]['marka'],
					'code'=> $sCode,
					'user_input_code' => $aUserInputCode[$i],
					'cat_name'=>Base::$aRequest['part'][$i]['cat_name'],
					'code_visible'=>Base::$aRequest['part'][$i]['code_visible'],
					'i_visible'=>Base::$aRequest['part'][$i]['i'],
					'number'=>Base::$aRequest['part'][$i]['number'],
					'price'=>$aPrice[$i],
					'price_original'=>Base::$aRequest['part'][$i]['price_original'],
					'term'=>Base::$aRequest['part'][$i]['term'],
					'id_provider'=>Base::$aRequest['part'][$i]['id_provider'],
					'provider'=> $aProviderHash[Base::$aRequest['part'][$i]['id_provider']]['name'],
					'code_delivery'=> $aProviderHash[Base::$aRequest['part'][$i]['id_provider']]['code_delivery'],
					'weight'=>Base::$aRequest['part'][$i]['weight'],
					);
					
					if ($aPrice[$i] == 0)
						$iCountError += 1;
				}
			}
			$sPartArray=serialize($aPartList);

			$aVinRequestUpdate=String::FilterRequestData(Base::$aRequest['data'],array('id_delivery_type','price_delivery'));
			$aVinRequestUpdate['part_array']=$sPartArray;
			$aVinRequestUpdate['manager_comment']=Base::$aRequest['manager_comment'];
			$aVinRequestUpdate['remember_text']=Base::$aRequest['remember_text'];

			$oImageProcess=new ImageProcess();
			$aImage=$oImageProcess->GetUploadedImage('passport_image',1,'/imgbank/Image/passport_image/',Auth::$aUser['id']
			,Base::GetConstant('passport_image:big_width',800),Base::GetConstant('passport_image:small_width',150),true);

			if (Base::$aRequest['data']['manager_image_url']) {
				$sFileContent=file_get_contents(trim(Base::$aRequest['data']['manager_image_url']));

				$sUrlExtension= strtolower(substr(Base::$aRequest['data']['manager_image_url'], -3, 3));
				if ($sUrlExtension=='peg') $sDownloadedExtension=".jpg";
				elseif ($sUrlExtension=='jpg') $sDownloadedExtension=".jpg";
				elseif ($sUrlExtension=='gif') $sDownloadedExtension=".gif";
				elseif ($sUrlExtension=='png') $sDownloadedExtension=".png";
				elseif ($sUrlExtension=='pdf') $sDownloadedExtension=".pdf";
				else $sError.="$i: only jpg and gif\n";

				if ($sFileContent && !$sError) {
					$sTargetPath='/imgbank/Image/';
					$sPath=$sTargetPath.date('Y').'/'.date('m').'/';
					if (!file_exists(SERVER_PATH.$sPath)) {
						if (!file_exists(SERVER_PATH.$sTargetPath)) mkdir(SERVER_PATH.$sTargetPath);
						if (!file_exists(SERVER_PATH.$sTargetPath.date('Y'))) mkdir(SERVER_PATH.$sTargetPath.date('Y'));
						mkdir(SERVER_PATH.$sPath);
					}
					$sUnique=uniqid();
					$aImage[1]['name']=$sPath.'image_'.$sUnique.$sDownloadedExtension;
					$aImage[1]['name_small']=$aImage[1]['name'];
					file_put_contents(SERVER_PATH.$aImage[1]['name'],$sFileContent);
				}
			}

			if ($aImage[1]) {
				$aPassportImage=array(
				'id_user'=>Auth::$aUser['id'],
				'name'=>$aImage[1]['name'],
				'name_small'=>$aImage[1]['name_small'],
				);
				Db::AutoExecute('passport_image',$aPassportImage);

				$aVinRequestUpdate['manager_image_name']=$aPassportImage['name'];
				$aVinRequestUpdate['manager_image_name_small']=$aPassportImage['name_small'];
			}


			Db::AutoExecute('vin_request',$aVinRequestUpdate,'UPDATE'
			,"id='".Base::$aRequest['id']."'	and id in (".$this->GetVinIdList(true).")");
			//[----- END UPDATE -------------------------------------------------]
		}
		if ($bRedirect) {
			$sMessage = Language::GetMessage('saved, but exist errors, see empty price lines. Count: ').$iCountError;
			if (!$iCountError || $iCountError == 0)
				$sMessage = Language::GetMessage('saved');
			
			Base::Redirect('/?action=vin_request_manager_edit&form_message='.$sMessage.'&id='.Base::$aRequest['id']);
		}
	}
	//-----------------------------------------------------------------------------------------------
	public function ManagerSend()
	{
		Auth::NeedAuth('manager');

		if (Base::$aRequest['is_post']) {
			$this->ManagerSave(false);

			$aVinRequest=Db::GetRow(Base::GetSql('VinRequest',array(
			'id'=>Base::$aRequest['id'],
			'id_in'=>$this->GetVinIdList(),
			)));
			if (!$aVinRequest) Base::Redirect('/?action=vin_request_manager');

			$aCustomer=Db::GetRow(Base::GetSql('Customer',array('id'=>$aVinRequest['id_user'])));
			$aManager=Db::GetRow(Base::GetSql('Manager',array('id'=>$aCustomer['id_manager'])));

			$aPartList=unserialize($aVinRequest['part_array']);
			if ($aPartList)
			foreach ($aPartList as $key => $value)
			{
				$aPartList[$key]['name']=base64_decode($value[name]);
				$aPartList[$key]['print_price']=Base::$oCurrency->PrintPrice($value['price']);
			}
			$aVinRequest['part_list']=$aPartList;
			$aVinRequest['print_price_delivery']=Base::$oCurrency->PrintPrice($aVinRequest['price_delivery']);

			Db::Execute("update vin_request set order_status='parsed' where
				order_status in ('work','refused')
				and id='".Base::$aRequest['id']."'
				and id in (".$this->GetVinIdList(true).") ");

			$this->ManagerRelease(Base::$aRequest['id']);

			if ($aVinRequest['mobile']) {
				$this->ManagerMobileNotification($aVinRequest);
			}

			if (Base::$aRequest['section']=='customer') {
				Base::$tpl->assign('aVinRequest',$aVinRequest);
				$sVinRequestTable=Base::$tpl->fetch("vin_request/manager/vin_request_table.tpl");

				Message::CreateDelayedNotification($aVinRequest['id_user'], 'vin_request_sent'
				,array(
				'aVinRequest'=>$aVinRequest,
				'aManager'=>$aManager,
				'aCustomer'=>$aCustomer,
				'sVinRequestTable'=>$sVinRequestTable,
				),true);
			}
		}
		Base::Redirect('/?action=vin_request_manager');
	}
	//-----------------------------------------------------------------------------------------------
	public function ManagerRefuse()
	{
		Auth::NeedAuth('manager');

		if (Base::$aRequest['is_post']) {
			$this->ManagerSave(false);

			$aVinRequest=Db::GetRow(Base::GetSql('VinRequest',array(
			'id'=>Base::$aRequest['id'],
			'id_in'=>$this->GetVinIdList(),
			)));
			if (!$aVinRequest) Base::Redirect('/?action=vin_request_manager');

			$aCustomer=Db::GetRow(Base::GetSql('Customer',array('id'=>$aVinRequest['id_user'])));
			$aManager=Db::GetRow(Base::GetSql('Manager',array('id'=>$aCustomer['id_manager'])));

			Db::Execute("update vin_request set order_status='refused' where id='".Base::$aRequest['id']."'");
			$this->ManagerRelease(Base::$aRequest['id']);

			Message::CreateDelayedNotification($aVinRequest['id_user'], 'vin_request_refused'
			,array('aVinRequest'=>$aVinRequest,'aManager'=>$aManager,'aCustomer'=>$aCustomer),true);

			if ($aVinRequest['mobile']) {
				//$this->ManagerMobileNotification($aVinRequest);
			}
		}
		Base::Redirect('/?action=vin_request_manager');
	}
	//-----------------------------------------------------------------------------------------------
	/**
	 * Makes manager able to take new vin request from general queue
	 */
	public function ManagerRelease($iId)
	{
		Auth::NeedAuth('manager');

		Db::Execute("update user_manager set id_vin_request_fixed='0'
				where id_user='".Auth::$aUser['id']."' and id_vin_request_fixed='$iId'");
	}
	//-----------------------------------------------------------------------------------------------
	public function ManagerMobileNotification($aVinRequest)
	{
		if (!Base::GetConstant('vin_request:sms_notification',0)) return;

		$aCustomer=Db::GetRow( Base::GetSql('Customer',array('id'=>$aVinRequest['id_user'])) );

		$aSmartyTemplate=String::GetSmartyTemplate('parsed_vin_request', array(
		'aVinRequest'=>$aVinRequest,
		'aCustomer'=>$aCustomer
		));
		Sms::AddDelayed($aVinRequest['mobile'],strip_tags($aSmartyTemplate['parsed_text']));

		$aSmartyTemplate=String::GetSmartyTemplate('vin_request_mobile_parsed');
		$sNoteDescription=$aSmartyTemplate['parsed_text'];
		Message::AddNote($aVinRequest['id_user'], Language::GetMessage('Vin request mobile parsed Subject')
		,$sNoteDescription);
	}
	//-----------------------------------------------------------------------------------------------
	/**
	 * Get the list of id vinrequests which manager can have access
	 *
	 * @return array
	 */
	public function GetVinIdList($bReturnArray=false)
	{
		$sVinRequestQueue=Base::GetSql('VinRequest/MyQueue',array(
		'id_manager'=>Auth::$aUser['id'],
		'view_all'=>(Auth::$aUser['is_super_manager'] || Auth::$aUser['is_sub_manager'] ? "1":"") ,
		'assoc'=>($bReturnArray ? "1":"") ,
		));
		if ($bReturnArray) {
			return implode(',',Db::GetAssoc($sVinRequestQueue));
		}
		return $sVinRequestQueue;
	}
	//-----------------------------------------------------------------------------------------------
	public function ManagerRemember()
	{
		Auth::NeedAuth('manager');

		if (Base::$aRequest['id']) {
			$aVinRequest['is_remember']=(Base::$aRequest['checked']=='true' ? 1:0);
			Db::AutoExecute('vin_request',$aVinRequest,'UPDATE',"id='".Base::$aRequest['id']."'");
		}
	}
	//-----------------------------------------------------------------------------------------------
	public function ManagerDelivery()
	{
		if (Base::$aRequest['id_delivery_type']) {
			$aDeliveryType=Db::GetRow(Db::GetSql('DeliveryType',array('id'=>Base::$aRequest['id_delivery_type'])));

			Base::$oResponse->AddScript("$('#price_delivery_id').val('".$aDeliveryType['price']."')");
		}
	}
	//-----------------------------------------------------------------------------------------------
	public function ManagerSendPreview()
	{
		if (Base::$aRequest['is_post']) {
			if (Base::$aRequest['part']) {
				$this->ManagerSave(false);
			}
			else {
				Db::Execute("update vin_request set order_status='parsed' where
					order_status in ('work','refused')
					and id='".Base::$aRequest['id']."'
					and id in (".$this->GetVinIdList(true).") ");

				Mail::AddDelayed(Base::$aRequest['data']['to'],stripslashes(Base::$aRequest['data']['subject'])
				,stripslashes(Base::$aRequest['data_content']),Base::$aRequest['data']['from'],Base::$aRequest['data']['from_name'],true,3);

				$sMessage="&aMessage[MF_NOTICE]=Mail send";
				$aMessage['MF_NOTICE']='Mail Send';
				Form::RedirectAuto($sMessage);
			}
		}

		$aVinRequest=Base::$db->getRow(Base::GetSql('VinRequest',array(
		'id'=>Base::$aRequest['id'],
		'id_in'=>$this->GetVinIdList(),
		)));
		$aCustomer=Db::GetRow(Base::GetSql('Customer',array('id'=>$aVinRequest['id_user'])));
		Base::$tpl->assign('aCustomer',$aCustomer);

		$aPartList=unserialize($aVinRequest['part_array']);
		if ($aPartList)	foreach ($aPartList as $key => $value)
		{
			$aPartList[$key]['name']=base64_decode($value[name]);
			$aPartList[$key]['print_price']=Base::$oCurrency->PrintPrice($value['price']);
		}
		$aVinRequest['part_list']=$aPartList;
		$aVinRequest['print_price_delivery']=Base::$oCurrency->PrintPrice($aVinRequest['price_delivery']);

		$aCustomer=Db::GetRow(Base::GetSql('Customer',array('id'=>$aVinRequest['id_user'])));
		$aManager=Db::GetRow(Base::GetSql('Manager',array('id'=>$aCustomer['id_manager'])));

		Base::$tpl->assign('aVinRequest',$aVinRequest);
		$sVinRequestTable=Base::$tpl->fetch("vin_request/manager/vin_request_table.tpl");
		$aTemplate=String::GetSmartyTemplate('vin_request_sent'
		,array(
		'aVinRequest'=>$aVinRequest,
		'sVinRequestTable'=>$sVinRequestTable,
		'aCustomer'=>$aCustomer,
		'aManager'=>$aManager,
		));
		Base::$tpl->assign('sSubject',$aTemplate['name']);
		Base::$tpl->assign('sEditor',Admin::getFCKEditor('data_content',$aTemplate['parsed_text'],683,600));


		$aData=array(
		'sHeader'=>"method=post",
		'sWidth'=>"800px",
		'sTitle'=>"Vin request send preview",
		'sContent'=>Base::$tpl->fetch('vin_request/manager/form_vin_request_send_preview.tpl'),
		'sSubmitButton'=>'Do send',
		'sSubmitAction'=>'vin_request_manager_send_preview',
		);
		$oForm=new Form($aData);
		Base::$sText.=$oForm->getForm();
	}
	//-----------------------------------------------------------------------------------------------
	public function PackageCreate()
	{
		if (Base::$aRequest['id_vin_request']){
			$aData=Db::GetRow(Base::GetSql('VinRequest',array(
			'id'=>Base::$aRequest['id_vin_request'],
			)));
			$aData['price_total']=0;

			$aData['part_list']=unserialize($aData['part_array']);
			if ($aData['part_list']) foreach ($aData['part_list'] as $key => $value) {
				if (stripos($value['code'],'ZZZ')!==false) {
					$aData['part_list'][$key]['info']=Db::GetRow(Base::GetSql("Catalog/Price",array(
					'aCode'=>array($value['user_input_code']),
					'id'=>str_ireplace("ZZZ_","",$value['code']),
					)));
				} else {
					$aData['part_list'][$key]['info']=Db::GetRow(Base::GetSql("Catalog/Price",array(
					'aCode'=>array($value['user_input_code']),
					//'id'=>str_ireplace("ZZZ_","",$value['code']),
					)));
				}
				$aData['part_list'][$key]['name']=base64_decode($value['name']);
				if ($value['price']>0) $aData['price_total']+=($value['price'] * $value['number']);
			}
		}

		if (Base::$aRequest['id_cart_package']){
			$aData=Db::GetRow(Base::GetSql('CartPackage',array(
			'id'=>Base::$aRequest['id_cart_package'],
			)));

			$aData['price_total']=0;
		}

		if ($aData) {
			$bIsEmpty = 1;
			// check correct data in info
			if (count($aData['part_list']) > 0) {
				foreach ($aData['part_list'] as $aPlValue) {
					if ($aPlValue['price'] > 0 && $aPlValue['info']['item_code'] != '' && $aPlValue['info']['pref'] != '') {
						$bIsEmpty = 0;
					}
				}
			}
			
			if (!$bIsEmpty) {
				$aCartPackageInsert=array(
				'id_user'=>$aData['id_user'],
				'price_total'=>($aData['price_total']+$aData['price_delivery']),
				'price_delivery'=>$aData['price_delivery'],
				'order_status'=>'pending',
				'id_delivery_type'=>$aData['id_delivery_type'],
				'vin'=>$aData['vin'],
				'vin_check'=>$aData['vin_check'],
				'is_confirm'=>1,
				);
				Db::AutoExecute('cart_package',$aCartPackageInsert);
				$iCartPackageId=Db::InsertId();
	
				if ($aData['part_list']) foreach ($aData['part_list'] as $sKey=> $aValue) {

					if ($aValue['price'] > 0 && $aValue['info']['item_code'] != '' && $aValue['info']['pref'] != '') {
							
						unset($aCart);
		
						$aCart['item_code']=$aValue['info']['item_code'];
						$aCart['pref']=$aValue['info']['pref'];
						$aCart['cat_name']=$aValue['info']['cat_name'];
						$aCart['code']=$aValue['info']['code'];
		
						$aCart['name_translate']=$aValue['name'];
						$aCart['id_user']=$aData['id_user'];
						$aCart['price']=$aValue['price'];
						$aCart['number']=$aValue['number'];
						//$aCart['price_original']=0;
						$aCart['price_original']=$aValue['info']['price_original'];
						$aCart['type_']='order';
						//$aCart['id_provider']=Base::GetConstant('vin_request:id_provider_created_package',241);
						$aCart['id_provider']=$aValue['info']['id_provider'];
						$aCart['id_cart_package']=$iCartPackageId;
						$aCart['order_status']='pending';
						if ($aCart['item_code']) {
							Db::AutoExecute('cart',Db::Escape($aCart));
						}
					}
	
				}
				$sMessage="&aMessage[MT_NOTICE]=Package created";
			}
			else $sMessage="&aMessage[MT_ERROR]=No data for package";
		} else $sMessage="&aMessage[MT_ERROR]=No data for package";

		Form::RedirectAuto($sMessage);
	}
	//-----------------------------------------------------------------------------------------------
	// for use in other module
	public function Get_aTypeBody() {
		// use count from 1! in smarty tlp check 0 and not set equal
		return array(
				1 => Language::GetMessage('sedan'),
				2 => Language::GetMessage('hetch'),
				3 => Language::GetMessage('universal'),
				4 => Language::GetMessage('jeep'),
				5 => Language::GetMessage('kupe'),
				6 => Language::GetMessage('cabriolet'),
				7 => Language::GetMessage('minivan'),
				8 => Language::GetMessage('microbus'),
		);
		/*
		 array(
		 		1 => Language::GetMessage('автобус'),
		 		2 => Language::GetMessage('автомобиль для нужд коммунального хозяйства'),
		 		3 => Language::GetMessage('вездеход закрытый'),
		 		4 => Language::GetMessage('вездеход открытый'),
		 		5 => Language::GetMessage('вэн'),
		 		6 => Language::GetMessage('закрытый'),
		 		7 => Language::GetMessage('кабрио'),
		 		8 => Language::GetMessage('кузов с твердым верхом'),
		 		9 => Language::GetMessage('купе'),
		 		10 => Language::GetMessage('наклонная задняя часть'),
		 		11 => Language::GetMessage('одноосный тягач'),
		 		12 => Language::GetMessage('особый кузов'),
		 		13 => Language::GetMessage('пикап'),
		 		14 => Language::GetMessage('c бортовой платформой/ходовая часть'),
		 		15 => Language::GetMessage('самосвал'),
		 		16 => Language::GetMessage('седан'),
		 		17 => Language::GetMessage('тарга'),
		 		18 => Language::GetMessage('тягач'),
		 		19 => Language::GetMessage('универсал'),
		 		20 => Language::GetMessage('фургон'),
		 		21 => Language::GetMessage('фургон/универсал'));
		*/
	}
	//-----------------------------------------------------------------------------------------------
	// for use in other module
	public function Get_aTypeKpp() {
		// use count from 1! in smarty tlp check 0 and not set equal
		return array(
			1 => Language::GetMessage('Automat'),
			2 => Language::GetMessage('Mechanics'),
			3 => Language::GetMessage('Variator'),
			4 => Language::GetMessage('Robotic'),
		);
		/*
		 array(
				1 => Language::GetMessage('CVT-автоматическая коробка передач(без ступений)'),
				2 => Language::GetMessage('Variomatic'),
				3 => Language::GetMessage('автоматическая коробка передач'),
				4 => Language::GetMessage('автоматическая коробка передач 3-ступенчатая'),
				5 => Language::GetMessage('автоматическая коробка передач 4-ступенчатая'),
				6 => Language::GetMessage('автоматическая коробка передач 5-ступенчатая'),
				7 => Language::GetMessage('автоматическая коробка передач 6-ступенчатая'),
				8 => Language::GetMessage('лента с толкающими звеньями (бесступенчатая)'),
				9 => Language::GetMessage('механическая коробка передач'),
				10 => Language::GetMessage('полностью автоматическая коробка передач'),
				11 => Language::GetMessage('ручная коробка передач 4-ступенчатая'),
				12 => Language::GetMessage('ручная коробка передач 5-ступенчатая'),
				13 => Language::GetMessage('ручная коробка передач 6-ступенчатая'),
				14 => Language::GetMessage('ступенчатая / факультативная автоматическая коробка'),
		);
		 */
	}
	//-----------------------------------------------------------------------------------------------
	// for use in other module
	public function	Get_aTypeWheel() {
		// use count from 1! in smarty tlp check 0 and not set equal
	 	return array(
	 		1 => Language::GetMessage('Leftside'),
	 		2 => Language::GetMessage('Rightside'),
	 	);
	}
	//-----------------------------------------------------------------------------------------------
	// for use in other module
	public function	Get_Months() {	
		return array(
			'January'=>Language::GetMessage('January'),
			'February'=>Language::GetMessage('February'),
			'March'=>Language::GetMessage('March'),
			'April'=>Language::GetMessage('April'),
			'May'=>Language::GetMessage('May'),
			'June'=>Language::GetMessage('June'),
			'July'=>Language::GetMessage('July'),
			'August'=>Language::GetMessage('August'),
			'September'=>Language::GetMessage('September'),
			'October'=>Language::GetMessage('October'),
			'November'=>Language::GetMessage('November'),
			'December'=>Language::GetMessage('December'),
		);
	}
	//-----------------------------------------------------------------------------------------------
	public function GetMarka() {
		return Db::GetAssoc("Assoc/Cat",array(
		'visible'=>1,
		'is_vin_brand'=>1,
		));
	}
	//-----------------------------------------------------------------------------------------------
	public function ChangeSelect() {
		if (Base::$aRequest['data']['id_make']) {
			//opti
//			Base::$tpl->assign('aModel',array(""=>Language::getMessage('choose model'))+Db::GetAssoc("Assoc/OptiCatModel",array(
//			"id_make"=>Base::$aRequest['data']['id_make'],
//			"check_visible"=>"1",
//			"sOrder"=>" order by name "
//			)));
			
			Base::$tpl->assign('aModel',array(""=>Language::getMessage('choose model'))+TecdocDb::GetModelAssoc(
			array(
			"id_make"=>Base::$aRequest['data']['id_make'],
			"check_visible"=>"1",
			"sOrder"=>" order by name "
			)));
	
			Base::$oResponse->addAssign('id_model','outerHTML',
			Base::$tpl->fetch("vin_request/customer/select_model.tpl"));	
		}
	}
	//-----------------------------------------------------------------------------------------------
	public function ChangeSelectOwnAuto() {
		if (Base::$aRequest['id_own_auto']) {
			$aData = Db::GetRow("Select * from user_auto where id=".Base::$aRequest['id_own_auto']);

			Base::$oResponse->addAssign('vin','outerHTML',
				"<input type=text id=vin name=vin value=".$aData['vin']." maxlength=17>");

			Base::$oResponse->addScript("$('#marka option[value=".$aData['id_make']."]').prop('selected', true);");
			
			/*Base::$tpl->assign('aModel',array(""=>Language::getMessage('choose model'))+Db::GetAssoc("Assoc/OptiCatModel",array(
			"id_make"=>$aData['id_make'],
			"check_visible"=>"1",
			"sOrder"=>" order by name ")));
			*/
			$aModelAsoc=TecdocDb::GetModelAssoc(
					array(
						"id_make"=>$aData['id_make'],
						//"id_tof"=>Base::$aRequest['data']['id_tof'],
						"sOrder"=>" order by name"
					)
			);
			Base::$tpl->assign('aModel',array(""=>Language::getMessage('choose model'))+$aModelAsoc);
			
			Base::$oResponse->addAssign('id_model','outerHTML',
			Base::$tpl->fetch("vin_request/customer/select_model.tpl"));
			
			Base::$oResponse->addScript("$('#id_model option[value=\"".$aData['id_model']."\"]').prop('selected', true);");
			Base::$oResponse->addScript("$('#wheel option[value=\"".$aData['wheel']."\"]').prop('selected', true);");
			Base::$oResponse->addScript("$('#engine').val(\"".($aData['engine']?$aData['engine']:'')."\");");
			Base::$oResponse->addScript("$('#country_producer').val(\"".($aData['country_producer']?$aData['country_producer']:'')."\");");
			Base::$oResponse->addScript("$('[name=Month] option[value=\"".($aData['month']?$aData['month']:"January")."\"]').attr('selected','selected');");
			Base::$oResponse->addScript("$('[name=Year] option[value=\"".($aData['year']?$aData['year']:"1959")."\"]').attr('selected','selected');");
			Base::$oResponse->addScript("$('#volume_auto').val(\"".($aData['volume']?$aData['volume']:'')."\");");
			Base::$oResponse->addScript("$('#body_auto option[value=\"".$aData['body']."\"]').prop('selected', true);");
			Base::$oResponse->addScript("$('#kpp option[value=\"".$aData['kpp']."\"]').prop('selected', true);");
			if ($aData['is_abs'])
				Base::$oResponse->addScript("$('#add_abs').prop('checked', true)");
			else 
				Base::$oResponse->addScript("$('#add_abs').prop('checked', false)");
			if ($aData['is_hyd_weel'])
				Base::$oResponse->addScript("$('#add_hyd').prop('checked', true)");
			else 
				Base::$oResponse->addScript("$('#add_hyd').prop('checked', false)");
			if ($aData['is_conditioner'])
				Base::$oResponse->addScript("$('#add_cond').prop('checked', true)");
			else 
				Base::$oResponse->addScript("$('#add_cond').prop('checked', false)");
			Base::$oResponse->addScript("$('#customer_comment').val(\"".($aData['customer_comment']?$aData['customer_comment']:'')."\");");
		}
	}
}
?>