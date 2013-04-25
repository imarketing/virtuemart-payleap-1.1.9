<?php
if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' );

class ps_payleap {

	var $payment_code = "PL";
	var $classname = "ps_payleap";

	/**
    * Show all configuration parameters for this payment method
    * @returns boolean False when the Payment method has no configration
    */
	function show_configuration() {

		global $VM_LANG, $sess;
		$db =& new ps_DB;
		$payment_method_id = vmGet( $_REQUEST, 'payment_method_id', null );
		/** Read current Configuration ***/
		require_once(CLASSPATH ."payment/".$this->classname.".cfg.php");
    ?>
      <table>
        <tr>
            <td><strong>Test Mode (Requires Test Account)</strong></td>
            <td>
                <select name="PL_TEST_REQUEST" class="inputbox" >
                <option <?php if (PL_TEST_REQUEST == 'TRUE') echo "selected=\"selected\""; ?> value="TRUE"><?php echo $VM_LANG->_('PHPSHOP_ADMIN_CFG_YES') ?></option>
                <option <?php if (PL_TEST_REQUEST == 'FALSE') echo "selected=\"selected\""; ?> value="FALSE"><?php echo $VM_LANG->_('PHPSHOP_ADMIN_CFG_NO') ?></option>
                </select>
            </td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td><strong>Username</strong></td>
            <td>
                <input type="text" name="PL_USERNAME" class="inputbox" value="<? echo PL_USERNAME ?>" />
            </td>
            <td>&nbsp;</td>
        </tr>
        <tr>
            <td><strong>Password</strong></td>
            <td>
                <input type="text" name="PL_PASSWORD" class="inputbox" value="<? echo PL_PASSWORD ?>" />
            </td>
            <td>&nbsp;</td>
        </tr>
      </table>
   <?php
   // return false if there's no configuration
   return true;
	}

	function has_configuration() {
		// return false if there's no configuration
		return true;
	}

	/**
	* Returns the "is_writeable" status of the configuration file
	* @param void
	* @returns boolean True when the configuration file is writeable, false when not
	*/
	function configfile_writeable() {
		return is_writeable( CLASSPATH."payment/".$this->classname.".cfg.php" );
	}

	/**
	* Returns the "is_readable" status of the configuration file
	* @param void
	* @returns boolean True when the configuration file is writeable, false when not
	*/
	function configfile_readable() {
		return is_readable( CLASSPATH."payment/".$this->classname.".cfg.php" );
	}
	/**
	* Writes the configuration file for this payment method
	* @param array An array of objects
	* @returns boolean True when writing was successful
	*/
	function write_configuration( &$d ) {

		$my_config_array = array("PL_TEST_REQUEST" => $d['PL_TEST_REQUEST'],
		"PL_USERNAME" => $d['PL_USERNAME'],
		"PL_PASSWORD" => $d['PL_PASSWORD']
		);
		$config = "<?php\n";
		$config .= "if( !defined( '_VALID_MOS' ) && !defined( '_JEXEC' ) ) die( 'Direct Access to '.basename(__FILE__).' is not allowed.' ); \n\n";
		foreach( $my_config_array as $key => $value ) {
			$config .= "define ('$key', '$value');\n";
		}

		$config .= "?>";

		if ($fp = fopen(CLASSPATH ."payment/".$this->classname.".cfg.php", "w")) {
			fputs($fp, $config, strlen($config));
			fclose ($fp);
			return true;
		}
		else {
			return false;
		}
	}

	/**************************************************************************
	** name: process_payment()
	** created by: Soeren
	** description: process transaction with authorize.net
	** parameters: $order_number, the number of the order, we're processing here
	**            $order_total, the total $ of the order
	** returns:
	***************************************************************************/
	function process_payment($order_number, $order_total, &$d) {
		global $vendor_mail, $vendor_currency, $VM_LANG, $vmLogger;
        
		$database = new ps_DB;
		$ps_vendor_id = $_SESSION["ps_vendor_id"];
		$auth = $_SESSION['auth'];
		$ps_checkout = new ps_checkout;

		// Get the Configuration File for payleap
		require_once(CLASSPATH ."payment/".$this->classname.".cfg.php");
		// connector class
		require_once(CLASSPATH ."connectionTools.class.php");

		// Get the Transaction Key securely from the database
		/*$database->query( "SELECT ".VM_DECRYPT_FUNCTION."(payment_passkey,'".ENCODE_KEY."') as passkey FROM #__{vm}_payment_method WHERE payment_class='".$this->classname."' AND shopper_group_id='".$auth['shopper_group_id']."'" );
		$transaction = $database->record[0];
        
        
        echo 'PL_USERNAME:'.PL_USERNAME."<br />";
        echo 'PL_PASSWORD:'.PL_PASSWORD."<br />";
        
		if( empty($transaction->passkey)) {
			$vmLogger->err( $VM_LANG->_('PHPSHOP_PAYMENT_ERROR',false).'. Technical Note: The required transaction key is empty! The payment method settings must be reviewed.' );
			return false;
		}*/

		// Get user billing information
		$dbbt = new ps_DB;

		$qt = "SELECT * FROM #__{vm}_user_info WHERE user_id=".$auth["user_id"]." AND address_type='BT'";

		$dbbt->query($qt);
		$dbbt->next_record();
		$user_info_id = $dbbt->f("user_info_id");
		if( $user_info_id != $d["ship_to_info_id"]) {
			// Get user billing information
			$dbst =& new ps_DB;
			$qt = "SELECT * FROM #__{vm}_user_info WHERE user_info_id='".$d["ship_to_info_id"]."' AND address_type='ST'";
			$dbst->query($qt);
			$dbst->next_record();
		}
		else {
			$dbst = $dbbt;
		}
        
        //echo 'month= '.$_SESSION['ccdata']['order_payment_expire_month']."<br />";
        //echo 'year= '.$_SESSION['ccdata']['order_payment_expire_year']."<br />";
        
        if (PL_TEST_REQUEST == 'TRUE')
        {
            //$_SESSION['ccdata']['order_payment_expire_month'] = '07';
            //$_SESSION['ccdata']['order_payment_expire_year'] = '13';
        }
		//Authnet vars to send
		$formdata = array (
		'UserName' => PL_USERNAME,
		'Password' => PL_PASSWORD,
		'TransType' => 'Sale',
		'CardNum' => $_SESSION['ccdata']['order_payment_number'],
		'CVNum' => $_SESSION['ccdata']['credit_card_code'],
        /*'NameOnCard' => $_SESSION['ccdata']['credit_card_code'],*/
		'ExpDate' => ($_SESSION['ccdata']['order_payment_expire_month']) . substr(($_SESSION['ccdata']['order_payment_expire_year']), 2, 2),
		//'MagData' => 'FALSE',
		'MagData' => '',
		'NameOnCard' => '',
		'Amount' => $order_total,
		'InvNum' => '',
		'PNRef' => '',
		'Zip' => substr($dbbt->f("zip"), 0, 40),
		'Street' => substr($dbbt->f("address_1"), 0, 40),
		'ExtData' => '<TrainingMode>F</TrainingMode>'
		);

		//build the post string
		$poststring = '';
		foreach($formdata AS $key => $val){
			$poststring .= urlencode($key) . "=" . urlencode($val) . "&";
		}
		// strip off trailing ampersand
		$poststring = substr($poststring, 0, -1);
		
		switch (PL_TEST_REQUEST) {
			case 'FALSE':
			  $host = 'https://secure1.payleap.com/TransactServices.svc/ProcessCreditCard';
			  break;

			default:
			  $host = 'https://uat.payleap.com/TransactServices.svc/ProcessCreditCard';
			  break;
		}
					
		$result = vmConnector::handleCommunication($host, $poststring);
		
		if( !$result ) {
			$vmLogger->err('The transaction could not be completed.' );
			return false;
		}
        
        $approval = $this->simple_xml_find($result, "</Result>");
        
        $message = $this->simple_xml_find($result, "</Message>");
        
        $authcode = $this->simple_xml_find($result, "</AuthCode>");

        if($approval == "0")
        {
            $d["order_payment_log"] = $VM_LANG->_('PHPSHOP_PAYMENT_TRANSACTION_SUCCESS').": ";
            $d["order_payment_log"] .= $message;

            $vmLogger->debug($d['order_payment_log']);

            // Catch Transaction ID
            $d["order_payment_trans_id"] = $authcode;

            return True;
        }
        else
        {
			//$vmLogger->err( $response[0] . "-" . $response[1] . "-" . $response[2] . "-" .  $response[5] . "-" . $response[38] . "-" . $response[39] . "-" . $response[3] );
               /*} else {
                   $vmLogger->err( $response[3] );
            }*/

            $d["order_payment_log"] = $message;
            // Catch Transaction ID
            $d["order_payment_trans_id"] = $authcode;
			$d["order_payment_trans_result"] = $this->PrintResult($approval);
			echo $this->PrintResult($approval);
            return False;
        }
	}
    
    function simple_xml_find($haystack, $needle) 
    {
        // supplying a valid closing XML tag in $needle, this will return the data contained by the element
        // the element in question must be a leaf, and not itself contain other elements (this is *simple*_xml_find =)

        if(($end = strpos($haystack, $needle)) === FALSE)
            return("");
            
        for($x = $end; $x > 0; $x--)
            {
            if($haystack{$x} == ">")
                return(trim(substr($haystack, $x + 1, $end - $x - 1)));
            }
        return ("");
    }
	function PrintResult($errorcode)
	{
		$strResult = '';
		switch($errorcode)
		{
			case -100:
				$strResult = 'Transaction NOT Processed; Generic Host Error';
				break;
			case 0:
				$strResult = 'Approved';
				break;
			case 1:
				$strResult = 'User Authentication Failed';
				break;
			case 2:
				$strResult = 'Invalid Transaction';
				break;
			case 3:
				$strResult = 'Invalid Transaction Type';
				break;
			case 4:
				$strResult = 'Invalid Amount';
				break;
			case 5:
				$strResult = 'Invalid Merchant Information';
				break;
			case 7:
				$strResult = 'Field Format Error';
				break;
			case 8:
				$strResult = 'Not a Transaction Server';
				break;
			case 9:
				$strResult = 'Invalid Parameter Stream';
				break;
			case 10:
				$strResult = 'Too Many Line Items';
				break;
			case 11:
				$strResult = 'Client Timeout Waiting for Response';
				break;
			case 12:
				$strResult = 'Transaction Declined';
				break;
			case 13:
				$strResult = 'Referral';
				break;
			case 14:
				$strResult = 'Transaction Type Not Supported In This Version';
				break;
			case 19:
				$strResult = 'Original Transaction ID Not Found';
				break;
			case 20:
				$strResult = 'Customer Reference Number Not Found';
				break;
			case 22:
				$strResult = 'Invalid ABA Number';
				break;
			case 23:
				$strResult = 'Invalid Account Number';
				break;
			case 24:
				$strResult = 'Invalid Expiration Date';
				break;
			case 25:
				$strResult = 'Transaction Type Not Supported by Host';
				break;
			case 26:
				$strResult = 'Invalid Reference Number';
				break;
			case 27:
				$strResult = 'Invalid Receipt Information';
				break;
			case 28:
				$strResult = 'Invalid Check Holder Name';
				break;
			case 29:
				$strResult = 'Invalid Check Number';
				break;
			case 30:
				$strResult = 'Check DL Verification Requires DL State';
				break;
			case 40:
				$strResult = 'Transaction did not connect(to NCN because SecureNCIS is not running on the web server)';
				break;
			case 50:
				$strResult = 'Insufficient Funds Available';
				break;
			case 99:
				$strResult = 'General Error';
				break;
			case 100:
				$strResult = 'Invalid Transaction Returned from Host';
				break;
			case 101:
				$strResult = 'Timeout Value too Small or Invalid Time Out Value';
				break;
			case 102:
				$strResult = 'Processor Not Available';
				break;
			case 103:
				$strResult = 'Error Reading Response from Host';
				break;
			case 104:
				$strResult = 'Timeout waiting for Processor Response';
				break;
			case 105:
				$strResult = 'Credit Error';
				break;
			case 106:
				$strResult = 'Host Not Available';
				break;
			case 107:
				$strResult = 'Duplicate Suppression Timeout';
				break;
			case 108:
				$strResult = 'Void Error';
				break;
			case 109:
				$strResult = 'Timeout Waiting for Host Response';
				break;
			case 110:
				$strResult = 'Duplicate Transaction';
				break;
			case 111:
				$strResult = 'Capture Error';
				break;
			case 112:
				$strResult = 'Failed AVS Check';
				break;
			case 113:
				$strResult = 'Cannot Exceed Sales Cap';
				break;
			case 1000:
				$strResult = 'Generic Host Error';
				break;
			case 1001:
				$strResult = 'Invalid Login';
				break;
			case 1002:
				$strResult = 'Insufficient Privilege or Invalid Amount';
				break;
			case 1003:
				$strResult = 'Invalid Login Blocked';
				break;
			case 1004:
				$strResult = 'Invalid Login Deactivated';
				break;
			case 1005:
				$strResult = 'Transaction Type Not Allowed';
				break;
			case 1006:
				$strResult = 'Unsupported Processor';
				break;
			case 1007:
				$strResult = 'Invalid Request Message';
				break;
			case 1008:
				$strResult = 'Invalid Version';
				break;
			case 1010:
				$strResult = 'Payment Type Not Supported';
				break;
			case 1011:
				$strResult = 'Error Starting Transaction';
				break;
			case 1012:
				$strResult = 'Error Finishing Transaction';
				break;
			case 1013:
				$strResult = 'Error Checking Duplicate';
				break;
			case 1014:
				$strResult = 'No Records To Settle(in the current batch)';
				break;
			case 1015:
				$strResult = 'No Records To Process(in the current batch)';
				break;
		}
		return $strResult;
	}

}
