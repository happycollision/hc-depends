<?php
/*
Plugin Name: It All Depends
Description: Set plugin dependencies and alter load order accordingly
Author: Don Denton
Version: 1.0
Author URI: http://happycollision.com
Depends: Happy Collision Testing Functions
*/

//cover my bases with hcwarn() and hcprint()
if(!function_exists('hcwarn')) {
	function hcwarn() {error_log('Did not find ' . __FUNCTION__);}
}
if(!function_exists('hcprint')) {
	function hcprint() {error_log('Did not find ' . __FUNCTION__);}
}


// Action hook load-plugins.php is called BEFORE activated_plugin
add_action( 'extra_plugin_headers', 'hc_extra_plugin_headers' );
function hc_extra_plugin_headers( $headers ) {
	$headers['Depends'] = 'Depends';
	return $headers;
}

function happycol_run_deps($just_activated = NULL) {
	// $just_activated is not used, but for reference, it is the plugin that was just activated (or deactivated) that caused this function to fire.

	$get_plugins = get_plugins();
	
	//remove inactive plugins from list
	$active_plugins = get_option('active_plugins');
	foreach($get_plugins as $path => $data){
		$kill_if_false = array_search($path, $active_plugins);
		if($kill_if_false === FALSE){
			unset($get_plugins[$path]);
		}
	}

	//create array of name keys with path values
	$key_to_paths = array();
	foreach($get_plugins as $path => $plugin_data){
		$key_to_paths[$plugin_data['Name']] = $path;
	}
	
	//Change dependencies to paths
	foreach($get_plugins as &$plugin_data){
		$plugin_data['Depends'] = explode(',', $plugin_data['Depends']);
		$plugin_data['Depends'] = array_map('trim', $plugin_data['Depends']);
		foreach($plugin_data['Depends'] as $key_for_dependency => &$dependency_name){
			if($dependency_name == '') {
				unset($plugin_data['Depends'][$key_for_dependency]);
				continue;
			}
			if(array_key_exists($dependency_name, $key_to_paths)){
				$dependency_name = $key_to_paths[$dependency_name];
			}else{
				//The dependency doesn't even exist
				//throw up a warning that will be picked up later
				//$hc_all_depends_warning[] = $plugin_data['Name'] 
				hcwarn( 'The <strong>' . $plugin_data['Name'] . '</strong> plugin'
					. ' has a dependency that is not being met: ' 
					. '<strong>' . $dependency_name . '</strong>.'
					. ' This could cause unexpected results and/or break WordPress. Yep. Could be minor. Could be major.',TRUE);
				
				//remove dependency to keep things clean below
				$plugin_data['Depends'][$key_for_dependency] = 'nothing';
				unset($plugin_data['Depends'][$key_for_dependency]);
			}
		}
		unset($dependency_name);
	}
	unset($plugin_data);
	
	//hcwarn($get_plugins);
	
	//make a dependency array based on remaining deps
	$dependencies = array();
	foreach($get_plugins as $path => $plugin_data){
		if(count($plugin_data['Depends']) > 0){
			$dependencies[$path] = $plugin_data['Depends'];
		}
	}
	
	$old_active_plugins = $active_plugins;
	
	//hcwarn($active_plugins);
	$logical_errors_exist = happycol_arrange_deps($active_plugins, $dependencies);
	//hcwarn($active_plugins);

	if($logical_errors_exist){
		hcwarn('There is a logical error in your plugin dependencies. That means that two (or more) of your plugins are waiting for each other to load before they can work properly. This may not actually be problematic, depending on how the author wrote the plugin.', TRUE);
	}
	
	$array_diffs = array_diff_assoc($active_plugins, $old_active_plugins);
	if(count($array_diffs) > 0){
		update_option('active_plugins', $active_plugins);
		hcwarn('The <strong>It All Depends</strong> plugin took the liberty of rearranging the load order of your plugins so they would work better. The change may not take effect until your next page load. Here are the ones that needed shifting:' . hcprint($array_diffs, true));
	}
	
}


function happycol_arrange_deps(&$load_list, $dependencies_array){
	$original_load_list = $load_list;
	
	//sort dependencies first time
	foreach($original_load_list as $plugin_path){
		//Does it have dependencies?
		if(array_key_exists($plugin_path, $dependencies_array)){
			happycol_move_plugin_deps($load_list, $plugin_path, $dependencies_array[$plugin_path]);
		}
	}
	
	//check that the logic is correct second time
	$moved_again = FALSE;
	foreach($original_load_list as $plugin_path){
		//Does it have dependencies?
		if(array_key_exists($plugin_path, $dependencies_array)){
			$moved_again = happycol_move_plugin_deps($load_list, $plugin_path, $dependencies_array[$plugin_path]);
		}
		if($moved_again == TRUE){
			return $moved_again;
		}
	}

	return FALSE;
}
function happycol_move_plugin_deps(&$arrangeable_load_list, $plugin_path, $plugin_deps){
	$moved_stuff = FALSE;
	
	foreach($plugin_deps as $dep_path){
		$plugin_key = array_search($plugin_path, $arrangeable_load_list);
		$dep_key = array_search($dep_path, $arrangeable_load_list);
		
		if($plugin_key < $dep_key){
			// We need to move stuff. Move the dependency to the spot right before our needy plugin.
			array_splice($arrangeable_load_list, $dep_key, 1);
			array_splice($arrangeable_load_list, $plugin_key, 0, $dep_path);
			$moved_stuff = TRUE;
		}
	}
	
	return $moved_stuff;
}

add_action('load-plugins.php', 'happycol_run_deps');

