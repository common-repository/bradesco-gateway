<?php
/**
 * @author Jefersson Nathan de Oliveira Chaves <jefersson@swapi.com.br>
 * @copyright SWAPI Agência Digital
 *
 *	Recupera os dados da compra indentificado pelo numOrder
 *	Fornece os dados do produto para o Banco Bradesco realizar
 *	a compra com o cliente
 */
 
	/**
	 * O $numeroCompra é responsavel pela referencia aos respectivos
	 * produtos correspondente a essa posição na base de dados da loja
	 * É usado para pegar dados destes produtos
	 */
	$numeroCompra = (int) $_REQUEST['numOrder'];
	
	/**
	 * O $tipoTransacao 'seta' a forma de pagamento
	 * Exemplo: boleto, cartão, etc ...
	 */
	$tipoTransacao = $_REQUEST['transId'];
	
	/**
	 * variavel para ($i)ncrementação em loop's
	 */
	$i = 0;
		
	try
	{
		/**
		 *Instancia uma conexão com o banco de dados
		 */
		$conexaoBanco = new PDO('mysql:host=;dbname=', '', '');
		$conexaoBanco->setAttribute(PDO::ATTR_PERSISTENT,true);
		$conexaoBanco->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch(PDOException $e)
	{
		/**
		 *Erro no bloco try - Para conexao com o PDO
		 */
		echo 'Error: ' . $e->getMessage();
	}

 /**
  * banco de dados - data
  *
  */
  try
  {
    /**
	 * Modelagem das Querys
	 */
	$listaProdutosSelecionados = $conexaoBanco->query("SELECT * FROM `wp_wpsc_purchase_logs` WHERE sessionid='$numeroCompra'");
	$idcompra = $conexaoBanco->query("SELECT id,base_shipping FROM `wp_wpsc_purchase_logs` WHERE sessionid='$numeroCompra'");
		while($resultadoID = $idcompra->fetch(PDO::FETCH_OBJ))
		{
			$idCompra = (int) $resultadoID->id;
			$valorFrete = $resultadoID->base_shipping;
		}
	$precoProdutos = $conexaoBanco->query("SELECT * FROM `wp_wpsc_cart_contents` WHERE purchaseid='$idCompra'");
	
	/**
	 *  Quanto produtos estão na lista de compras
	 */
	$totalDeProdutos = $precoProdutos->rowCount();
	
	while($dados = $listaProdutosSelecionados->fetch(PDO::FETCH_OBJ))
	{
		$precoTotal  =  $dados->totalprice;
	}
	
	while($dados = $precoProdutos->fetch(PDO::FETCH_OBJ))
	{
		$nomeProduto[]          = $dados->name;
		$precoProduto[]         = str_replace('.' ,'', $dados->price);
		$quantidadeProduto[]    = $dados->quantity;
	}
  }
  catch(PDOException $erro)
  {
	echo 'Mensagem de erro #2: ' . $erro->getMessage();
  }
 

 /**
  * @obs Ao montar essa string não pode haver quebra de linha
  * e/ou espaços entre as tags
  */
	
	/**
	 * Dados para pagamento com boleto
	 */
	if($tipoTransacao == 'getBoleto')
	{
		/**
		 * Montando dados dos produtos selecionado
		 */
		$descricao = '<BEGIN_ORDER_DESCRIPTION>';
		$descricao .= "<orderid>=($numeroCompra)"; 
		
		while($i < $totalDeProdutos){
		
			$valorTotal = $quantidadeProduto[$i] * $precoProduto[$i];
			
			$descricao .= "<descritivo>=($nomeProduto[$i])";
			$descricao .= "<quantidade>=($quantidadeProduto[$i])";
			$descricao .= "<unidade>=(un)";
			$descricao .= "<valor>=($valorTotal)";
			
			$i++;
		
		}
		
		if($valorFrete != '' and $valorFrete != '0.00')
		{
			$descricao .= '<adicional>=(frete)';
			$descricao .= '<valorAdicional>=('.$valorFrete.')';
		}
		$descricao .= '<END_ORDER_DESCRIPTION>';
		
		/**
		 * Pegando variaveis do banco para
		 * montar o boleto
		 */
		$boletoBanco = $conexaoBanco->query("SELECT * FROM `wp_options` WHERE option_name='cedente_nome' OR  option_name='banco_nome' OR option_name='agencia_nome' OR option_name='conta_nome' OR option_name='assinatura_nome' OR option_name='vencimento_nome'");
		while($dados = $boletoBanco->fetch(PDO::FETCH_OBJ))
		{
			$informacoes[$dados->option_name] = $dados->option_value;
		}
		
		$clienteBanco = $conexaoBanco->query("SELECT * FROM `wp_wpsc_submited_form_data` WHERE log_id='$idCompra'");
		while($dados = $clienteBanco->fetch(PDO::FETCH_OBJ))
		{
			$numeroDado[$dados->form_id] = $dados->value;
		}
		
			
		$descricao .= '<BEGIN_BOLETO_DESCRIPTION>';
		$descricao .= '<CEDENTE>=('.$informacoes['cedente_nome'].')';
		$descricao .= '<BANCO>=('.$informacoes['banco_nome'].')';
		$descricao .= '<NUMEROAGENCIA>=('.$informacoes['agencia_nome'].')';
		$descricao .= '<NUMEROCONTA>=('.$informacoes['conta_nome'].')';
		$descricao .= '<ASSINATURA>=('.$informacoes['assinatura_nome'].')';
		
		#Datas de vencimento e pagamento
		$descricao .= '<DATAEMISSAO>=('.date("d/m/Y").')';
		$descricao .= '<DATAPROCESSAMENTO>=('.date("d/m/Y").')';
		$descricao .= '<DATAVENCIMENTO>=('.date("d/m/Y", strtotime("+".$informacoes['vencimento_nome']." days")).')';
		
		$numeroDoc = (string) str_pad($idCompra, 9 ,0 , STR_PAD_LEFT);
			
		#Dados do cliente
		$descricao .= '<NOMESACADO>=('.$numeroDado[2].' '.$numeroDado[3].')';
		$descricao .= '<ENDERECOSACADO>=('.$numeroDado[4].')';
		$descricao .= '<CIDADESACADO>=('.$numeroDado[5].')';
		$descricao .= '<UFSACADO>=('.strtoupper(substr($numeroDado[6] ,0 ,2)).')';
		$descricao .= '<CEPSACADO>=('.$numeroDado[8].')';
		$descricao .= '<CPFSACADO>=('.$numeroDado[19].')';
		$descricao .= "<NUMEROPEDIDO>=(".$numeroDoc.")";
		$descricao .= '<VALORDOCUMENTOFORMATADO>=(R$'.$precoTotal.')';
		$descricao .= '<SHOPPINGID>=(0)';
		$descricao .= '<NUMDOC>=('.$numeroDoc.')';
		$descricao .= '<END_BOLETO_DESCRIPTION>';
			
		echo $descricao;
		
	
	}
	
	/**
	 * Dados para pagamento com transferencia
	 */
	elseif($tipoTransacao == 'getTransfer')	{

	}

	/**
	 * Pega a descrição do produto inicialmente,
	 * o banco lê esses dados e monta em seu sistema
	 * para informar ao usuario e para ele decidir
	 * como efetuará o pagamento. Cartão, Boleto, etc...
	 */
	elseif($tipoTransacao == 'getOrder')
	{
	
		/**
		 * Montando dados dos produtos selecionado
		 */
		$descricao = '<BEGIN_ORDER_DESCRIPTION>';
		$descricao .= "<orderid>=($numeroCompra)"; 
		
		while($i < $totalDeProdutos){
		
			$valorTotal = $quantidadeProduto[$i] * $precoProduto[$i];
			
			$descricao .= "<descritivo>=($nomeProduto[$i])";
			$descricao .= "<quantidade>=($quantidadeProduto[$i])";
			$descricao .= "<unidade>=(un)";
			$descricao .= "<valor>=($valorTotal)";
			
			$i++;
		
		}
		
		if($valorFrete != '' and $valorFrete != '0.00')
		{
			$descricao .= '<adicional>=(frete)';
			$descricao .= '<valorAdicional>=('.$valorFrete.')';
		}
		
		$descricao .= '<END_ORDER_DESCRIPTION>';
		
		echo $descricao;
	}
	
	/**
	 * Resposta ao 2º acesso do banco
	 * Este processo tenta salvar os dados da compra
	 * no banco de dados da loja, se tudo ocorrer bem
	 * retornará a tag <PUT_AUTH_OK> se não retornará
	 * a tag <ERRO>
	 */
	elseif(($tipoTransacao == 'putAuth') and ($_REQUEST['cod'] == '0') and ($_REQUEST['if'] == 'bradesco')){

			try
			{
				/**
				 * Setando variaveis com os valores 
				 * recebidos do banco.
				 */
				$metodoPagamento = "CEB";
				$tipoPagamento   = isset($_REQUEST['tipopagto']) ? $_REQUEST['tipopagto'] : "";
				$prazo           = isset($_REQUEST['prazo']) ? $_REQUEST['prazo'] : "";
				$numeroParcelas  = isset($_REQUEST['numparc']) ? $_REQUEST['numparc'] : "";
				$valorParcela    = isset($_REQUEST['valparc']) ? $_REQUEST['valparc'] : "";
				$valorTotal      = isset($_REQUEST['valtotal']) ? $_REQUEST['valtotal'] : "";
				$dataCompra      = date('d/m/Y');
				$status          = 3;
				$nomeComprador   = isset($_REQUEST['ccname']) ? $_REQUEST['ccname'] : "Comprador desconhecido";
				$emailComprador  = isset($_REQUEST['ccemail']) ? $_REQUEST['ccemail'] : "Email deconhecido";
				$tipoComprador   = isset($_REQUEST['cctype']) ? $_REQUEST['cctype'] : "";
				$assinatura      = isset($_REQUEST['assinatura']) ? $_REQUEST['assinatura'] : "";

				/**
				 * Atualiza a situação da compra no banco de dados
				 */
					$atualizacao = $conexaoBanco->query("UPDATE `wp_wpsc_purchase_logs` SET processed='$status' WHERE id='$idCompra'");
					echo '<PUT_AUTH_OK>';

			}
			catch(PDOException $erro)
			{
				echo '<ERRO>';
			}
	}
	
	/**
	 * Se ouver algum erro na plataforma
	 * Bradesco de pagamento
	 */
	elseif($_REQUEST['cod'] != 0)
	{
		echo '<ERRO>';
	}
		
?>
