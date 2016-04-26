# SilverStripe weather forecast module

## Usage

In your project .yml config

```
---
Name: weather-config
After: weatherforecast/*
---
Injector:
  OpenWeatherService:
    properties:
      key: {your key here}
```

From your code, call the following. The LocationID will need to be looked up from the openweather site.

```
Requirements::css('weatherforecast/owfonts/css/owfont-regular.min.css');
$forecast = singleton('OpenWeatherService')->forecastFor($locationID);
```

## PLEASE NOTE 

The requirements of OpenWeatherMaps mean you _must_ provide attribution on your site for using the service. 