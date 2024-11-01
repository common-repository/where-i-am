<?php
/*
Plugin Name: Where I Am
Plugin URI: http://travelllll.com/where-i-am/
Description: Display where I am!
Author: Travelllll.com
Version: 0.1.4
Author URI: http://travelllll.com
*/

function where_i_am($type) {
	$location = get_option('wiam-location');
	if(empty($location)) {
		wiam_hourly();
		$location = get_option('wiam-location');
	}
	if(!is_array($location)) {
		$location = unserialize($location);
	}
	switch($type) {
		case 'location':
			$format = get_option('wiam-location-as');
			if(!empty($format)) {
				switch($format) {
					case 'city, country':
						if($location['country'] == 'United Kingdom') {
							$location['country'] = 'UK';
						} elseif($location['country'] == 'United States') {
							$location['country'] = 'US';
						}
						echo $location['city'].', '.$location['country'];
					break;
					case 'city':
						echo $location['city'];
					break;
					case 'country':
						if($location['country'] == 'United Kingdom') {
							$location['country'] = 'The UK';
						} elseif($location['country'] == 'United States') {
							$location['country'] = 'The US';
						}
						echo $location['country'];
					break;
				}
			}
		break;
		case 'weather':
			echo get_option('wiam-weather-'.$location['weather']);
		break;
		case 'weatherclass':
			echo $location['weather'];
		break;
		case 'gps':
			echo $location['coord']['lat'].','.$location['coord']['lng'];
		break;
	}
}

add_action('wiam_hourly', 'wiam_hourly');

function wiam_admin_init() {
	wiam_register_settings();
}

add_action('widgets_init', 'wiam_widget');

function wiam_widget() {
	register_widget('WIAM_Widget');
}

function wiam_init() {
	wp_register_sidebar_widget(
		'wiam_widget',
		'Where I Am',
		'wiam_widget',
		array(
			'description' => 'Output current Gowalla location as well as weather'
		)
	);
}

function wiam_hourly() {
	if(get_option('wiam-gowalla-username') != '' && get_option('wiam-api-key') != '') {
		$url ='http://api.gowalla.com/users/'.get_option('wiam-gowalla-username').'/stamps?limit=1';
		if(function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Accept: application/json',
				'X-Gowalla-API-Key: '.get_option('wiam-api-key')
			));
			$result = curl_exec($ch);
			if($result) {
				update_option('wiam-gowalla', serialize(json_decode($result, true)));
				$result = json_decode($result, true);
				if($result['stamps'][0]['spot']['lat'] && $result['stamps'][0]['spot']['lng']) {
					$yahoo = file_get_contents('http://where.yahooapis.com/geocode?q='.$result['stamps'][0]['spot']['lat'].','.$result['stamps'][0]['spot']['lng'].'&gflags=R');
					if($yahoo) {
						$yahoo = simplexml_load_string($yahoo);
						$location = array(
							'city'		=> (string) $yahoo->Result->city,
							'country'	=> (string) $yahoo->Result->country,
							'woeid'		=> (string) $yahoo->Result->woeid,
							'coord'		=> array(
								'lat'		=> $result['stamps'][0]['spot']['lat'],
								'lng'		=> $result['stamps'][0]['spot']['lng']
							)
						);
						$url = file_get_contents('http://weather.yahooapis.com/forecastrss?w='.$location['woeid']);
						$weather = simplexml_load_string($url);
						$yweather = $weather->channel->item->children('http://xml.weather.yahoo.com/ns/rss/1.0');
						$channel = array();
						foreach($yweather as $k => $v) {
							foreach($v->attributes() as $k2 => $v2) {
								$channel[$k][$k2] = $v2;
							}
						}
						$types = array(
							'storm'		=> array(
								0,1,2,3,4,37,38,39,45,47
							),
							'snow'		=> array(
								7,13,14,15,16,17,18,41,42,43
							),
							'rain'		=> array(
								5,6,8,9,10,11,12,35,40
							),
							'cloudy'	=> array(
								19,20,21,22,23,24,25,26,27,28,29,30,44
							),
							'sunny'		=> array(
								31,32,33,34,36
							)
						);
						if(in_array($channel['condition']['code'][0], $types['storm'])) {
							$location['weather'] = 'stormy';
						} elseif(in_array($channel['condition']['code'][0], $types['snow'])) {
							$location['weather'] = 'snowy';
						} elseif(in_array($channel['condition']['code'][0], $types['rain'])) {
							$location['weather'] = 'rainy';
						} elseif(in_array($channel['condition']['code'][0], $types['cloudy'])) {
							$location['weather'] = 'cloudy';
						} elseif(in_array($channel['condition']['code'][0], $types['sunny'])) {
							$location['weather'] = 'sunny';
						} else {
							$location['weather'] = 'mysterious';
						}
						update_option('wiam-location', serialize($location));
					}					
				}		
			}
		}
	}
}

function wiam_activation() {
	wiam_hourly();
	wiam_register_settings();
	update_option('wiam-api-key', 'fa574894bddc43aa96c556eb457b4009');
	update_option('wiam-location-as', 'city, country');
	update_option('wiam-weather-rainy', 'it\'s a bit cold and wet');
	update_option('wiam-weather-sunny', 'it\'s sunny and awesome!');
	update_option('wiam-weather-snowy', 'white stuff is falling from the sky!');
	update_option('wiam-weather-stormy', 'there\'s a storm passing through');
	update_option('wiam-weather-cloudy', 'it\'s a bit cloudy today');
	update_option('wiam-widget-text', 'Right now I\'m in %location% and %weather%');
	wp_schedule_event(time(), 'hourly', 'wiam_hourly');
}

function wiam_register_settings() {
	register_setting('wiam', 'wiam-location-as');
	register_setting('wiam', 'wiam-weather-rainy');
	register_setting('wiam', 'wiam-weather-sunny');
	register_setting('wiam', 'wiam-weather-snowy');
	register_setting('wiam', 'wiam-weather-stormy');
	register_setting('wiam', 'wiam-weather-cloudy');
	register_setting('wiam', 'wiam-widget-text');
	register_setting('wiam', 'wiam-gowalla-username');
	register_setting('wiam', 'wiam-api-key');
}

function wiam_settings() {
	add_options_page('Where I Am', 'Where I Am', 'manage_options', 'wiam-settings', 'wiam_settings_page');
}

function wiam_settings_page() {
	if(isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') {
		wiam_hourly();
	}
	?>
<div class="wrap">
<h2>Where I Am</h2>
<style>
.form-table label {
display: inline-block;
width: 100px; 
}
</style>
<form method="post" action="options.php">
    <?php settings_fields('wiam'); ?>
    <table class="form-table">
    
        <tr valign="top">
        <th scope="row">Gowalla username</th>
        <td>
			<input type="text" name="wiam-gowalla-username" value="<?=get_option('wiam-gowalla-username');?>" />
		</td>
        </tr>
    
        <tr valign="top">
        <th scope="row">Gowalla API key</th>
        <td>
			<input type="text" name="wiam-api-key" value="<?=get_option('wiam-api-key');?>" />
		</td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Show my location as</th>
        <td>
			<label>City, Country</label> <input type="radio" name="wiam-location-as" value="city, country"<?=get_option('wiam-location-as') == 'city, country' ? ' checked' : '';?> />
			<br />
			<label>City</label> <input type="radio" name="wiam-location-as" value="city"<?=get_option('wiam-location-as') == 'city' ? ' checked' : '';?> />
			<br />
			<label>Country</label> <input type="radio" name="wiam-location-as" value="country"<?=get_option('wiam-location-as') == 'country' ? ' checked' : '';?> />
		</td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Show weather as</th>
        <td>
			<label>Rainy</label> <input type="text" name="wiam-weather-rainy" value="<?=get_option('wiam-weather-rainy');?>" />
			<br />
			<label>Sunny</label> <input type="text" name="wiam-weather-sunny" value="<?=get_option('wiam-weather-sunny');?>" />
			<br />
			<label>Snowy</label> <input type="text" name="wiam-weather-snowy" value="<?=get_option('wiam-weather-snowy');?>" />
			<br />
			<label>Stormy</label> <input type="text" name="wiam-weather-stormy" value="<?=get_option('wiam-weather-stormy');?>" />
			<br />
			<label>Cloudy</label> <input type="text" name="wiam-weather-cloudy" value="<?=get_option('wiam-weather-cloudy');?>" />
		</td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Widget text</th>
        <td>
			<textarea name="wiam-widget-text" cols="100" rows="5"><?=get_option('wiam-widget-text');?></textarea>
		</td>
        </tr>
         
    </table>
    
    <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
    </p>

</form>
</div>
	<?php
}

class WIAM_Widget extends WP_Widget {
	
	public function WIAM_Widget() {
		$widget_ops = array('description' => 'Output current gowalla location as well as weather');
		$control_ops = array();
		$this->WP_Widget('wiam-widget', 'WIAM Widget', $widget_ops, $control_ops );
	}
	
	public function widget($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', $instance['title'] );
		echo $before_widget;
		if($title) {
			echo $before_title.$title.$after_title;
		}
		$location = get_option('wiam-location');
		if(empty($location)) {
			wiam_hourly();
			$location = get_option('wiam-location');
		}
		$location = unserialize($location);
		$format = get_option('wiam-location-as');
		$widget = get_option('wiam-widget-text');
		if(!empty($widget)) {
			switch($format) {
				case 'city, country':
					if($location['country'] == 'United Kingdom') {
						$location['country'] = 'UK';
					} elseif($location['country'] == 'United States') {
						$location['country'] = 'US';
					}
					$widget = str_replace('%location%', $location['city'].', '.$location['country'], $widget);
				break;
				case 'city':
					$widget = str_replace('%location%', $location['city'], $widget);
				break;
				case 'country':
					if($location['country'] == 'United Kingdom') {
						$location['country'] = 'The UK';
					} elseif($location['country'] == 'United States') {
						$location['country'] = 'The US';
					}
					$widget = str_replace('%location%', $location['country'], $widget);
				break;
			}
			if($location['weather'] !== 'mysterious') {
				$widget = str_replace('%weather%', get_option('wiam-weather-'.$location['weather']), $widget);
			} else {
				$widget = str_replace('%weather%', 'there is a mysterious feel around me', $widget);
			}
			echo $widget;
		}
		echo $after_widget;
		
	}
	
	public function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);

		return $instance;
	}
	
	public function form($instance) {
		$defaults = array('title' => 'Where I Am');
		$instance = wp_parse_args((array) $instance, $defaults);
		?>
		<p>
			<label for="<?=$this->get_field_id('title');?>">Title:</label>
			<input id="<?=$this->get_field_id('title');?>" name="<?=$this->get_field_name('title');?>" value="<?=$instance['title'];?>" style="width:100%;" />
		</p>
		<?php
	}
	
}

if(is_admin()) {
	add_action('admin_init', 'wiam_admin_init');
	add_action('admin_menu', 'wiam_settings');
} else {
	add_action('init', 'wiam_init');
}

register_activation_hook(__FILE__, 'wiam_activation');