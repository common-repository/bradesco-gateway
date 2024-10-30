<?php
 /**
  * Tratamento de erros 
  */

   echo 'Erro ao efetuar a compra';
	$log = print_r($_REQUEST);
	
	$arquivo  = fopen("log.txt", 'w+');
	fwrite($arquivo, $log);
	fclose($arquivo);
	
	
?>