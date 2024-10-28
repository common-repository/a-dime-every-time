<?php
	
	
	/**
	 * 
	 * We use this to keep track of authorized services, those that report a permanent transaction, which means that user already has the rights to use the service
	 * @author bachbill
	 *
	 */
	class BachbillService{
		function __construct($serviceId){
			$this->serviceId=$serviceId;
		}
		public function getServiceId(){
			return $this->$serviceId;
		}
		
	}
	class BachbillApi
	{
		private $adminAreaId='';
		private $adminUserId='';
		private $adminUserPassword='';
		private $rootUrl;
		private $params=array();
		private $headers=array();
		private $method="post";
		
		private $serviceId;
		private $errorCode=0;
		private $errorMessage;
		
		public function getErrorCode(){
			return $this->errorCode;
		}
		public function getErrorMessage(){
			return $this->errorMessage;
		}
		public function hasErrors(){
			return $this->errorCode<>0;
		}
		public function getMethod(){
			return $this->method;
		}
		/**
		 * 
		 * Enter description here ...
		 * @param string $method get or post. Default is post
		 */
		function setMethod($method){
			$this->method=$method;
		}
		
		function __construct($rootUrl) {
			$this->rootUrl=$rootUrl;
		}
		public function getRootUrl(){
			return $this->rootUrl;
		}
		public function setRootUrl($rootUrl){
			$this->rootUrl=$rootUrl;
		}
		
		public function setAdminAreaId($adminAreaId){
			$this->adminAreaId=$adminAreaId;
		}
		public function setAdminUserId($adminUserId){
			$this->adminUserId=$adminUserId;
		}
		public function setAdminUserPassword($adminUserPassword){
			$this->adminUserPassword=$adminUserPassword;
		}
		/**
		 * 
		 * 
		 * @param array $params an array with key=>value
		 */
		public function setParams($params){
			$this->params=$params;
		}
		public function setParam($key, $value){
			$this->params[$key]=$value;
		}
		
		public function setHeaders($headers){
			$this->headers=$headers;
		}
		public function setHeader($header){
			$this->headers[]=$headers;
		}
		
		/**
		 * Returns a raw response or a json object of the response
		 * Enter description here ...
		 * @param unknown_type $url
		 */
		public function callApi($url, $returnJson=false)
		{
			//exec( 'echo "que pasa?">>/tmp/php.log');
			$finalUrl=$url;
			if (sizeof($this->params)>0){
				$glue=strpos($url, '?')>0?"&":"?";
				foreach ($this->params as $key=>$value){
					$finalUrl=$finalUrl.$glue.$key.'='.urlencode($value);
					$glue=$glue=='?'?'&':$glue;
				}
			}
			$fp = curl_init();

			if ($this->getMethod()=="post"){
				$n=strpos($finalUrl, '?');
				$n=$n<=0?strlen($finalUrl):$n;
				$qs=substr($finalUrl, $n+1);
				$fu=substr($finalUrl, 0, $n);
				curl_setopt($fp, CURLOPT_URL, $fu);
				curl_setopt ($fp, CURLOPT_POST, 1);
				curl_setopt ($fp, CURLOPT_POSTFIELDS, $qs);
				//exec( 'echo "url: '.$fu.'?'.$qs.'">>/tmp/php.log');
			}else {
				curl_setopt($fp, CURLOPT_URL, $finalUrl);
			}
			curl_setopt($fp, CURLOPT_HTTPHEADER, $this->headers); 
			curl_setopt($fp, CURLOPT_HEADER, 1);
			curl_setopt($fp, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($fp, CURLOPT_TIMEOUT, 30);
			curl_setopt($fp, CURLOPT_CONNECTTIMEOUT, 5);
			if (!empty($this->adminAreaId) && !empty($this->adminUserId) && !empty($this->adminUserPassword)){
				curl_setopt($fp, CURLOPT_USERPWD, $this->adminAreaId . "/".$this->adminUserId.":".$this->adminUserPassword);
			}
			$result = curl_exec($fp);
			//exec( 'echo "result '.$result.'">>/tmp/php.log');
			$ec=curl_errno($fp);
			if ($ec){
				$this->errorCode=$ec;
				$this->errorMessage=curl_error($fp);
				return;
			}
			curl_close($fp);
			if (!$result){
				return curl_errno($fp);
			}
			$matches='';
			preg_match('/ (.*) /', substr($result, 0, strpos($result, "\n")), $matches);
			$retcode=$matches[1];
			if ($retcode!=200){
				$err="Error: ".$retcode."\n";
				$this->errorCode=$retCode;
				$this->errorMessage=$err;
				return $err;
			}
			
			$pos=strpos($result, "\n\n");
			$pos=$pos?$pos+2:strpos($result, "\r\n\r\n")+4;
			$result=substr($result, $pos);
			//exec( 'echo "result '.$result.'">>/tmp/php.log');
			if (!$returnJson){
				return $result;
			}
			$obj=json_decode($result, true);
			return $obj;
		}
		
		public function checkErrorsInJSON($expr){
			$err=$expr['error'];
			if ($err){
				$this->errorCode=$err['code'];
				$this->errorMessage=$err['message'];
			}
		}
		
		function callUrl($url, $method, $postBody=null, $returnJson=true, $useAuth=false, $username=null, $password=null){
			$fp = curl_init();
		
			if ($method=='post'){
				curl_setopt($fp, CURLOPT_URL, $url);
				curl_setopt ($fp, CURLOPT_POST, 1);
				curl_setopt ($fp, CURLOPT_POSTFIELDS, $postBody);
			}else {
				curl_setopt($fp, CURLOPT_URL, $url);
			}
			curl_setopt($fp, CURLOPT_HEADER, 1);
			curl_setopt($fp, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($fp, CURLOPT_TIMEOUT, 30);
			curl_setopt($fp, CURLOPT_CONNECTTIMEOUT, 5);
			if ($useAuth){
				curl_setopt($fp, CURLOPT_USERPWD, $username.":".$password);
			}
			$result = curl_exec($fp);
			$ec=curl_errno($fp);
			if ($ec<>0){
				$this->errorCode=$ec;
				$this->errorMessage=curl_error($fp);
			}
			$pos=strpos($result, "\n\n");
			$pos=$pos?$pos+2:strpos($result, "\r\n\r\n")+4;
			$result=substr($result, $pos);
			//exec( 'echo "result '.$result.'">>/tmp/php.log');
			curl_close($fp);
			if (!$returnJson){
				return $result;
			}
			$json=json_decode($result, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		
		public function getSession($priceplanId, $provider, $endUserAreaId, $redirectUrl){
			$url=$this->rootUrl.'/bachbill/charging/1.0/'.$priceplanId.'/end_user/getSession?format=json&provider='.$provider.'&endUserAreaId='.$endUserAreaId.'&redirectUrl='.urlencode($redirectUrl);
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		public function checkSession($priceplanId, $provider, $endUserAreaId, $endUserSessionId){
			$url=$this->rootUrl.'/bachbill/charging/1.0/'.$priceplanId.'/end_user/getSession?format=json&provider='.$provider.'&endUserAreaId='.$endUserAreaId.'&endUserSessionId='.$endUserSessionId;
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		public function endSession($priceplanId, $endUserSessionId){
			$url=$this->rootUrl.'/bachbill/charging/1.0/'.$priceplanId.'/end_user/endSession?format=json&endUserSessionId='.$endUserSessionId;
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		public function authorize($priceplanId, $endUserAreaId, $endUserId, $serviceId, $nUsages=1){
			$url=$this->rootUrl.'/bachbill/charging/1.0/'.$priceplanId.'/end_user/authorize?format=json&endUserId='.$endUserAreaId.'/'.$endUserId.'&serviceId='.$serviceId.'&nUsages='.$nUsages;
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		public function purchase($priceplanId, $endUserAreaId, $endUserId, $bundleId, $pricepointId){
			$url=$this->rootUrl.'/bachbill/charging/1.0/'.$priceplanId.'/bundle/purchase?format=json&endUserId='.$endUserAreaId.'/'.$endUserId.'&bundleId='.$bundleId.'&pricepointId='.$pricepointId;
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		public function usage($priceplanId, $endUserAreaId, $endUserId, $subscriptionId, $serviceId, $nUsages=1){
			$url=$this->rootUrl.'/bachbill/charging/1.0/'.$priceplanId.'/subscription/usage?format=json&endUserId='.$endUserAreaId.'/'.$endUserId.'&subscriptionId='.$subscriptionId.'&serviceId='.$serviceId.'&nUsages='.$nUsages.'&autoCapture=true';
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		public function listSubscriptions($priceplanId, $endUserAreaId, $endUserId, $activeOnly=true){
			$url=$this->rootUrl.'/bachbill/charging/1.0/'.$priceplanId.'/subscription/listByUserId?format=json&endUserId='.$endUserAreaId.'/'.$endUserId.'&activeOnly='.($activeOnly?'true':'false');
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		public function calncelSubscription($priceplanId, $endUserAreaId, $endUserId, $subscriptionId){
			$url=$this->rootUrl.'/bachbill/charging/1.0/'.$priceplanId.'/subscription/cancel?format=json&endUserId='.$endUserAreaId.'/'.$endUserId.'&subscriptionId='.$subscriptionId;
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
		public function getReport($priceplanId, $reportType, $date1, $date2){
			$url=$this->rootUrl.'/bachbill/reportServlet?pricePlanName='.$priceplanId.'&date1='.$date1.'&date2='.$date2.'&reportName='.$reportType.'&groupBy=none&jobId=0&jobName=Live%20job';
			$this->setMethod("get");
			return $this->callApi($url, false);
			
		}
		public function callOnIncomingUrlAction($priceplanId, $url){
			$this->setParam('format', 'json');
			$url=$this->rootUrl.'/bachbill/in/'.$priceplanId.$url;
			$json=$this->callApi($url, true);
			$this->checkErrorsInJSON($json);
			return $json;
		}
	}
?>