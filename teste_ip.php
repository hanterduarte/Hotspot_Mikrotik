<?php

function execute_no_windows($comando) {
	echo "execute_no_windows(): Executando $comando\r\n";
	if (exec($comando, $linhas) === false) {
		die("execute_no_windows(\$comando = \"$comando\"): Falhou!\r\n");
	}
	return $linhas;
}

$linhas = execute_no_windows('ping -4 api.infinitepay.io');
echo '$linhas = ' . var_export($linhas, true) . "\r\n";

$linhas = execute_no_windows('getmac');
echo '$linhas = ' . var_export($linhas, true) . "\r\n";

?>