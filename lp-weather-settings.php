<?php

// CREATE THE SETTINGS PAGE
function lp_weather_setting_page_menu()
{
	add_options_page( 'LonelyPlanet Weather ', 'LonelyPlanet Weather', 'manage_options', 'lp-weather', 'lp_weather_page' );
}

function lp_weather_page()
{
?>
<div class="wrap">
    <h2><?php _e('LonelyPlanet Weather Widget', 'lp-weather'); ?></h2>
    
    <form action="options.php" method="POST">
        <?php settings_fields( 'lp-basic-settings-group' ); ?>
        <?php do_settings_sections( 'lp-weather' ); ?>
        <?php submit_button(); ?>
    </form>
</div>
<?php
}


// SET SETTINGS LINK ON PLUGIN PAGE
function lp_weather_plugin_action_links( $links, $file )
{
	$appid = apply_filters( 'lp_weather_appid', lp_get_appid() );
	
	if( $appid )
	{
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=lp-weather' ) . '">' . esc_html__( 'Settings', 'lp-weather' ) . '</a>';
	}
	else
	{
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=lp-weather' ) . '">' . esc_html__( 'API Key Required', 'lp-weather' ) . '</a>';
	}
	
	if( $file == 'lp-weather/lp-weather.php' ) array_unshift( $links, $settings_link );
	
	return $links;
}
add_filter( 'plugin_action_links', 'lp_weather_plugin_action_links', 10, 2 );


add_action( 'admin_init', 'lp_weather_setting_init' );
function lp_weather_setting_init()
{
    register_setting( 'lp-basic-settings-group', 'open-weather-key' );

    add_settings_section( 'lp-basic-settings', '', 'lp_weather_api_keys_description', 'lp-weather' );
	add_settings_field( 'open-weather-key', __('OpenWeatherMaps APPID', 'lp-weather'), 'lp_weather_openweather_key', 'lp-weather', 'lp-basic-settings' );
}

function lp_weather_api_keys_description() { }

function lp_weather_openweather_key()
{
	if( defined('LP_WEATHER_APPID') )
	{
		echo "<em>" . __('Defined in wp-config', 'lp-weather-pro') . ": " . lp_weather_APPID . "</em>";
	}
	else 
	{
		$setting = esc_attr( apply_filters('lp_weather_appid', get_option( 'open-weather-key' )) );
		echo "<input type='text' name='open-weather-key' value='$setting' style='width:70%;' />";
		echo "<p>";
		echo " <a href='http://openweathermap.org/appid' target='_blank'>";
		echo __('Get your APPID', 'lp-weather');
		echo "</a>";
		echo "</p>";
	}
}
