<?php

class Rezi {

	public $environment;
	public $clientSecret;
	public $clientID;
	public $token;

	public function __construct($apiKey = false, $clientSecret = false, $clientID = false, $environment = "UAT"){

		// SET ENVIRONMENT

		$this->environment = $environment;

		if(!$apiKey){

			$this->clientSecret = $clientSecret;
			$this->clientID = $clientID;

			if(!$this->checkToken()){

				$this->reziAuthenticate(); 

			}

		}

	}

	public function processPayLoad($payload){

		return json_encode($payload);

	}

	public function getEnvironment($type){

		switch($type){

			case "auth":

				if($this->environment === "UAT"){

					return "https://dezrez-core-auth-uat.dezrez.com";

				}
				else{

					return "https://auth.dezrez.com";

				}


				break;

			case "endpoint":

				if($this->environment === "UAT"){

					return "http://core-api-uat.dezrez.com";

				}
				else{

					return "http://api.dezrez.com";

				}

				break;



		}

		return false;

	}

	public function reziAuthenticate(){

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->getEnvironment("auth")."/Dezrez.Core.Api/oauth/token",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => "{ grant_type:'client_credentials', scope:'impersonate_web_user property_read people_read property_write event_read document_write document_read people_write event_write lead_sender'}",
			CURLOPT_HTTPHEADER => array(
				"authorization: Basic " . $this->getAuthString(),
				"cache-control: no-cache",
				"content-type: application/json",
			),
		));

		$cresponse = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		$answer = json_decode($cresponse);


		if ($answer->access_token) {
			// Store access token and report good result

			$file = substr($this->clientSecret, 0, 8) . '.txt';

			$current = $answer->access_token;

			file_put_contents($file, $current);
			echo "success<br/><br/><br/>";
			return $current;
		}
		else{
			echo "fail <br/><br/><br/>";
			return false;
		}


	}

	public function checkToken(){

		$file = substr($this->clientSecret, 0, 8) . '.txt';

		if ($file){

			if(!file_exists($file)){
				return false;
			}

			$token = file_get_contents($file);

			$jwt = explode(".", $token);
			$theToken = json_decode(base64_decode($jwt[1]));

			$timestamp = $theToken->exp;
			if( date("d-m-Y H:i:s") > date("d-m-Y H:i:s", $timestamp)){
				return false;
			}
			else
			{
				$this->token = $token;
				return true;
			}

		}

		return false;

	}

	public function getAuthString(){
		return base64_encode($this->clientID . ':' . $this->clientSecret);
	}

	public function getRezi($type, $endpoint, $pagesize = false, $pagenumber = false, $payload = false, $qstring = false){

		$curl = curl_init();

		if($pagesize){

			$psize = "&pagesize=" . $pagesize;

		}

		if($pagenumber){

			$pnum = "&pagenumber=" . $pagenumber;

		}

		if($qstring){

			$query = "&" . $qstring;

		}

		$requesturl = $this->getEnvironment("endpoint")."/".$endpoint."?agencyid=".$this->getoption('agencyid') . $psize. $pnum . $query;

		$curlopts = array(
			CURLOPT_URL => $requesturl,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $type,
			CURLOPT_HTTPHEADER => array(
				"authorization: Bearer ". $this->token,
				"cache-control: no-cache",
				"content-type: application/json",
				"rezi-api-version: 1.0"
			),
			CURLOPT_POSTFIELDS => ""
		);

		// IF THERE IS A PAYLOAD, SHOVE IT IN (GIGGEDY)
		if($payload){
			$curlopts[CURLOPT_POSTFIELDS] = $payload;
		}

		curl_setopt_array($curl, $curlopts);

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			return "cURL Error #:" . $err;
		} else {
			//echo $requesturl;
			return json_decode($response, true, 512);
		}


	}

	public function propertySearch($params){

		$params['MarketingFlags'] = array("ApprovedForMarketingWebsite");
		$params['BranchIdList'] = array();
		$params['RoleTypes'] = array();

		if($params['type']){
			foreach($params['type'] as $roleType){
				array_push($params['RoleTypes'], $roleType);
			}
		}

		$payload = $this->processPayLoad($params);

		$results = $this->getRezi("POST", 'api/simplepropertyrole/search', 20, 1, $payload);

		return ($results);

	}

	public function getSimplePropertyRole($id){

		return $this->getRezi("GET", "api/simplepropertyrole/" . $id , false, false, false);

	}

}