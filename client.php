<?php
include 'class.client.php';
$client = new client();
$client -> connect();
$client -> auth('123456');
$t = microtime(true);
for($i=0;$i<1;$i++){
	$client -> set($i,'hello Cached',10,client::w); //写缓存
	//$client -> heart(); //心跳
}
echo 'timeout: '. (microtime(true) - $t);
?>