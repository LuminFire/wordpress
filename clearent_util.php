<?php

class clearent_util {

    protected $option_name = 'clearent_opts';

    public function add_record($table_name, $values) {
        global $wpdb;

        $table = $wpdb->prefix . $table_name;
        $wpdb->insert($table, $values);

    }

    public function get_year_options() {
        // set up year dropdown for expiration month
        $year_options = '';
        $today = getdate();
        for ($i = $today["year"]; $i < $today["year"] + 11; $i++) {
            $year_options .= '<option value="' . strftime('%y', mktime(0, 0, 0, 1, 1, $i)) . '">' . strftime('%Y', mktime(0, 0, 0, 1, 1, $i)) . '</option>';
        }
        return $year_options;
    }

    public function get_state_options() {
        $states = array(
            "AL" => "Alabama",
            "AK" => "Alaska",
            "AZ" => "Arizona",
            "AR" => "Arkansas",
            "CA" => "California",
            "CO" => "Colorado",
            "CT" => "Connecticut",
            "DE" => "Delaware",
            "DC" => "District Of Columbia",
            "FL" => "Florida",
            "GA" => "Georgia",
            "HI" => "Hawaii",
            "ID" => "Idaho",
            "IL" => "Illinois",
            "IN" => "Indiana",
            "IA" => "Iowa",
            "KS" => "Kansas",
            "KY" => "Kentucky",
            "LA" => "Louisiana",
            "ME" => "Maine",
            "MD" => "Maryland",
            "MA" => "Massachusetts",
            "MI" => "Michigan",
            "MN" => "Minnesota",
            "MS" => "Mississippi",
            "MO" => "Missouri",
            "MT" => "Montana",
            "NE" => "Nebraska",
            "NV" => "Nevada",
            "NH" => "New Hampshire",
            "NJ" => "New Jersey",
            "NM" => "New Mexico",
            "NY" => "New York",
            "NC" => "North Carolina",
            "ND" => "North Dakota",
            "OH" => "Ohio",
            "OK" => "Oklahoma",
            "OR" => "Oregon",
            "PA" => "Pennsylvania",
            "RI" => "Rhode Island",
            "SC" => "South Carolina",
            "SD" => "South Dakota",
            "TN" => "Tennessee",
            "TX" => "Texas",
            "UT" => "Utah",
            "VT" => "Vermont",
            "VA" => "Virginia",
            "WA" => "Washington",
            "WV" => "West Virginia",
            "WI" => "Wisconsin",
            "WY" => "Wyoming"
        );

        $state_options = '<option value="" disabled="disabled" selected="selected" style="display:none">State</option>';
        foreach ($states as $key => $value) {
            $state_options .= '<option value="' . $key . '">' . $value . '</option>';
        }

        return $state_options;

    }

    function ccMasking($number, $maskingCharacter = '#') {
        return substr($number, 0, 4) . str_repeat($maskingCharacter, strlen($number) - 8) . substr($number, -4);
    }

    public function sendCurl($url, $payment_data) {
    	$is_recurring = $payment_data["recurring-payment"];
	    unset($payment_data["recurring-payment"]);

	    $payment_url = $url . wp_clearent::TRANSACTION_ENDPOINT;
	    $curl = curl_init($payment_url);

	    $headers = array(
		    "Accept: " . "application/json",
		    "Content-Type: " . "application/json",
		    "Cache-Control: no-cache"
	    );

	    //curl_setopt($curl, CURLOPT_PORT, 443);
	    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
	    //curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
	    curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
	    curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
	    curl_setopt($curl, CURLOPT_POST, 1);
	    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payment_data));

	    $this->logger("CURL sent to: " . $url);

	    $payment_response = curl_exec($curl);
	    curl_close($curl);

	    $this->logger("--------------------- begin curl response ---------------------");
	    $this->logger($payment_response);
	    $this->logger("--------------------- end curl response ---------------------");

	    $payment_response_array = json_decode($payment_response, true);

    	if ( 'true' === $is_recurring && $payment_response_array["code"] == "200") {
		    $customer_url = $url . wp_clearent::CUSTOMER_ENDPOINT;

		    $curl = curl_init($customer_url);

		    $headers = array(
		    	"api-key: " . $payment_data["api-key"],
			    "Accept: " . "application/json",
			    "Content-Type: " . "application/json",
			    "Cache-Control: no-cache"
		    );

		    $customer_payload = array(
		    	"billing-address" => $payment_data["billing"],
				"email" => $payment_data["email-address"],
				"first-name" => $payment_data["billing"]["first-name"],
				"last-name" => $payment_data["billing"]["last-name"],
				"phone" => $payment_data["billing"]["phone"],
			    "shipping-address" => $payment_data["shipping"]
		    );

		    //curl_setopt($curl, CURLOPT_PORT, 443);
		    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		    //curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		    curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
		    curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
		    curl_setopt($curl, CURLOPT_POST, 1);
		    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($customer_payload));

		    $customer_response = curl_exec($curl);
		    curl_close($curl);

		    $this->logger("--------------------- begin curl response ---------------------");
		    $this->logger($customer_response);
		    $this->logger("--------------------- end curl response ---------------------");

		    $customer_response_array = json_decode($customer_response, true);

		    if ($customer_response_array["code"] == "201" && !empty($customer_response_array["payload"]["customer"]["customer-key"])) {
			    $token_url = $url . wp_clearent::TOKEN_ENDPOINT;

			    $curl = curl_init($token_url);

			    $headers = array(
				    "api-key: " . $payment_data["api-key"],
				    "Accept: " . "application/json",
				    "Content-Type: " . "application/json",
				    "Cache-Control: no-cache"
			    );

			    $token_payload = array(
				    "card" => $payment_data["card"],
				    "card-type" => $payment_data["card-type"],
				    "csc" => $payment_data["csc"],
				    "customer-key" => $customer_response_array["payload"]["customer"]["customer-key"],
				    "exp-date" => $payment_data["exp-date"],
				    "last-four-digits" => substr($payment_data["card"], -4),
			    );

			    //curl_setopt($curl, CURLOPT_PORT, 443);
			    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
			    //curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, FALSE);
			    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
			    curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
			    curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
			    curl_setopt($curl, CURLOPT_POST, 1);
			    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($token_payload));

			    $token_response = curl_exec($curl);
			    curl_close($curl);

			    $this->logger("--------------------- begin curl response ---------------------");
			    $this->logger($token_response);
			    $this->logger("--------------------- end curl response ---------------------");

			    $token_response_array = json_decode($token_response, true);

			    if ($token_response_array["code"] == "200" && !empty($token_response_array["payload"]["tokenResponse"]["token-id"])) {
				    $payment_plan_url = $url . wp_clearent::PAYMENT_PLAN_ENDPOINT;

				    $curl = curl_init($payment_plan_url);

				    $headers = array(
					    "api-key: " . $payment_data["api-key"],
					    "Accept: " . "application/json",
					    "Content-Type: " . "application/json",
					    "Cache-Control: no-cache"
				    );

				    $date = new DateTime();
				    $date->modify('+1 month');
				    $start_date = $date->format("Y-m-d");
				    $date->modify('+118 month');
				    $end_date = $date->format("Y-m-d");


				    $payment_plan_payload = array(
					    "customer-key" => $customer_response_array["payload"]["customer"]["customer-key"],
					    "customer-name" => $payment_data["billing"]["first-name"] . $payment_data["billing"]["last-name"],
					    "frequency" => "MONTHLY",
					    "frequency-month" => "1",
					    "frequency-day" => $date->format("j"),
					    "payment-amount" => $payment_data["amount"],
					    "start-date" => $start_date,
					    "end-date" => $end_date,
					    "status" => "Active",
					    "token-id" => $token_response_array["payload"]["tokenResponse"]["token-id"],
				    );

				    //curl_setopt($curl, CURLOPT_PORT, 443);
				    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
				    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
				    //curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, FALSE);
				    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
				    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
				    curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
				    curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
				    curl_setopt($curl, CURLOPT_POST, 1);
				    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payment_plan_payload));

				    $plan_response = curl_exec($curl);
				    curl_close($curl);

				    $this->logger("--------------------- begin curl response ---------------------");
				    $this->logger($plan_response);
				    $this->logger("--------------------- end curl response ---------------------");
			    }
		    }
	    }

        return $payment_response;
    }

    public function logger($message, $prefix = '') {
        // recursively walks message if array is passed in
        $debug = get_option($this->option_name)["enable_debug"] == 'enabled';
        if ($debug) {
            if (is_array($message)) {
                foreach ($message as $key => $value) {
                    if (is_array($value)) {
                        $this->logger($value, $prefix . $key . '.');
                    } else {
                        if ($key == 'card') {
                            // if array contains card then sanitize output
                            // never, ever, ever, ever, ever, ever, ever log raw card numbers
                            $this->logMessage($prefix . $key . ' = ' . (str_repeat('X', strlen($value) - 4) . substr($value, -4)));
                        } else if ($key == 'exp-date'|| $key == 'csc' || $key == 'api-key') {
                            $this->logMessage($prefix . $key . ' = [redacted]');
                        } else {
                            $this->logMessage($prefix . $key . ' = ' . $value);
                        }
                    }
                }
            } else {
                $this->logMessage($prefix . $message);
            }
        }
    }

    public function logMessage($msg, $path = "") {
        if ($path == "") {
            $path = plugin_dir_path(__FILE__);
        }
        $logfile = $path . "log\\debug.log";
        $msg = date('Y-m-d H:i:s') . ": " . $msg;
        error_log($msg . "\n", 3, $logfile);
    }

    function array_clone($array) {
        return array_map(function ($element) {
            return ((is_array($element))
                ? call_user_func(__FUNCTION__, $element)
                : ((is_object($element))
                    ? clone $element
                    : $element
                )
            );
        }, $array);
    }

    public function clearLog($path) {
        $logfile = $path . "log\\debug.log";
        file_put_contents($logfile, "");
    }


}

