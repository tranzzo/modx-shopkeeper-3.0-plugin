<?php
/*
* Платежный модуль Интеркасса (сниппет)
* Совместим с MODX REVO 2.5.0 (версии 2.х.х тоже должны быть совместимы, возможно с некоторыми поправками в коде)!
* Модуль требует наличие плагина ShopKeeper 3.0, 2.0 тоже будет работать, только нужно сменить переменные и вызовы $modx функций!
* version 2.0
*/

if (isset($_REQUEST['payment']) && $_REQUEST['payment'] == 'tranzzo') {
	$payment_form = MODX_SITE_URL . $modx -> makeUrl( $modx->getOption('page_paymentForm', $scriptProperties, null) );
	$modx->sendRedirect($payment_form);
}



if (isset($scriptProperties['action'])) {
	require_once $modx->getOption('core_path') . "components/payment_tranzzo/TranzzoApi.php";

	$tranzzo_api = new TranzzoApi(trim($scriptProperties['TRANZZO_POS_ID']), trim($scriptProperties['TRANZZO_API_KEY']),trim($scriptProperties['TRANZZO_API_SECRET']), trim($scriptProperties['TRANZZO_ENDPOINTS_KEY']));


	switch ($scriptProperties['action']) {

		case 'result':
		if (empty($_REQUEST)){exit;}
		$order_id  = intval($_REQUEST['order_id']);
		$status  = $_REQUEST['status'];
		
		$order = $modx->getObject('shk_order', $order_id);
		
        $tpl_dir = $modx->getOption('core_path') . 'components/payment_tranzzo/template/';			
		$smarty = $modx->getService('smarty','smarty.modSmarty');
		$smarty->caching = false;		
		$smarty->setTemplateDir( $tpl_dir );
		$smarty->assign('status', $status);
		$smarty->assign('order', $order);
		return $smarty->fetch('result.tpl');		
		
			break;
		case 'callback':
			$modelpath = $modx->getOption('core_path') . 'components/shopkeeper3/model/';
			$modx->addPackage( 'shopkeeper3', $modelpath );
	        $signature = $_REQUEST['signature'];
            $data = $_REQUEST['data'];
			
			$data_response = TranzzoApi::parseDataResponse($data);
				$method_response = $data_response[TranzzoApi::P_REQ_METHOD];
		if ($method_response == TranzzoApi::P_METHOD_AUTH || $method_response == TranzzoApi::P_METHOD_PURCHASE) {
                $order_id = (int)$data_response[TranzzoApi::P_RES_PROV_ORDER];
                $tranzzo_order_id = (int)$data_response[TranzzoApi::P_RES_ORDER];
            } else {
                $order_id = (int)$data_response[TranzzoApi::P_RES_ORDER];
            }
			

			$order = $modx->getObject('shk_order', $order_id);

            if (empty($order)) die("FAIL");
		         $status = $data_response[TranzzoApi::P_RES_STATUS];
				 $code = $data_response[TranzzoApi::P_RES_RESP_CODE];
		         $amount_payment = TranzzoApi::amountToDouble($data_response[TranzzoApi::P_RES_AMOUNT]);
				 
		   // $amount_payment == $invoice->get_amount()
			  if ($tranzzo_api->validateSignature($data, $signature) ){
				if ($status === TranzzoApi::P_TRZ_ST_SUCCESS && $amount_payment >=$order->price  ) {
					$modx->updateCollection('shk_order', array('status' => 6), array('id' => $order_id));
					die("SUCCESS");
                }elseif($status === TranzzoApi::P_TRZ_ST_CANCEL){
					$modx->updateCollection('shk_order', array('status' => 5), array('id' => $order_id));
					die("CANCEL");
				}elseif($status === TranzzoApi::P_TRZ_ST_PENDING && $invoice){
					$modx->updateCollection('shk_order', array('status' => 2), array('id' => $order_id));
					die("PENDING");
				} else {
                    die("FAIL");
                }
			} else {
				die("FAIL");
			}
			break;
		case 'payment':

			$modelpath = $modx->getOption('core_path') . 'components/shopkeeper3/model/';
			$modx->addPackage( 'shopkeeper3', $modelpath);
			

			$values['orderId'] = $_SESSION['shk_lastOrder']['id'];
			$values['orderPrice'] = $_SESSION['shk_lastOrder']['price'];

			if (!$values['orderId']) {
				return "Заказ не найден.";
			}
		      $order = $modx->getObject('shk_order', $values['orderId']);
		       
			if ($order->payment != 'tranzzo'){return;} 
			


			if($order->status == 6 OR empty($order)) return;
			
			  $total = number_format(sprintf("%01.2f", $values['orderPrice']), 1, '.', '');
			  $currency = trim($scriptProperties['currency'])? trim($scriptProperties['currency']) : '';
			  
			  if($currency =='RUR' OR empty($currency)){
				  $currency = 'RUB';
			  }
			  
/* 			  print_r($tranzzo_api);
			  print_r($scriptProperties['page_callback']);
			  print_r(MODX_SITE_URL . $modx->makeUrl(trim($scriptProperties['page_callback'])));exit(); */
			  
		      $tranzzo_api->setServerUrl(MODX_SITE_URL . $modx->makeUrl(trim($scriptProperties['page_callback'])));
		      $tranzzo_api->setResultUrl(MODX_SITE_URL . $modx -> makeUrl(trim($scriptProperties['page_result'])));
		      $tranzzo_api->setOrderId($values['orderId']);
		      $tranzzo_api->setAmount($total);
		      $tranzzo_api->setCurrency($currency);
		      $tranzzo_api->setDescription("Payment order " . $values['orderId']);
		      $form = array();
		      //print_r($tranzzo_api);
              $response = $tranzzo_api->createPaymentHosted(0);
		      //print_r($response);
		      //$this->wrlog($response);
	          $tr_action = '';
		      if (!empty($response['redirect_url'])) {
		      	$tr_action = $response['redirect_url'];
              }else{
		      	return;
		      }	
			
			$tpl_dir = $modx->getOption('core_path') . 'components/payment_tranzzo/template/';			
			$smarty = $modx->getService('smarty','smarty.modSmarty');
			$smarty->caching = false;
			
			$smarty->setTemplateDir( $tpl_dir );
			$smarty->assign('tr_action', $tr_action);
			return $smarty->fetch('payment.tpl');

			break;
		default:
			break;
	}
}


/* Логин: admin
Пароль: a5Y4cZya */