<?php
/**
 * @author Jefersson Nathan <jefersson@swapi.com.br>
 * @copyright Swapi Agência Digital <www.swapi.com.br>
 * @date 06/08/2012
 * @package Wordpress (WP e-commence)
 * @since 3.7.6
 * @subpackage wpsc-merchants
 *
 * Gateway para banco bradesco.
 * Formas de pagamento e redirecionamento para o pagamento
 *
 * ---------------------------------------------------------------
 * @obs: Não mecher na 'api_version' pois pode ocasionar problemas
 * e instabilidade nas compras usando o gateway bradesco
 * ---------------------------------------------------------------
 *
 * Configuração!!!
 *
 * form => Chama a função 'form_bradesco' que será mostrada
 * na área de administração e configuração do gateway.
 *
 * submit_function => função usada para salvar os dados das
 * configurações no banco de dados.
 *
 * payment_type => tipo de pagamento a ser setado no banco de dados
 *
 * internalname => Nome da class no html
 *
 */
$nzshpcrt_gateways[$num] = array(
	'name' => 'Bradesco Gateway',
	'api_version' => 2.0,
	'image' => WPSC_URL . '/images/bradesco.png',
	'class_name' => 'wpsc_merchant_bradesco', 
	'has_recurring_billing' => true,
	'wp_admin_cannot_cancel' => true,
	'display_name' => 'Bradesco Gateway',
	'requirements' => array(
		'php_version' => 4.3,
		'extra_modules' => array()
	),
	'internalname' => 'wpsc_merchant_bradesco', 
	'form' => 'form_bradesco',
	'submit_function' => 'submit_bradesco',
	'payment_type' => 'bradesco',
);



/**
	* WP eCommerce Bradesco Merchant Class
	*
	* Base de toda a classe
	*
	* @package wp-e-commerce
	* @since 3.7.6
	* @subpackage wpsc-merchants
*/
class wpsc_merchant_bradesco extends wpsc_merchant {
  var $name = 'Pagamento Bradesco';
  var $paypal_ipn_values = array();

	
	/**
	* construct value array method, converts the data gathered by the base class code to something acceptable to the gateway
	* @access private
	* @param boolean $aggregate Whether to aggregate the cart data or not. Defaults to false.
	* @return array $paypal_vars The paypal vars
	*/
	function _construct_value_array($aggregate = false) {
		global $wpdb;
		$paypal_vars = array();
		$add_tax = ! wpsc_tax_isincluded();

		// Store settings to be sent to paypal
		$paypal_vars += array(
			'business' => get_option('bradesco_loja'),
			'return' => add_query_arg('sessionid', $this->cart_data['session_id'], $this->cart_data['transaction_results_url']),
			'cancel_return' => $this->cart_data['transaction_results_url'],
			'rm' => '2',
			'currency_code' => $this->get_paypal_currency_code(),
			'lc' => $this->cart_data['store_currency'],
			'bn' => $this->cart_data['software_name'],

			'no_note' => '1',
			'charset' => 'utf-8',
		);

		// IPN data
		if (get_option('estadoloja') == 1) {
			$notify_url = $this->cart_data['notification_url'];
			$notify_url = add_query_arg('gateway', 'wpsc_merchant_bradesco', $notify_url);
			$notify_url = apply_filters('wpsc_paypal_standard_notify_url', $notify_url);
			$paypal_vars += array(
				'notify_url' => $notify_url,
			);
		}

		// Shipping
		if ((bool) get_option('paypal_ship')) {
			$paypal_vars += array(
				'address_override' => '1',
				'no_shipping' => '0',
			);
		}

		// Customer details
		$paypal_vars += array(
			'email' => $this->cart_data['email_address'],
			'first_name' => $this->cart_data['shipping_address']['first_name'],
			'last_name' => $this->cart_data['shipping_address']['last_name'],
			'address1' => $this->cart_data['shipping_address']['address'],
			'city' => $this->cart_data['shipping_address']['city'],
			'country' => $this->cart_data['shipping_address']['country'],
			'zip' => $this->cart_data['shipping_address']['post_code'],
			'state' => $this->cart_data['shipping_address']['state'],
		);

		if ( $paypal_vars['country'] == 'UK' ) {
			$paypal_vars['country'] = 'GB';
		}

		// Order settings to be sent to paypal
		$paypal_vars += array(
			'invoice' => $this->cart_data['session_id']
		);

		// Two cases:
		// - We're dealing with a subscription
		// - We're dealing with a normal cart
		if ($this->cart_data['is_subscription']) {
			$paypal_vars += array(
				'cmd'=> '_xclick-subscriptions',
			);

			$reprocessed_cart_data['shopping_cart'] = array(
				'is_used' => false,
				'price' => 0,
				'length' => 1,
				'unit' => 'd',
				'times_to_rebill' => 1,
			);

			$reprocessed_cart_data['subscription'] = array(
				'is_used' => false,
				'price' => 0,
				'length' => 1,
				'unit' => 'D',
				'times_to_rebill' => 1,
			);

			foreach ($this->cart_items as $cart_row) {
				if ($cart_row['is_recurring']) {
					$reprocessed_cart_data['subscription']['is_used'] = true;
					$reprocessed_cart_data['subscription']['price'] = $this->convert( $cart_row['price'] );
					$reprocessed_cart_data['subscription']['length'] = $cart_row['recurring_data']['rebill_interval']['length'];
					$reprocessed_cart_data['subscription']['unit'] = strtoupper($cart_row['recurring_data']['rebill_interval']['unit']);
					$reprocessed_cart_data['subscription']['times_to_rebill'] = $cart_row['recurring_data']['times_to_rebill'];
				} else {
					$item_cost = ($cart_row['price'] + $cart_row['shipping'] + $cart_row['tax']) * $cart_row['quantity'];

					if ($item_cost > 0) {
						$reprocessed_cart_data['shopping_cart']['price'] += $item_cost;
						$reprocessed_cart_data['shopping_cart']['is_used'] = true;
					}
				}

				$paypal_vars += array(
					'item_name' => __('Your Subscription', 'wpsc'),
					// I fail to see the point of sending a subscription to paypal as a subscription
					// if it does not recur, if (src == 0) then (this == underfeatured waste of time)
					'src' => '1'
				);

				// This can be false, we don't need to have additional items in the cart/
				if ($reprocessed_cart_data['shopping_cart']['is_used']) {
					$paypal_vars += array(
						"a1" => $this->convert($reprocessed_cart_data['shopping_cart']['price']),
						"p1" => $reprocessed_cart_data['shopping_cart']['length'],
						"t1" => $reprocessed_cart_data['shopping_cart']['unit'],
					);
				}

				// We need at least one subscription product,
				// If this is not true, something is rather wrong.
				if ($reprocessed_cart_data['subscription']['is_used']) {
					$paypal_vars += array(
						"a3" => $this->convert($reprocessed_cart_data['subscription']['price']),
						"p3" => $reprocessed_cart_data['subscription']['length'],
						"t3" => $reprocessed_cart_data['subscription']['unit'],
					);

					// If the srt value for the number of times to rebill is not greater than 1,
					// paypal won't accept the transaction.
					if ($reprocessed_cart_data['subscription']['times_to_rebill'] > 1) {
						$paypal_vars += array(
							'srt' => $reprocessed_cart_data['subscription']['times_to_rebill'],
						);
					}
				}
			} // end foreach cart item
		} else {
			$paypal_vars += array(
				'upload' => '1',
				'cmd' => '_ext-enter',
				'redirect_cmd' => '_cart',
			);

			$free_shipping = false;
			if ( isset( $_SESSION['coupon_numbers'] ) ) {
				$coupon = new wpsc_coupons( $_SESSION['coupon_numbers'] );
				$free_shipping = $coupon->is_percentage == '2';
			}

			if ( $this->cart_data['has_discounts'] && $free_shipping )
				$handling = 0;
			else
				$handling = $this->cart_data['base_shipping'];

			$tax_total = 0;
			if ( $add_tax )
				$tax_total = $this->cart_data['cart_tax'];

			// Set base shipping
			$paypal_vars += array(
				'handling_cart' => $this->convert( $handling )
			);

			// Stick the cart item values together here
			$i = 1;

			if (!$aggregate) {
				foreach ($this->cart_items as $cart_row) {
					$paypal_vars += array(
						"item_name_$i" => $cart_row['name'],
						"amount_$i" => $this->convert($cart_row['price']),
						"quantity_$i" => $cart_row['quantity'],
						"item_number_$i" => $cart_row['product_id'],
						// additional shipping for the the (first item / total of the items)
						"shipping_$i" => $this->convert($cart_row['shipping']/ $cart_row['quantity'] ),
						// additional shipping beyond the first item
						"shipping2_$i" => $this->convert($cart_row['shipping']/ $cart_row['quantity'] ),
						"handling_$i" => '',
					);
					if ( $add_tax && ! empty( $cart_row['tax'] ) )
						$tax_total += $cart_row['tax'];
					++$i;
				}
				if ( $this->cart_data['has_discounts'] && ! $free_shipping )
					$paypal_vars['discount_amount_cart'] = $this->convert( $this->cart_data['cart_discount_value'] );
			} else {
				$paypal_vars['item_name_'.$i] = "Your Shopping Cart";
				$paypal_vars['amount_'.$i] = $this->convert( $this->cart_data['total_price'] ) - $this->convert( $this->cart_data['base_shipping'] );
				$paypal_vars['quantity_'.$i] = 1;
				$paypal_vars['shipping_'.$i] = 0;
				$paypal_vars['shipping2_'.$i] = 0;
				$paypal_vars['handling_'.$i] = 0;
			}

			$paypal_vars['tax_cart'] = $this->convert( $tax_total );
		}
		return apply_filters( 'wpsc_paypal_standard_post_data', $paypal_vars );	

	}
	

	


	/**
	* Envia para o getway adequado
	* a escolha de pagamento do cliente
	*
	* @access public
	*/
	function submit() {
		/**
		 * No $nomeLoja fica o MerchantID da loja bradesco
		 * que pode ser configurado no ambiente de administração
		 * de loja do wp-e-commence
		 */
		$nomeLoja = get_option('bradesco_loja');
				
		/**
		 *	Monta a url para link de compra
		 *
		 * Exemplo de url : http://mupteste.comercioeletronico.com.br/sepsapplet/xxxx/prepara_pagto.asp?merchantid=xxxx&orderid=zzzz
		 * xxxx => Numero da loja 
		 * zzzz => ID da compra
		 */

		$urlReirect = (int) $_REQUEST['TipoDePagamentoSELECT'];
		
		/**
		 * Redirecionamento de acordo com o metodo
		 * de pagamento escolhido pelo cliente
		 */
		 switch($urlReirect)
		 {
			#Pagamento Facil -> Url
			case 1:
				$redirect = get_option('bradesco_url')."/sepsapplet/".$nomeLoja."/prepara_pagto.asp?merchantid=".$nomeLoja."&orderid=".$this->cart_data['session_id'];
			break;
			
			#Boleto Bancário -> Url
			case 2:
				$redirect = get_option('bradesco_url')."/sepsBoleto/".$nomeLoja."/prepara_pagto.asp?merchantid=".$nomeLoja."&orderid=".$this->cart_data['session_id'];
			break;
			
			#Boleto Retorno -> Url
			case 3:
				$redirect = get_option('bradesco_url')."/sepsBoletoRet/".$nomeLoja."/prepara_pagto.asp?merchantid=".$nomeLoja."&orderid=".$this->cart_data['session_id'];
			break;
			
			#Trasnferencia -> Url
			case 4:
				$redirect = get_option('bradesco_url')."/sepsTransfer/".$nomeLoja."/prepara_pagto.asp?merchantid=".$nomeLoja."&orderid=".$this->cart_data['session_id'];
			break;
			
			#Financiamento -> Url
			case 5:
				$redirect = get_option('bradesco_url')."/sepsFinanciamento/".$nomeLoja."/prepara_pagto.asp?merchantid=".$nomeLoja."&orderid=".$this->cart_data['session_id'];
			break;
			
		 
		 
		 }
		
		// URLs up to 2083 characters long are short enough for an HTTP GET in all browsers.
		// Longer URLs require us to send aggregate cart data to PayPal short of losing data.
		// An exception is made for recurring transactions, since there isn't much we can do.
		if (strlen($redirect) > 2083 && !$this->cart_data['is_subscription']) {
			$name_value_pairs = array();
			foreach($this->_construct_value_array(true) as $key => $value) {
				$name_value_pairs[]= $key . '=' . urlencode($value);
			}
			$gateway_values =  implode('&', $name_value_pairs);

			$redirect = get_option('bradesco_url')."?".$gateway_values;
		}

		if (defined('WPSC_ADD_DEBUG_PAGE') && WPSC_ADD_DEBUG_PAGE) {
			echo "<a href='".esc_url($redirect)."'>Test the URL here</a>";
			echo "<pre>".print_r($this->collected_gateway_data,true)."</pre>";
			exit();
		} else {
			wp_redirect($redirect);
			exit();
		}
	}

	/**
	* process_gateway_notification method, receives data from the payment gateway
	* @access public
	*/
	function process_gateway_notification() {
		$status = false;
		switch ( strtolower( $this->paypal_ipn_values['payment_status'] ) ) {
			case 'pending':
				$status = 2;
				break;
			case 'completed':
				$status = 3;
				break;
			case 'denied':
				$status = 6;
				break;
		}

		do_action( 'wpsc_paypal_standard_ipn', $this->paypal_ipn_values, $this );
		$paypal_email = strtolower( get_option( 'bradesco_loja' ) );

	  // Compare the received store owner email address to the set one
		if( strtolower( $this->paypal_ipn_values['receiver_email'] ) == $paypal_email || strtolower( $this->paypal_ipn_values['business'] ) == $paypal_email ) {
			switch($this->paypal_ipn_values['txn_type']) {
				case 'cart':
				case 'express_checkout':
					if ( $status )
						$this->set_transaction_details( $this->paypal_ipn_values['txn_id'], $status );
					if ( in_array( $status, array( 2, 3 ) ) )
						transaction_results($this->cart_data['session_id'],false);
				break;

				case 'subscr_signup':
				case 'subscr_payment':
					if ( in_array( $status, array( 2, 3 ) ) ) {
						$this->set_transaction_details( $this->paypal_ipn_values['subscr_id'], $status );
						transaction_results($this->cart_data['session_id'],false);
					}
					foreach($this->cart_items as $cart_row) {
						if($cart_row['is_recurring'] == true) {
							do_action('wpsc_activate_subscription', $cart_row['cart_item_id'], $this->paypal_ipn_values['subscr_id']);
						}
					}
				break;

				case 'subscr_cancel':
				case 'subscr_eot':
				case 'subscr_failed':
					foreach($this->cart_items as $cart_row) {
						$altered_count = 0;
						if((bool)$cart_row['is_recurring'] == true) {
							$altered_count++;
							wpsc_update_cartmeta($cart_row['cart_item_id'], 'is_subscribed', 0);
						}
					}
				break;

				default:
				break;
			}
		}

		$message = "
		{$this->paypal_ipn_values['receiver_email']} => ".get_option('bradesco_loja')."
		{$this->paypal_ipn_values['txn_type']}
		{$this->paypal_ipn_values['mc_gross']} => {$this->cart_data['total_price']}
		{$this->paypal_ipn_values['txn_id']}

		".print_r($this->cart_items, true)."
		{$altered_count}
		";
	}



	function format_price($price, $paypal_currency_code = null) {
		if (!isset($paypal_currency_code)) {
			$paypal_currency_code = get_option('paypal_curcode');
		}
		switch($paypal_currency_code) {
			case "JPY":
			$decimal_places = 0;
			break;

			case "HUF":
			$decimal_places = 0;

			default:
			$decimal_places = 2;
			break;
		}
		$price = number_format(sprintf("%01.2f",$price),$decimal_places,'.','');
		return $price;
	}
}


/**
 * submit_bradesco() function.
 *
 * Responsavel por atualizar os dados 
 * e configuraçãos do plugin dentro do painel
 * do wordpress
 * 
 * @access public
 * @return void
 */
function submit_bradesco(){
  if(isset($_POST['bradesco_loja'])) {
    update_option('bradesco_loja', $_POST['bradesco_loja']);
	}
  if(isset($_POST['estadoloja'])) {
    update_option('estadoloja', (int)$_POST['estadoloja']);
	}
	
  if($_POST['estadoloja'] == 1)
  {
	 update_option('bradesco_url', "http://mupteste.comercioeletronico.com.br");
  }
  else{
	update_option('bradesco_url', "http://mup.comercioeletronico.com.br");
  }
  if(isset($_POST['paypal_curcode'])) {
    update_option('paypal_curcode', $_POST['paypal_curcode']);
	}

  if(isset($_POST['paypal_curcode'])) {
    update_option('paypal_curcode', $_POST['paypal_curcode']);
	}
	
	/**
	 * Formas de pagamento
	 * Atualiza a situação no banco de dados
	 */
	if($pagFacilBanco = isset($_POST['pagfacil']) ? $_POST['pagfacil'] : "off") {
		update_option('pagfacil', $pagFacilBanco);
	}
	if($boletobancBanco = isset($_POST['boletobanc']) ? $_POST['boletobanc'] : "off") {
		update_option('boletobanc', $boletobancBanco);
	}
	if($boletoretBanco = isset($_POST['boletoret']) ? $_POST['boletoret'] : "off") {
		update_option('boletoret', $boletoretBanco);
	}
	if($transferBanco = isset($_POST['transfer']) ? $_POST['transfer'] : "off") {
		update_option('transfer', $transferBanco);
	}
	if($financiamentoBanco = isset($_POST['financiamento']) ? $_POST['financiamento'] :  "off") {
		update_option('financiamento', $financiamentoBanco);
	}
	
	
	/**
	 * Armazenamento de configurações
	 * dos dados do lojista, que pode ser configurado
	 * no painel da administração.
	 * 
	 */
	if($_POST['cedente_nome']) {
		update_option('cedente_nome', $_POST['cedente_nome']);
	}
	if($_POST['banco_nome']) {
		update_option('banco_nome', $_POST['banco_nome']);
	}
	if($_POST['agencia_nome']) {
		update_option('agencia_nome', $_POST['agencia_nome']);
	}
	if($_POST['conta_nome']) {
		update_option('conta_nome', $_POST['conta_nome']);
	}
	if($_POST['assinatura_nome']) {
		update_option('assinatura_nome', $_POST['assinatura_nome']);
	}
	if($_POST['vencimento_nome']) {
		update_option('vencimento_nome', $_POST['vencimento_nome']);
	}
	 
	 
	 
	 
	 
	 
    if (!isset($_POST['paypal_form'])) $_POST['paypal_form'] = array();
		foreach((array)$_POST['paypal_form'] as $form => $value) {
			update_option(('paypal_form_'.$form), $value);
	}

  return true;
}



/**
 * Esta função apresenta uma interface de configuração do getway
 * Bradesco na área de administração, para configurações básicas
 * da loja
 */
function form_bradesco() {
  global $wpdb, $wpsc_gateways;

  /**
   * Pega a situação da loja, se ela está funcionando
   * e aberta para o publico. ou se está em fase de teste
   * e seleciona a respectiva $url para cada situação
   */
  $estadoDaLoja = (int) get_option( 'estadoloja' );
  if($estadoDaLoja == 1)
  {
	$url = "mupteste.comercioeletronico.com.br";
  }
  else
  {
	$url = "mup.comercioeletronico.com.br";
  }
  
  $output = "
  <tr>
      <td>" . __( 'ID da Loja:', 'wpsc' ) . "
      </td>
      <td>
      <input type='text' size='40' value='".get_option('bradesco_loja')."' name='bradesco_loja' />
      </td>
  </tr>
  <tr>
  	<td></td>
  	<td colspan='1'>
  	<span  class='wpscsmall description'>
  	" . __( 'Coloque aqui o merchantid da sua loja','wpsc' ) . "
  	</span>
  	</td>
  </tr>";

	/**
	 * Pega as opções do estado da loja
	 * Ex: Teste, Aberta!
	 */
	$paypal_ipn = get_option('estadoloja');
	$paypal_ipn1 = "";
	$paypal_ipn2 = "";
	switch($paypal_ipn) {
		case 0:
		$paypal_ipn2 = "checked ='checked'";
		break;

		case 1:
		$paypal_ipn1 = "checked ='checked'";
		break;
	}
	
	$address_override = get_option('address_override');
	$address_override1 = "";
	$address_override2 = "";
	switch($address_override) {
		case 1:
		$address_override1 = "checked ='checked'";
		break;

		case 0:
		default:
		$address_override2 = "checked ='checked'";
		break;
	}
	$output .= "
   <tr>
     <td>Estado da loja :
     </td>
     <td>
       <input type='radio' value=\"1\" name='estadoloja' id='estado1' ".$paypal_ipn1." /> <label for='paypal_ipn1'>".__('Em teste', 'wpsc')."</label> &nbsp;
       <input type='radio' value=\"0\" name='estadoloja' id='estado2' ".$paypal_ipn2." /> <label for='paypal_ipn2'>".__('Funcionando', 'wpsc')."</label>
     </td>
  </tr>
  <tr>
  	<td colspan='2'>
  	<span  class='wpscsmall description'>
  	O estado da loja indicará se a loja está em fasse de testes ou se já está aberta ao público.
  	</span>
  	</td>
  </tr>";
  
  
  	/**
	 * Pegando opções de formas de pagamento
	 */
	 $pagfacil = get_option('pagfacil');
	 $pagfacil_1 = "";
	 
	 switch($pagfacil){
	 
		case 'on':
			$pagfacil_1 = "checked ='checked'";
		break;
		
		case 'off':
		default:
			$pagfacil_1 = "";
		break;
	 }
	 
	 
	 $boletobanc = get_option('boletobanc');
	 $pboletobanc_1 = "";
	 
	 switch($boletobanc)
	 {
	 
		case 'on':
			$boletobanc_1 = "checked ='checked'";
		break;
		
		case 'off':
		default:
			$boletobanc_1 = "";
		break;
	 }
	 
	 $boletoret = get_option('boletoret');
	 $boletoret_1 = "";
	 
	 switch($boletoret)
	 {
	 
		case 'on':
			$boletoret_1 = "checked ='checked'";
		break;
		
		case 'off':
		default:
			$boletoret_1 = "";
		break;
	 } 
	 
	 $transfer = get_option('transfer');
	 $transfer_1 = "";
	 
	 switch($transfer)
	 {
	 
		case 'on':
			$transfer_1 = "checked ='checked'";
		break;
		
		case 'off':
		default:
			$transfer_1 = "";
		break;
	 }
	 
	  
	 $financiamento = get_option('financiamento');
	 $financiamento_1 = "";
	 
	 switch($financiamento)
	 {
	 
		case 'on':
			$financiamento_1 = "checked ='checked'";
		break;
		
		case 'off':
		default:
			$financiamento_1 = "";
		break;
	 }
	 
	 
  $output .= "
   <tr>
     <td>Formas de pagamento :
     </td>
     <td>
       <input type='checkbox'  name='pagfacil' id='estado1' ".$pagfacil_1."/> <label for='paypal_ipn1'>".__('Pagamento Fácil', 'wpsc')."</label><br />
       <input type='checkbox'  name='boletobanc' id='estado1'   ".$boletobanc_1."/> <label for='paypal_ipn1'>".__('Boleto bancário', 'wpsc')."</label><br />
       <!--<input type='checkbox' name='boletoret' id='estado1' ".$boletoret_1." /> <label for='paypal_ipn1'>".__('Boleto Retorno', 'wpsc')."</label><br />
       <input type='checkbox'  name='transfer' id='estado1'  ".$transfer_1."/> <label for='paypal_ipn1'>".__('Transferencia', 'wpsc')."</label><br />
       <input type='checkbox' name='financiamento' id='estado2' ".$financiamento_1." /> <label for='paypal_ipn2'>".__('Financiamento', 'wpsc')."</label>-->
     </td>
  </tr>
  <tr>
  	<td colspan='2'>
  	<span  class='wpscsmall description'>
  	Escolha acima as formas de pagamentos aceitas em sua loja.
  	</span>
  	</td>
  </tr>";
  
  /**
   *
   * HTML para as configurações do banco e conta
   * para geração de boleto bancário e outros afins!
   *
   */
  $output .= "
  <tr>
     <td colspan='2'>
	 <h3>Configurações de conta</h3>
     </td>
  </tr>
  <tr>
  	<td colspan='2'>
  	<span  class='wpscsmall description'>
  	Escolha acima as formas de pagamentos aceitas em sua loja.
  	</span>
  	</td>
  </tr>
  <tr>
	<td>
		Cedente:
	</td>
	<td>
		<input type='text' name='cedente_nome' value='".get_option('cedente_nome')."' />
	</td>
  </tr>

  <tr>
	<td>
		Banco:
	</td>
	<td>
		<input type='text' name='banco_nome' value='".get_option('banco_nome')."'/>
	</td>
  </tr>
  
  <tr>
	<td>
		Agência:
	</td>
	<td>
		<input type='text' name='agencia_nome' value='".get_option('agencia_nome')."'/>
	</td>
  </tr>
  
  <tr>
	<td>
		Conta:
	</td>
	<td>
		<input type='text' name='conta_nome' value='".get_option('conta_nome')."' />
	</td>
  </tr>
  
  <tr>
	<td>
		Assinatura:
	</td>
	<td>
		<input type='text' name='assinatura_nome' value='".get_option('assinatura_nome')."' />
	</td>
  </tr>
  
  <tr>
	<td>
		Dias para vencimento:
	</td>
	<td>
		<input type='text' name='vencimento_nome' value='".get_option('vencimento_nome')."' />
	</td>
  </tr>
   <tr>
  	<td colspan='2'>
  	<span  class='wpscsmall description'>
  	Coloque acima uma quandtidade de dias para o vencimento do boleto bancario. Exemplo: 5
  	</span>
  	</td>
  </tr>
     <tr>
  	<td colspan='2'>
  	<a href='https://mup.comercioeletronico.com.br/sepsmanager/senha.asp?loja=".get_option('bradesco_loja')."'><span  class='wpscsmall description'>
  	Visitar administração Bradesco
  	</span></a>
  	</td>
  </tr>
  ";



  return $output;
}

/**
 * Opções para compras dos clientes
 */
if ( in_array( 'wpsc_merchant_bradesco', (array)get_option( 'custom_gateway_options' ) ) ) {

	$curryear = date( 'Y' );
	
	$pagfacil_yeep = (get_option('pagfacil') == 'on') ? "<option value='1'>Pagamento Fácil</option>" : "";
	$boletobanc_yeep = (get_option('boletobanc') == 'on') ? "<option value='2'>Boleto Bancário</option>" : "";
	$boletoret_yeep = (get_option('boletoret') == 'on') ? "<option value='3'>Boleto Retorno</option>" : "";
	$transfer_yeep = (get_option('transfer') == 'on') ? "<option value='4'>Transferência</option>" : "";
	$financiamento_yeep = (get_option('financiamento') == 'on') ? "<option value='5'>Financiamento</option>" : "";
	

	$gateway_checkout_form_fields[$nzshpcrt_gateways[$num]['internalname']] = "
	<tr>
		<td class='formas_pagamentos'>" . __( 'Forma de pagamento *', 'wpsc' ) . "</td>
		<td>
		<select name='TipoDePagamentoSELECT' id='TipoDePagamentoSELECT'>
		".$pagfacil_yeep."\n
		".$boletobanc_yeep."\n
		".$boletoret_yeep."\n
		".$transfer_yeep."\n
		".$financiamento_yeep."\n
		</select>
		</td>
	</tr>
	
";

}
?>
