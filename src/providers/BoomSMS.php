<?php 
use GuzzleHttp\Psr7;

use Sergomet\Sms\SMS as SMS;

class BoomSMS
{

	/**
	 * Once you have constructed your XML you just need to send it as POST data 
	 * @var string
	 */
	private $uri = 'http://www.boom-sms.co.uk/cgi-bin/sendsms.pl';


	/**
	 * End user's Username - obtained on registering on the Boom SMS website.
	 */
	private $account_id;

	/**
	 * user’s Password - obtained on registering on the Boom SMS website
	 */
	private $password;

	/**
	 * Optional. Max 20 Characters. Personal Identifier - If your application stores the username of the individual originating the
		SMS message you can populate this field with that name. This allows more detailed reporting of who is sending messages from a multi
		user application
	 */
	private $username;

	/**
	 * The destination Mobile Number. May be multiple
	 */
	private $mobile_to;

	/**
	 * Max 160 Characters. The SMS message.
	 */
	private $message;

	/**
	 * 1 = Yes 0 = No
	 */
	private $notify;

	/**
	Optional. This address is used to send notifications and other
	error/status messages back to the originator. If you send via
	email
	this address will override the email address contained in the
	email.
	If you send by HTTP and you do not supply this then notification
	messages will be sent to the email of the account holder address
	 */
	private $sender_email;

	/**
	 * SmsAccountUN: the login name of the subaccount you're retriving credits for.
	 */
	private $smsAccountUN;

	/**
	 * Refenrece SMS Class
	 */
	private $sms;

	/**
	 * Provider status 1/null
	 */
	private $status;

	/**
	 * Provider id
	 */
	private $id;


	public function __construct(SMS $sms)
	{
		$this->username = $sms->data->username;		
		$this->password = $sms->data->password;		
		$this->smsAccountUN = $sms->data->smsAccountUN;	
		$this->id = $sms->id;	

		$this->status = isset($sms->data->status) ? 1:null;		
		$this->sms = $sms;
	}

    public static function fields()
    {
    	$obj = new \Stdclass;
    	$obj->id = 1;
    	$obj->name = 'BoomSMS';
    	$obj->status = null;

    	$config = new \Stdclass;
    	
    	$username = new \Stdclass;
    	$username->name = 'username';
    	$username->value = null;
    	$username->type = 'text';
    	$config->username = $username;
    	
    	$password = new \Stdclass;
    	$password->name = 'password';
    	$password->value = null;
    	$password->type = 'password';
    	$config->password = $password;
    	
    	$smsAccountUN = new \Stdclass;
    	$smsAccountUN->name = 'smsAccountUN';
    	$smsAccountUN->value = null;
    	$smsAccountUN->type = 'text';
    	$config->smsAccountUN = $smsAccountUN;

    	$obj->config = $config;

    	return $obj;
    }

    public function send($to,$message)
    {

    	$this->mobile_to = $to;
    	$this->message = $message;
    	$body = $this->getXMLMessage();

    	$this->checkCredit();
    	// dd($body);

    	// check credit 
		$client = new \GuzzleHttp\Client();

		// Send an asynchronous request.
		$request = new \GuzzleHttp\Psr7\Request('POST', $this->uri);
		$promise = $client->postAsync($this->uri, compact('body'))
				->then(function ($res) {
		    		$string = $res->getBody();
		    		if (!$string == 'OK') throw new \Exception('Sent failed!');
				});
		$promise->wait();
    }


    /**
     * Gets the number of credits currently remaining on the specified account
     * @return int
     */
    public function checkCredit()
    {

    	$url = 'http://services.boom-sms.co.uk/BoomUserASP/Credit_Check/';
    	$url .='?Username=' .$this->username. '&Password=' .$this->password. '&SmsaccountUN=' .$this->smsAccountUN. '';
    	
    	$client = new \GuzzleHttp\Client();
		$res = $client->request('GET', $url);

		if ($res->getStatusCode() !== 200) {
			throw new \Exception('Get credit reponse failed!');	
		}

		$credit = $res->getBody()->read(4);
		$credit = (int)$credit;

		if ($credit < 1) {
			throw new \Exception('insufficient credits ' . $credit);	
		}

    }


    public function getXMLMessage()
    {
		$xml = \View::make('sms::xml.messages', [
			'account_id' => $this->account_id,
			'username' => $this->username,
			'password' => $this->password,
			'mobile_to' => $this->mobile_to,
			'message' => $this->message,
			'sender_email' => $this->sender_email,
			'notify' => $this->notify,
		]);

		return $xml->render();
    }

    /**
     * Update provider config
     */
    public function update()
    {
        $config = json_encode([
            'username' => $this->username,
            'password' => $this->password,
            'smsAccountUN' => $this->smsAccountUN,
        ]);
        $providerData = [
			'status' => $this->status,  
			'config' => $config,
			'name' => 'BoomSMS',
        ];

		$provider = $this->find_provider();

        if ( ! $provider) {
        	$providerData['id'] = $this->id;
        	if ($this->sms->scope_field) $providerData[$this->sms->scope_field] = $this->sms->scope_value;
        	\DB::table($this->sms->table)->insert($providerData);
        } else {
        	$query = \DB::table($this->sms->table)
        		->where('id', $this->id);
        	if ($this->sms->scope_field) $query->where($this->sms->scope_field, $this->sms->scope_value);
        	$query->update($providerData);   
        }    	
    }


    private function find_provider()
    {
		$provider = \DB::table($this->sms->table)
            ->where('id', $this->id);
        
        if ($this->sms->scope_field) $provider->where($this->sms->scope_field, $this->sms->scope_value);

        return $provider->first();

    }



}
