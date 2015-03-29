<?php

require_once 'modules/admin/models/FraudPlugin.php';
require_once 'library/CE/RestRequest.php';

class PluginFraudlabspro extends FraudPlugin
{
    function getVariables()
    {
        $variables = array(
            lang('Plugin Name')   => array(
                'type'          => 'hidden',
                'description'   => '',
                'value'         => lang('FraudLabs Pro'),
            ),
            lang('Enabled')       => array(
                'type'          => 'yesno',
                'description'   => lang('Setting allows FraudLabs Pro customers to check orders for fraud.'),
                'value'         => '0',
            ),
            lang('API Key')       => array(
                'type'          => 'text',
                'description'   => lang('Enter your API Key here.<br>You can obtain a license at <a href="http://www.fraudlabspro.com/?ref=1614" target="_blank">https://www.fraudlabspro.com/</a>'),
                'value'         => '',
            ),
            lang('Reject Free E-mail Service')       => array(
                'type'          => 'yesno',
                'description'   => lang('Setting allows you to reject any order using free E-mail services like Hotmail and Yahoo (free E-mail = higher risk).<br><b>NOTE: </b>Requires MaxMind'),
                'value'         => '0',
            ),
            lang('Reject Country Mismatch')       => array(
                'type'          => 'yesno',
                'description'   => lang('Setting allows you to reject any order where country of IP address does not match the billing address country (mismatch = higher risk).<br><b>NOTE: </b>Requires MaxMind'),
                'value'         => '1',
            ),
            lang('Reject Anonymous Proxy')       => array(
                'type'          => 'yesno',
                'description'   => lang('Setting allows you to reject any order where the IP address is an Anonymous Proxy (anonymous proxy = very high risk).<br><b>NOTE: </b>Requires MaxMind'),
                'value'         => '1',
            ),
            lang('Reject High Risk Country')       => array(
                'type'          => 'yesno',
                'description'   => lang('Setting allows you to reject any order where the country the IP is based from is considered a country where fraudulent order is likely.<br><b>NOTE: </b>Requires MaxMind'),
                'value'         => '0',
            ),
            lang('MaxMind Fraud Risk Score')       => array(
                'type'          => 'text',
                'description'   => lang('MaxMind risk score is based on known risk factors and their likelihood to indicate possible fraud. Select the threshold you want ClientExec to reject on. ( 0=low risk 100=high risk)<br><b>NOTE:</b> Requires MaxMind<br>To see how the fraud score is obtained visit <br><a href=http://www.maxmind.com/en/riskscore?rId=clientexec target=_blank>http://www.maxmind.com/en/riskscore</a>'),
                'value'         => 'none',
            ),
            lang('MaxMind Warning E-mail')       => array(
                'type'          => 'textarea',
                'description'   => lang('The E-mail address where a notification will be sent when the number of remaining queries reaches your MaxMind Low Query Threshold'),
                'value'         => '',
            ),
            lang('MaxMind Low Query Threshold')       => array(
                'type'          => 'text',
                'description'   => lang('A notification E-mail will be sent when the number of remaining queries reaches this value.'),
                'value'         => '10',
            ),
            lang('Show MaxMind Logo')       => array(
                'type'          => 'yesno',
                'description'   => lang('Setting this to YES will show the MaxMind fraud screening logo in the signup footer if credit card fraud detection or phone verification is turned on.'),
                'value'         => '1',
            ),
        );

        return $variables;
    }

    function grabDataFromRequest($request)
    {
        $ip = CE_Lib::getRemoteAddr();
        //get email custom id for user
        $query = "SELECT id FROM customuserfields WHERE type=".typeEMAIL;
        $result = $this->db->query($query);
        list($tEmailID) = $result->fetch();
        //get city custom id for user
        $query = "SELECT id FROM customuserfields WHERE type=".typeCITY;
        $result = $this->db->query($query);
        list($tCityID) = $result->fetch();
        //get state custom id for user
        $query = "SELECT id FROM customuserfields WHERE type=".typeSTATE;
        $result = $this->db->query($query);
        list($tStateID) = $result->fetch();
        //get country custom id for user
        $query = "SELECT id FROM customuserfields WHERE type=".typeCOUNTRY;
        $result = $this->db->query($query);
        list($tCountryID) = $result->fetch();
        //get zipcode custom id for user
        $query = "SELECT id FROM customuserfields WHERE type=".typeZIPCODE;
        $result = $this->db->query($query);
        list($tZipcodeID) = $result->fetch();
        //get phone custom id for user
        $query = "SELECT id FROM customuserfields WHERE type=".typePHONENUMBER;
        $result = $this->db->query($query);
        list($tPhoneNumberID) = $result->fetch();

        $this->input["ip"] = $ip;
        $this->input["city"] = $request['CT_'.$tCityID];
        $this->input["region"] = $request['CT_'.$tStateID];
        $this->input["postal"] = $request['CT_'.$tZipcodeID];
        $this->input["country"] = $request['CT_'.$tCountryID];
        $this->input["emailDomain"] = mb_substr(strstr($request['CT_'.$tEmailID],'@'),1);
        $this->input["phone"] = $request['CT_'.$tPhoneNumberID];
        $this->input["email"] = $request['CT_'.$tEmailID];

        if (!is_null($this->settings->get("plugin_".@$_REQUEST['paymentMethod']."_Accept CC Number"))
                && $this->settings->get("plugin_".@$_REQUEST['paymentMethod']."_Accept CC Number")) {
            $this->input["bin"] = mb_substr(@$_REQUEST[@$_REQUEST['paymentMethod'].'_ccNumber'],0,6);
        }
    }

    function execute()
    {
        $params['format']           = 'json';
        $params['ip']               = $_SERVER['REMOTE_ADDR'];
        $params['bill_city']        = $this->input['city'];
        $params['bill_state']       = $this->input['region'];
        $params['bill_zip_code']    = $this->input['postal'];
        $params['bill_country']     = $this->input['country'];
        $params['email_domain']     = $this->input['emailDomain'];
        $params['user_phone']       = $this->input['phone'];
        $params['email_hash']       = $this->hash($this->input['email']);
        if ( isset($this->input['bin']) ) {
            $params['bin_no']       = $this->input['bin'];
        }
        $params['session_id']       = session_id();

        $this->result = $this->makeRequest($params);
        return $this->result;
    }

    function makeRequest($params)
    {
        $apiKey = $this->settings->get('plugin_fraudlabspro_API Key');

        $query = '';
        foreach ( $params as $key => $value ) {
            $query .= '&' . $key . '=' . rawurlencode($value);
        }
        try {
            $request = new RestRequest('https://api.fraudlabspro.com/v1/order/screen?key=' . $apiKey . $query, 'GET');
            $request->execute();
            $result = $request->getResponseBody();
        } catch ( Exception $e ) {
            CE_Lib::log(1, 'Could not look up fraudlabspro order: ' . $e->getMessage());
        }
        return json_decode($result, true);
    }

    function hash($s)
    {
        $hash = 'fraudlabspro_' . $s;
        for($i=0; $i<65536; $i++) {
            $hash = sha1('fraudlabspro_' . $hash);
        }
        return $hash;
    }

    public function isOrderAccepted()
    {
        if ( $this->result['fraudlabspro_status'] == 'REJECT' ) {
            return false;
        }
        return true;
    }
}