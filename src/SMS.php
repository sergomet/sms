<?php namespace Sergomet\Sms;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;

require_once(__DIR__.'/providers/BoomSMS.php');

class SMS
{

    const BOOM_SMS = 1;

    public $id;
    public $scope_field;
    public $scope_value;
    public $data;

    public function __construct()
    {
        $config = config('sms_providers');
        if ( ! isset($config['table']) || ! $config['table']) throw new \Exception('Providers table not found!');
        $this->table = $config['table'];
    }


    /**
     * Set filter scope
     */
    public function scope($field, $value) 
    {
        $this->scope_field = $field;
        $this->scope_value = $value;

        return $this;
    }

    /**
     * Set/Get data
     */
    public function data($data)
    {
        if ($data) $this->data = (object)$data;
        return $this->data;
    }

	/**
	 * Return providers list
	 * @return array
	 */
    public function providers()
    {
    	// fields
    	$providersList = collect();
    	$providersList->push(\BoomSMS::fields());

    	// db
    	$providersConfig = \DB::table($this->table);

        if ($this->scope_field) {
            $providersConfig->where($this->scope_field, $this->scope_value);
        }

        $providersConfig = $providersConfig->get();    

        // if ( count($providersConfig) < 1) return false;    

    	foreach ($providersList as $k => $provider) {

    		// associate with db values
    		foreach ($providersConfig as $providerDb) {

    			if ($providerDb->id != $provider->id) continue;

    			$configs = json_decode($providerDb->config, false);

    			// set db config values
    			if ($configs)
                foreach ($configs as $configKey => $configValue) {

    				if (!$configValue || $configValue == '') continue;

    				$providersList[$k]->config->{$configKey}->value = $configValue;

    			}

                // set db status
                $providersList[$k]->status = @$providerDb->status ? 1:null;
    		}
    	}

        // dd($providersList);

        return $providersList;

    }


    /**
     * Update provider
     */
    public function updateProvider($id, $data)
    {
        $id = (int)$id;
        $this->data($data);

        if ($id === static::BOOM_SMS) {
            $this->id = static::BOOM_SMS;
            $sms = new \BoomSMS($this);
            $sms->update();
        }
    }

    /**
     * Send SMS
     * - get active provider
     * - check credit
     * - send sms
     */
    public function send($number =null, $message =null)
    {	

        // active provider
        $provider = \DB::table($this->table);

        if ($this->scope_field) {
            $provider->where($this->scope_field, $this->scope_value);
        }

        $provider = $provider->where('status', 1)->first();

        if ( ! $provider || ! $provider->config) {
            throw new \Exception('SMS porvider not set!');
        }

        $this->id = $provider->id;        

        $config = json_decode($provider->config, true);
        $this->data($config);

    	$provider = new \BoomSMS($this);

    	try {
    		$provider->send($number, $message);
    	} catch ( \Exception $e) {
    		throw new \Exception('Message not sent: '. $e->getMessage());
    	}

    }

}
