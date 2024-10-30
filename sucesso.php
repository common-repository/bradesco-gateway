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
		$conexaoBanco = new PDO('mysql:;dbname=', '', '');
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
		$precoProduto[]         = $dados->price;
		$quantidadeProduto[]    = $dados->quantity;
	}
  }
  catch(PDOException $erro)
  {
	echo 'Mensagem de erro #2: ' . $erro->getMessage();
  }
 
	/**
	 * Montando os dados da compra - 
	 * Produtos, quantidade, valores,
	 * custos extras, etc...
	 */
	 
	 echo '<table border="1">
	 <tr>
		<td colspan="4"><b><center>Itens Adquiridos<center></b></td>
	 </tr>
	 <tr>
		<td>descrição</td>
		<td>quantidade</td>
		<td>unidade</td>
		<td>valor</td>
	 </tr>';
	 
	 /**
	  * Loop para imprimir os produtos na tela
	  * usada na confirmação da compra
	  */
	 $quantidadeProdutos = count($nomeProduto);
	 $x = 0;
	 while($x <  $quantidadeProdutos)
	 {
		echo "<tr>
				<td>$nomeProduto[$x]</td>
				<td>$quantidadeProduto[$x]</td>
				<td>un</td>
				<td>R\$$precoProduto[$x]</td>
				</tr>";
		$x++;
	 }
	 
	 echo '<tr>
				<td colspan="3">Frete</td>
				<td>R$'.$valorFrete.'</td>
			</tr>
			<tr>
				<td colspan="3">Parcelas</td>
				<td>'.$_REQUEST['numparc'].'</td>
			</tr>
			<tr>
				<td colspan="3">Valor das Parcelas</td>
				<td>R$';
				
				$ValorParc = ($_REQUEST['valparc'] / 100);
				$ValorParc = number_format($ValorParc, 2 , ',', '.');
				
				echo $ValorParc.'</td>
			</tr>
			<tr>
				<td colspan="3">Valor total</td>
				<td>R$';
				
				$ValorAll = ($_REQUEST['valtotal'] / 100);
				$ValorAll = number_format($ValorAll, 2 , ',', '.');	
				
			
				echo $ValorAll . '</td>
			</tr>';
	 
	 echo '</table>';
	 
	 
	/**
	 * Pegando os dados do cliente e exibindo na tela
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
		
		
	 echo '<table border="1">
				<tr>
					<td colspan="2"><b><center>Dados de pagamento</center></b></td>
				</tr>
				<tr>
					<td>Número ref. da transação:</td>
					<td>'.$numeroCompra.'</td>
				</tr>
				<tr>
					<td>Comprador</td>
					<td>'.$_REQUEST['ccname'].'</td>
				</tr>
				<tr>
					<td>Email</td>
					<td>'.$_REQUEST['ccemail'].'</td>
				</tr>
				<tr>
					<td>Cartão</td>
					<td>'.$_REQUEST['cctype'].'</td>
				</tr>
				<tr>
					<td>Prazo para débito</td>
					<td>'.$_REQUEST['prazo'].'</td>
				</tr>';
		
		/**
		 * Dados de entrega
		 */
		  echo '<table border="1">
				<tr>
					<td colspan="2"><b><center>Dados de entrega</center></b></td>
				</tr>
				<tr>
					<td>Nome</td>
					<td>'.$numeroDado[2].'</td>
				</tr>
				<tr>
					<td>Endereço</td>
					<td>';
					if(isset($numeroDado[13]) and $numeroDado[13] != '')
					{
						echo $numeroDado[13];
					}
					else
					{
						echo $numeroDado[4]; 
					}
					
					echo '</td>
				</tr>
				<tr>
					<td>Cidade</td>
					<td>';
					
					if(isset($numeroDado[14]) and $numeroDado[14] != '')
					{
						echo $numeroDado[14];
					}
					else
					{
						echo $numeroDado[5]; 
					}
					
					
					echo '</td>
				</tr>
				<tr>
					<td>Estado</td>
					<td>';
					
					if(isset($numeroDado[15]) and $numeroDado[15] != '')
					{
						echo $numeroDado[15];
					}
					else
					{
						echo $numeroDado[6];
					}
					
					
					echo '</td>
				</tr>
				<tr>
					<td>CEP</td>
					<td>';
					
					if(isset($numeroDado[17]) and $numeroDado[17] != '')
					{
						echo $numeroDado[17];
					}
					else
					{
						echo $numeroDado[8];
					}
					
					echo '</td>
				</tr>
				<tr>
					<td>Tel</td>
					<td>'.$numeroDado[18].'</td>
				</tr>
				<tr>
					<td>EMail</td>
					<td>'.$numeroDado[9].'</td>
				</tr>';
	 
	 $conexaoBanco = null;
	 
	 
	 
	 $assinaturaBradesco = $_REQUEST['assinatura'];
	 /**
	  * Monta a tabela de assinatura digital
	  * enviada pelo banco Bradesco
	  */
	 echo "<table border='1'>";
	 echo "Autenticação:";
	 
	 $ini = 4;
	 $numero = 0;
	 $contagem = 0;
	 
		echo '<tr>';
		 while($ini <= 256)
		 {	
		 $contagem++;
			  echo '<td>';
			  while($numero < $ini)
			  {
				echo $assinaturaBradesco[$numero];
				$numero++;
			 }
			 echo '</td>';
			 if($contagem % 16 == 0) echo '</tr><tr>';
			 
		 $ini = $ini + 4;
		 }
		 echo '</tr>';

	echo '</table>';
	 
	 
?>	
