<?php

/**
 * @author marcus
 */
class OpenWeatherService {
    /** 
     * @var string
     */
    public $endpoint = 'http://api.openweathermap.org';

    /** 
     * @var string
     */
    public $key = 'oawne';

    /** 
     * Default to Celsius.
     *
     * @var string
     */
    public $units = 'metric';

    /**
     * @return object
     */
    public function forecastFor($location) {
        $params = array(
            'appid' => $this->key,
        );
        
        if (is_numeric($location)) {
            $params['id'] = $location;
        } else {
            $params['q'] = $location;
        }
        $units = $this->units;
		if ($units === 'metric' || $units === 'imperial') {
			$params['units'] = $units;
		} else if ($units) {
            throw new Exception('Invalid unit. Only "metric" and "imperial" is supported.');
        }

        //
        // OpenWeatherMap is in certain instances, not providing the 'list' data. (ie. one minute, you get 'list' data with a certain query, the next you dont.)
        //
        // Solution:
        // ---------
        // 1.) Query it once with a units parameter ('metric'). If the 'list' data is empty, fallback to next step, otherwise return data.
        // 2.) Query it WITHOUT the units parameter. If the list data is not empty, convert from kelvins to celsius and return data, otherwise fallback to next step.
        // 3.) Query with /data/2.5/forecast/daily. Returns slightly different data. No failsafe at this point and hopefully shouldn't need one.
        //

        // If 5 day, 3 hour feed, and providing a metric
        // @source https://web.archive.org/web/20160510222446/http://openweathermap.org/forecast5
        $forecast = null;
        if (isset($params['units']))
        {
            $data = $this->request('/data/2.5/forecast', $params);
            if ($data && is_array($data->list) && $data->list) {
                $forecast = $data->list[0];
            }
        }

        // Remove metric (if one was set) and query again, as the API doesn't support other metrics
        // for certain station requests.
        //
        // @source https://openweathermap.desk.com/customer/portal/questions/16305313-units-parameter-doesn-t-work-with-http-api-openweathermap-org-data-2-5-station-urls-
        //
        $convertFromKelvins = false;
        if ($forecast === null)
        {
            $fallbackParams = $params;
            unset($fallbackParams['units']);
            $data = $this->request('/data/2.5/forecast', $fallbackParams);
            $convertFromKelvins = true;
            if ($data && is_array($data->list) && $data->list) {
                $forecast = $data->list[0];
            }
        }

        if ($forecast) 
        {
            $forecast->name = isset($data->city) ? $data->city->name : '';

            $today = date('d');
            $max = $forecast->main->temp_max;
            foreach ($data->list as $daytime) {
                $day = date('d', $daytime->dt);
                if ($day != $today) {
                    continue;
                }
                if ($daytime->main->temp_max > $max) {
                    $max = $daytime->main->temp_max;
                }
            }
            $forecast->main->temp_max = $max;
            if ($convertFromKelvins && $units)
            {
                switch ($units) 
                {
                    case 'metric':
                        // Conversion from Kelvins to Celsius is simply: $celsius = $kelvins - 273.15
                        $forecast->main->temp -= 273.15; 
                        $forecast->main->temp_min -= 273.15; 
                        $forecast->main->temp_max -= 273.15; 
                    break;

                    default:
                        throw new Exception('Have not implemented a conversion from kelvins to unit "'.$units.'"');
                    break;
                }
            }
            return $forecast;
        }

        // Final fallback to a 16 day, daily feed.
        // @source https://web.archive.org/web/20160511043644/http://openweathermap.org/forecast16
        $params['cnt'] = 5; // 5 days
        $data = $this->request('/data/2.5/forecast/daily', $params);

        if (!$data || !is_array($data->list) || !$data->list) {
            return null;
        }
        $forecast = $data->list[0];
        $forecast->name = isset($data->city) ? $data->city->name : '';

        $today = date('d');
        $max = $forecast->temp->max;
        foreach ($data->list as $daytime) {
            $day = date('d', $daytime->dt);
            if ($day != $today) {
                continue;
            }
            if ($daytime->temp->max > $max) {
                $max = $daytime->temp->max;
            }
        }
        $forecast->temp->max = $max;

        // Add backwards compatibility with '/data/2.5/forecast' API call.
        // @source http://openweathermap.org/forecast5
        if (!isset($forecast->main))
        {
            $forecast->main = new stdClass;
            foreach ($forecast->temp as $k => $v) {
                // Add $forecast->main->temp_day from $forecast->temp->day, and etc. 
                $forecast->main->{'temp_'.$k} = $v;
            }
            // Unsure of what 'temp' represents in original API call and documentation doesn't help.
            // assuming it's just the daytime temperature.
            $forecast->main->temp = $forecast->temp->day;
        }

        return $forecast;
    }

    /**
     * @return array
     */
    private function request($apiURLPostfix, $params) {
        $service = new RestfulService($this->endpoint, 1800);
        $service->setQueryString($params);
        
        $response = $service->request($apiURLPostfix);
        if ($response->isError()) {
            user_error(__CLASS__.' error with response. -- '.$response->getBody(), E_USER_WARNING);
            return null;
        }
        $data = json_decode($response->getBody());
        if (!isset($data->list)) {
            user_error(__CLASS__.' is missing list property from json feed.', E_USER_WARNING);
            return null;
        }
        if (!is_array($data->list)) {
            user_error(__CLASS__.' expected "list" property from json feed to be an array.', E_USER_WARNING);
            return null;
        }
        //$data->response = $response; // Debug
        return $data;
    }
}
