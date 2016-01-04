<?php

//Settings
	$fd_url = "https://yoursite.freshdesk.com"; //Make sure to check if https is enabled in Freshdesk Security settings
	$fd_api="freshdeskapi"; //you can find this in your Freshdesk profile
	$ldap_host = "domaincontroller.domain.com"; // Active Directory server
	$ldap_dn = "OU=Users,DC=domain,DC=com"; // Active Directory DN
	$ldap_usr_dom = "@domain.com"; // Domain for purposes of constructing $user
	$ldap_username = "username"; //Domain username (doesn't need write access)
	$ldap_password = "password"; //Domain user password

//See if user exists in Freshdesk

	$url = $fd_url . "/contacts.json?query=email%20is%20" . urlencode($_GET['email']);
	$ch = curl_init ($url);
	curl_setopt($ch, CURLOPT_USERPWD, "$fd_api:X");                                                                                                  
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);                                                                 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                                                                    
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));      
	$result = curl_exec($ch);
	
	if(strlen($result) == 2) {
		print ("This user doesn't exist in Freshdesk. We'll create a new customer for " . $_GET['email'] . ".<br />");
		$req_type = "New";
		$user_id = explode("@",$_GET['email']);
		$user_id = $user_id[0];
		print("Changing user ID to " . $user_id . "<hr>");
	}
	else
	{
		$json = json_decode($result, true);
		$req_type = "Existing";
		foreach($json as $key){
			print("This email belongs to customer " . $key['user']['id'] . ". We'll update the details from Active Directory.<hr>");
			$user_id = $key['user']['id'];
		}
	}
	curl_close ($ch);

//Function to connect and search Active Directory
	function authenticate($user, $password, $email_add) {
		global $ldap_host, $ldap_usr_dom, $ldap_dn, $ldap_username, $ldap_password;
		$ldap = ldap_connect($ldap_host);
		if($bind = @ldap_bind($ldap, $user . $ldap_usr_dom, $password)) {
			$filter = "(mail=" . $email_add . ")";
			$attr = array("displayName", "company", "sn", "givenName", "title", "mobile", "telephoneNumber", "physicalDeliveryOfficeName","thumbnailphoto","mail");
			$resultad = ldap_search($ldap, $ldap_dn, $filter, $attr) or exit("Unable to connect to LDAP server");
			$entries = ldap_get_entries($ldap, $resultad);
			ldap_unbind($ldap);
			return $entries;
		}
	}
	$entries = authenticate($ldap_username,$ldap_password, $_GET['email']);
	
//Check if AD object is correct
	if (!isset($entries[0]['displayname'][0])) {
		print "No AD user found. Terminating import job.<hr>";
		file_put_contents("log.txt", "Imported " . $req_type . " user ". $user_id . ". Errors: No AD user found. \r\n", FILE_APPEND | LOCK_EX);
		exit;
	}	
	if (!isset($entries[0]['telephonenumber'][0])) {
		$entries[0]['telephonenumber'][0] = "";
		print "Blank Phone<hr>";
	}	
	if (!isset($entries[0]['mobile'][0])) {
		$entries[0]['mobile'][0] = "";
		print "Blank Mobile<hr>";
	}
	
//Download and convert AD photo
	$data = 'data:image/jpeg;base64,' . base64_encode($entries[0]['thumbnailphoto'][0]);
	list($type, $data) = explode(';', $data);
	list(, $data)      = explode(',', $data);
	$data = base64_decode($data);
	file_put_contents(getcwd() . '\images\\' . $user_id . '.jpg', $data);


//Set data upload & URL based on New or Existing Customer
	if ($req_type == "New") { 
			$data = array("user[email]" => $entries[0]['mail'][0], "user[name]" => $entries[0]['givenname'][0] . " " . $entries[0]['sn'][0], "user[email]" => $_GET['email'],"user[job_title]" => $entries[0]['title'][0],"user[company_name]" => $entries[0]['company'][0],"user[phone]" => $entries[0]['telephonenumber'][0],"user[mobile]" => $entries[0]['mobile'][0],"user[custom_field][cf_office]" => $entries[0]['physicaldeliveryofficename'][0], "user[avatar_attributes][content]"=>curl_file_create( getcwd() . '\images\\' . $user_id . '.jpg','image/jpeg', 'image.jpg'));                                                                  		
			$url = $fd_url . '/contacts.json';
	} else {  
			$data = array("user[name]" => $entries[0]['givenname'][0] . " " . $entries[0]['sn'][0], "user[email]" => $_GET['email'],"user[job_title]" => $entries[0]['title'][0],"user[company_name]" => $entries[0]['company'][0],"user[phone]" => $entries[0]['telephonenumber'][0],"user[mobile]" => $entries[0]['mobile'][0],"user[custom_field][cf_office]" => $entries[0]['physicaldeliveryofficename'][0], "user[avatar_attributes][content]"=>curl_file_create( getcwd() . '\images\\' . $user_id . '.jpg','image/jpeg', 'image.jpg'));                                                                  
			$url = $fd_url . '/contacts/' . $user_id . '.json';
	}
	
	print ("<table border='1'><tr><td>Field</td><td>Value</td></tr>");
	print ("<tr><td>Photo</td><td><img src='data:image/jpeg;base64," . base64_encode($entries[0]['thumbnailphoto'][0]) . "'/></td></tr>");
	foreach ($data as $key => $value)
	{
		if ($key != "user[avatar_attributes][content]"){
		print ("<tr><td>" . $key . "</td><td>" . $value . "</td></tr>");	
		}
	}
	print ("</table><hr>");
	
//Upload data to Freshdesk & log output to log.txt
	$ch = curl_init ($url);
	curl_setopt($ch, CURLOPT_USERPWD, "$fd_api:X");  
	if ($req_type == "New") {                                                                                                
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); 
	} else {                                                                    
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); 
	}                                                                    
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);                                                                 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);                                                                    
	$result = curl_exec($ch);
	print ("<b>Imported " . $req_type . " user with the result: </b><br />" . $result);
	file_put_contents("log.txt", "Imported " . $req_type . " user ". $entries[0]['mail'][0] . ". Errors: " . $result ."\r\n", FILE_APPEND | LOCK_EX);
	curl_close ($ch);
?>
