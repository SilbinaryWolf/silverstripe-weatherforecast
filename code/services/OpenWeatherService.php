<?php

/**
 * @author marcus
 */
class OpenWeatherService {
    public $endpoint = 'http://api.openweathermap.org';
    public $key = '';
    
    public function forecastFor($location) {
        $params = array(
            'appid' => $this->key,
        );
        
        if (is_numeric($location)) {
            $params['id'] = $location;
        } else {
            $params['q'] = $location;
        }
        
        $service = new RestfulService($this->endpoint, 1800);
        $service->setQueryString($params);
        
        $response = $service->request('/data/2.5/forecast');
        $forecast = null;
        if (!$response->isError()) {
            $data = json_decode($response->getBody());
            if (isset($data->list) && is_array($data->list) && count($data->list)) {
                $forecast = $data->list[0];
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
            }
        }
        
        return $forecast;
    }
}
