<?php
/**
 * A Wordpress weather plugin with OpenWeatherMap API
 *
 * @package   lp_weather
 * @author    Binh Tran <tranthienbinh1989@gmail.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2017 Lonely Planet
 *
 * @wordpress-plugin
 * Plugin Name:       Lonely Planet Weather
 * Plugin URI:        https://github.com/tranthienbinh1989/lp-weather
 * Description:       A weather plugin with OpenWeatherMap API
 * Version:           1.0.0
 * Author:            Binh Tran
 * Author URI:        https://github.com/tranthienbinh1989
 * Text Domain:       plugin-name-locale
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/tranthienbinh1989/lp-weather
 */

// SETTINGS
$lp_weather_sizes = apply_filters( 'lp_weather_sizes' , array( 'tall', 'wide' ) );


// SETUP
function lp_weather_setup()
{
    add_action(	'admin_menu', 'lp_weather_setting_page_menu' );
}
add_action('plugins_loaded', 'lp_weather_setup', 99999);



// ENQUEUE CSS
function lp_weather_wp_head( $posts )
{
    wp_enqueue_style( 'lp-weather', plugins_url( '/lp-weather.css', __FILE__ ) );

    $use_google_font = apply_filters('lp_weather_use_google_font', true);
    $google_font_queuename = apply_filters('lp_weather_google_font_queue_name', 'opensans-googlefont');

    if( $use_google_font )
    {
        wp_enqueue_style( $google_font_queuename, 'https://fonts.googleapis.com/css?family=Open+Sans:400,300' );
        wp_add_inline_style( 'lp-weather', ".lp-weather-wrap { font-family: 'Open Sans', sans-serif;  font-weight: 400; font-size: 14px; line-height: 14px; } " );
    }
}
add_action('wp_enqueue_scripts', 'lp_weather_wp_head');



//THE SHORTCODE
add_shortcode( 'lp-weather', 'lp_weather_shortcode' );
function lp_weather_shortcode( $atts )
{
    return lp_weather_logic( $atts );
}


// THE LOGIC
function lp_weather_logic( $atts )
{
    $dt_today = date( 'Ymd', current_time( 'timestamp', 0 ) );

    $rtn 						= "";
    $weather_data				= array();
    $city = "Nashville, TN";
    $user_ip = getUserIP();
    $ipInfo = json_decode(file_get_contents("http://ipinfo.io/{$user_ip}/json"));
    if (array_key_exists("city", $ipInfo)) {
        $city = $ipInfo->city;
    }

    $location 					= isset($atts['location']) ? $atts['location'] : $city;
    $units 						= (isset($atts['units']) AND strtoupper($atts['units']) == "C") ? "metric" : "imperial";
    $days_to_show 				= 5;
    $text_color					= '#ffffff';
    $locale						= 'en';

    // DISPLAY SYMBOL
    $units_display_symbol = apply_filters('lp_weather_units_display', '&deg;' );
    if( isset($atts['units_display_symbol']) ) $units_display_symbol = $atts['units_display_symbol'];


    // NO LOCATION, ABORT ABORT!!!1!
    if( !$location ) return lp_weather_error();


    //FIND AND CACHE CITY ID
    if( is_numeric($location) )
    {
        $city_name_slug 			= sanitize_title( $location );
        $api_query					= "id=" . urlencode($location);
    }
    else
    {
        $city_name_slug 			= sanitize_title( $location );
        $api_query					= "q=" . urlencode($location);
    }


    // OVERRIDE WITH LONG LAT, WHEN AVAILABLE
    if( isset($atts['lat']) AND isset($atts['lon']) )
    {
        $city_name_slug = str_replace(".","-", $atts['lat']) . "-" . str_replace(".","-", $atts['lon']);
        $api_query = "lat=" . $atts['lat'] . "&lon=" . $atts['lon'];
    }


    // TRANSIENT NAME
    $weather_transient_name 		= 'lp_' . $city_name_slug . "_" . $days_to_show . "_" . strtolower($units) . '_' . $locale;

    // APPID
    $appid_string = '';
    $appid = apply_filters( 'lp_weather_appid', lp_get_appid() );
    if($appid) $appid_string = '&APPID=' . $appid;


    // GET WEATHER DATA
    if( get_transient( $weather_transient_name ) )
    {
        $weather_data = get_transient( $weather_transient_name );
    }
    else
    {
        $weather_data['now'] = array();
        $weather_data['forecast'] = array();

        // NOW
        $now_ping = "http://api.openweathermap.org/data/2.5/weather?" . $api_query . "&lang=" . $locale . "&units=" . $units . $appid_string;
        $now_ping_get = wp_remote_get( $now_ping );


        // PING URL ERROR
        if( is_wp_error( $now_ping_get ) )  return lp_weather_error( $now_ping_get->get_error_message()  );


        // GET BODY OF REQUEST
        $city_data = json_decode( $now_ping_get['body'] );

        if( isset($city_data->cod) AND $city_data->cod == 404 )
        {
            return lp_weather_error( $city_data->message );
        }
        else
        {
            $weather_data['now'] = $city_data;
        }


        // FORECAST
        $forecast_ping = "http://api.openweathermap.org/data/2.5/forecast/daily?" . $api_query . "&lang=" . $locale . "&units=" . $units ."&cnt=7" . $appid_string;
        $forecast_ping_get = wp_remote_get( $forecast_ping );

        if( is_wp_error( $forecast_ping_get ) )
        {
            return lp_weather_error( $forecast_ping_get->get_error_message()  );
        }

        $forecast_data = json_decode( $forecast_ping_get['body'] );

        if( isset($forecast_data->cod) AND $forecast_data->cod == 404 )
        {
            return lp_weather_error( $forecast_data->message );
        }
        else
        {
            $weather_data['forecast'] = $forecast_data;
        }

        if($weather_data['now'] OR $weather_data['forecast'])
        {
            set_transient( $weather_transient_name, $weather_data, apply_filters( 'lp_weather_cache', 1800 ) );
        }
    }



    // NO WEATHER
    if( !$weather_data OR !isset($weather_data['now'])) return lp_weather_error();



    // TODAYS TEMPS
    $today 			= $weather_data['now'];
    $today_temp 	= isset($today->main->temp) ? round($today->main->temp) : false;


    // GET TODAY FROM FORECAST IF AVAILABLE
    if( isset($weather_data['forecast']) AND isset($weather_data['forecast']->list) AND isset($weather_data['forecast']->list[0]) )
    {
        $forecast_today = $weather_data['forecast']->list[0];
        $today_high = round($forecast_today->temp->max);
        $today_low = round($forecast_today->temp->min);
    }
    else
    {
        $today_high = isset($today->main->temp_max) ? round($today->main->temp_max) : false;
        $today_low 	= isset($today->main->temp_min) ? round($today->main->temp_min) : false;
    }


    // TEXT COLOR
    if( substr(trim($text_color), 0, 1) != "#" ) $text_color = "#" . $text_color;


    // BACKGROUND DATA, CLASSES AND OR IMAGES
    $background_classes = array();
    $background_classes[] = "lp-weather-wrap";
    $background_classes[] = "lpcf";
    $background_classes[] = "lp_wide";

    $background_classes[] = "temp1";


    // DATA
    $header_title = $today->name;


    // WIND
    $wind_label = array ( __('N', 'lp-weather'), __('NNE', 'lp-weather'), __('NE', 'lp-weather'), __('ENE', 'lp-weather'), __('E', 'lp-weather'), __('ESE', 'lp-weather'), __('SE', 'lp-weather'), __('SSE', 'lp-weather'), __('S', 'lp-weather'), __('SSW', 'lp-weather'), __('SW', 'lp-weather'), __('WSW', 'lp-weather'), __('W', 'lp-weather'), __('WNW', 'lp-weather'), __('NW', 'lp-weather'), __('NNW', 'lp-weather') );

    $wind_direction = false;
    if( isset($today->wind->deg) ) $wind_direction = apply_filters('lp_weather_wind_direction', $wind_label[ fmod((($today->wind->deg + 11) / 22.5),16) ]);


    // ADD WEATHER CONDITIONS CLASSES TO WRAP
    if( isset($today->weather[0]) )
    {
        $weather_code = $today->weather[0]->id;
        $weather_description_slug = sanitize_title( $today->weather[0]->description );

        $background_classes[] = "lp-code-" . $weather_code;
        $background_classes[] = "lp-desc-" . $weather_description_slug;
    }

    $background_class_string = @implode( " ", apply_filters( 'lp_weather_background_classes', $background_classes ));

    // DISPLAY WIDGET
    $rtn .= "<div id=\"lp-weather-{$city_name_slug}\" class=\"{$background_class_string}\">";

    $rtn .= "<div class=\"lp-weather-header\">{$header_title}</div>";
    $rtn .= "<div class=\"lp-weather-current-temp\"><strong>{$today_temp}<sup>{$units_display_symbol}</sup></strong></div><!-- /.lp-weather-current-temp -->";

    if(isset($today->main) )
    {
        $wind_speed = isset($today->wind->speed) ? $today->wind->speed : false;

        $wind_speed_text 	= ( $units == "imperial" ) ? __('mph', 'lp-weather') : __('m/s', 'lp-weather');
        $wind_speed_obj = apply_filters('lp_weather_wind_speed', array(
            'text' => apply_filters('lp_weather_wind_speed_text', $wind_speed_text),
            'speed' => round($wind_speed),
            'direction' => $wind_direction ), $wind_speed, $wind_direction );

        // CURRENT WEATHER STATS
        $rtn .= '<div class="lp-weather-todays-stats">';
        if( isset($today->weather[0]->description) ) $rtn .= '<div class="lp_desc">' . $today->weather[0]->description . '</div>';
        if( isset($today->main->humidity) ) $rtn .= '<div class="lp_humidty">' . __('humidity:', 'lp-weather') . " " . $today->main->humidity . '%</div>';
        if( $wind_speed AND $wind_direction) $rtn .= '<div class="lp_wind">' . __('wind:', 'lp-weather') . ' ' .$wind_speed_obj['speed'] . $wind_speed_obj['text'] . ' ' .$wind_speed_obj['direction'] . '</div>';
        if( $today_high AND $today_low) $rtn .= '<div class="lp_highlow">' . __('H', 'lp-weather') . ' ' . $today_high . ' &bull; ' . __('L', 'lp-weather') . ' ' . $today_low . '</div>';
        $rtn .= '</div><!-- /.lp-weather-todays-stats -->';
    }

    if($days_to_show != "hide")
    {
        $rtn .= "<div class=\"lp-weather-forecast lp_days_{$days_to_show} lpcf\">";
        $c = 1;
        $forecast = $weather_data['forecast'];
        $days_to_show = (int) $days_to_show;
        $days_of_week = apply_filters( 'lp_weather_days_of_week', array( __('Sun' ,'lp-weather'), __('Mon' ,'lp-weather'), __('Tue' ,'lp-weather'), __('Wed' ,'lp-weather'), __('Thu' ,'lp-weather'), __('Fri' ,'lp-weather'), __('Sat' ,'lp-weather') ) );

        if(!isset($forecast->list)) $forecast->list = array();

        foreach( (array) $forecast->list as $forecast )
        {
            if( $dt_today >= date('Ymd', $forecast->dt)) continue;
            $forecast->temp = (int) $forecast->temp->day;
            $day_of_week = $days_of_week[ date('w', $forecast->dt) ];
            $rtn .= "
				<div class=\"lp-weather-forecast-day\">
					<div class=\"lp-weather-forecast-day-temp\">{$forecast->temp}<sup>{$units_display_symbol}</sup></div>
					<div class=\"lp-weather-forecast-day-abbr\">{$day_of_week}</div>
				</div>";
            if($c == $days_to_show) break;
            $c++;
        }
        $rtn .= "</div><!-- /.lp-weather-forecast -->";
    }

    $rtn .= "</div> <!-- /.lp-weather-wrap -->";
    return $rtn;
}


// RETURN ERROR
function lp_weather_error( $msg = false )
{
    if(!$msg) $msg = __('No weather information available', 'lp-weather');
    return apply_filters( 'lp_weather_error', "<!-- LonelyPlanet WEATHER ERROR: " . $msg . " -->" );
}

// GET APPID
function lp_get_appid()
{
    return defined('LP_WEATHER_APPID') ? LP_WEATHER_APPID : get_option( 'open-weather-key' );
}


// PING OPENWEATHER FOR OWMID
add_action( 'wp_ajax_lp_ping_owm_for_id', 'lp_ping_owm_for_id');
function lp_ping_owm_for_id( )
{
    $appid_string = '';
    $appid = apply_filters('lp_weather_appid', lp_get_appid());
    if($appid) $appid_string = '&APPID=' . $appid;

    $location = urlencode($_GET['location']);
    $units = $_GET['units'] == "C" ? "metric" : "imperial";
    $owm_ping = "http://api.openweathermap.org/data/2.5/find?q=" . $location ."&units=" . $units . "&mode=json" . $appid_string;
    $owm_ping_get = wp_remote_get( $owm_ping );
    header("Content-Type: application/json");
    echo $owm_ping_get['body'];
    die;
}

// GET USER IP
function getUserIP()
{
    $client  = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote  = $_SERVER['REMOTE_ADDR'];

    if(filter_var($client, FILTER_VALIDATE_IP))
    {
        $ip = $client;
    }
    elseif(filter_var($forward, FILTER_VALIDATE_IP))
    {
        $ip = $forward;
    }
    else
    {
        $ip = $remote;
    }

    return $ip;
}

// SETTINGS
require_once( dirname(__FILE__) . "/lp-weather-settings.php");
