<?php
$environments = array();
$environments["sandbox"] 	=	array(
									"name"		=>	"Sandbox Test",
                  "api_url" =>  "https://secure.onecash-staging.com/api/v2/",
                  "web_url" =>  "https://secure.onecash-staging.com/",
								);

$environments["production"] =	array(
									"name"		=>	"Production",
									"api_url"	=>	"https://secure.onecash.com/api/v2/",
									"web_url"	=>	"https://secure.onecash.com/",
								);
?>
