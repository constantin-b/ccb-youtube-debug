<?php 
/*
 Plugin Name: YouTube Hub plugin addon - debug utility
 Plugin URI: https://wpythub.com/
 Description: Debug plugin for YouTube Hub plugin
 Author: Constantin Boiangiu
 Version: 1.0.2
 Author URI: https://wpythub.com
 */

// No direct access
if( !defined( 'ABSPATH' ) ){
	die();
}

class CBC_YT_Debug{
	
	private $handle;
	
	/**
	 * Store custom post type class reference from main plugin 
	 * @var cbc_youtube_Post_Type
	 */
	private $cpt;
	
	public function __construct(){
		
		add_action( 'init', array( $this, 'init' ) );
		
		// add extra menu pages
		add_action( 'admin_menu', array( $this, 'menu_pages' ), 10 );
		
		// process errors sent by the plugin
		add_action( 'cbc_debug_message', array( $this, 'register_error' ), 10, 3 );
		add_action( 'shutdown', array( $this, 'write_log' ), 999999999 );
	}
	
	/**
	 * Action 'init' callback
	 */
	public function init(){
		if( is_admin() ){
			$this->plugin_update();
		}

		global $CBC_POST_TYPE;
		if( !$CBC_POST_TYPE ){
			return;
		}
		$this->cpt = $CBC_POST_TYPE;
	}
	
	/**
	 * Add debug page to main plugin menu
	 */
	public function menu_pages(){
		if( !$this->cpt ){
			return;
		}
		
		$debug = add_submenu_page(
			'edit.php?post_type=' . $this->cpt->get_post_type(),
			__('Debug', 'cbc_youtube'),
			__('Debug', 'cbc_youtube'),
			'manage_options',
			'cbc_debug',
			array( $this, 'debug_page' ) );
		
		add_action( 'load-' . $debug, array( $this, 'debug_onload' ) );
		
	}
	
	/**
	 * Debug plugin menu page onLoad callback
	 */
	public function debug_onload(){
		
		wp_enqueue_style(
			'cbc_debug_css',
			plugin_dir_url( __FILE__ ) . 'assets/css/style.css'				
		);
		
		// clear log
		if( isset( $_GET['cbc_nonce'] ) ){
			check_admin_referer( 'cbc_reset_error_log', 'cbc_nonce' );
			$file = plugin_dir_path( __FILE__ ) . 'import_log';
			$handle = fopen( $file, 'w' );
			fclose( $handle );
			wp_redirect( str_replace( '&#038;' , '&', menu_page_url( 'cbc_debug', false ) ) );
		}
		
	}
	
	/**
	 * Output debug page
	 */
	public function debug_page(){
		$data = $this->gather_data();
		
?>
<div class="wrap">
	<h1><?php _e( 'Plugin information', 'cbc_youtube' );?></h1>
	<table class="cvm-debug widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'WordPress environment', 'cbc_youtube' );?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach( $data['wp'] as $d ):?>
			<tr>
				<td class="label"><?php echo $d['label'];?></td>
				<td><?php echo $d['value'];?></td>
			</tr>
			<?php endforeach;?>
		</tbody>
	</table>
	<br />
	<table class="cvm-debug widefat">
		<thead>
			<tr>
				<th colspan="2"><?php _e( 'Plugin', 'cbc_youtube' );?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach( $data['plugin'] as $d ):?>
			<tr>
				<td class="label"><?php echo $d['label']; ?>:</td>
				<td><?php echo $d['value']; ?></td>
			</tr>
			<?php endforeach;?>
		</tbody>
	</table>
	<br />
	<table class="cvm-debug widefat">
		<thead>
			<tr>
				<th><?php _e( 'Import log', 'cbc_youtube' );?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<?php 
						$file = plugin_dir_path( __FILE__ ) . 'import_log';
						if( !file_exists( $file ) || filesize( $file ) == 0 ){
							_e( 'No imports registered.', 'cbc_youtube' );
						}else{
							$handle = fopen( $file , 'r' );
							$content = fread( $handle, filesize( $file ) );
							fclose( $handle );
					?>
							<textarea id="cvm-debug-box" style="width:100%; height:300px;"><?php echo $content;?></textarea>
							<?php
								$url = menu_page_url( 'cbc_debug', false );
								$nonce = wp_create_nonce( 'cbc_reset_error_log' );
							?>							
							<a class="button" href="<?php echo $url . '&cbc_nonce=' . $nonce;?>"><?php _e( 'Clear log', 'cbc_youtube' );?></a>
					
					<?php }?>
				</td>
			</tr>
		</tbody>
	</table>
</div>
<script language="javascript">
var textarea = document.getElementById('cvm-debug-box');
textarea.scrollTop = textarea.scrollHeight;
</script>
<?php
	}
	
	/**
	 * Store errors in error log
	 * @param WP_Error $error
	 */
	public function register_error( $message, $separator, $data ){
		
		$error_log = plugin_dir_path( __FILE__ ) . 'import_log';
		//*
		if( file_exists( $error_log ) && filesize( $error_log ) >= pow(1024, 2) ){
			$filename = wp_unique_filename( plugin_dir_path( __FILE__ ), 'import_log' );
			$result = rename( $error_log , plugin_dir_path( __FILE__ ) . $filename );
			if( !$result ){
				//@todo do something in case the file could not be renamed
			}
		}
		//*/
		
		if( !$this->handle ){
			$this->handle = fopen( $error_log, "a" );
			if( false === $this->handle ){
				return;
			}
		}	
		
		$log_entry = sprintf(
			__( '[%s] %s', 'ccb_youtube' ),
			date( 'M/d/Y H:i:s' ),
			$message
			//"\n" . print_r( $data, true )
		);
			
		fwrite( $this->handle, $log_entry ."\n" );		
	}
	
	public function write_log(){
		if( $this->handle ){
			fclose( $this->handle );
		}	
	}
	
	/**
	 * Gather data about the plugin and WP to output it into the debug page
	 */
	private function gather_data(){
		
		global $CBC_POST_TYPE;
		$last_import = $CBC_POST_TYPE->__get_importer()->get_queue()->get_import_status();
		
		$data = array();
		$data['wp'] = array(
			'version' => array(
				'label' => __( 'WordPress version', 'cbc_youtube' ),
				'value' => get_bloginfo( 'version' )
			),
			'multisite' => array(
				'label' => __( 'WP Multisite', 'cbc_youtube' ),
				'value' => ( is_multisite() ? __( 'Yes', 'cbc_youtube' ) : __( 'No', 'cbc_youtube' ) )
			),
			'debug' => array(
				'label' => __( 'WordPress debug', 'cbc_youtube' ),
				'value' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'On', 'cbc_youtube' ) : __( 'Off', 'cbc_youtube' ) )
			),
			'cron' => array(
				'label' => __( 'WP Cron', 'cbc_youtube' ),
				'value' => ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Not allowed', 'cbc_youtube' ) : __( 'Allowed', 'cbc_youtube' ) )
			)			
		);
		
		$data['plugin'] = array(
			
			'last_import' => array(
				'label' => __( 'Last automatic import', 'cbc_youtube' ),
				'value' => sprintf( 
						'Post id: %s <br />Server time: %s <br> Import time: %s <br> Currently running: %s', 
						$last_import['post_id'],
						date( 'M/d/Y H:i:s' ),
						date( 'M/d/Y H:i:s', $last_import['time'] ),
						$last_import['running_update'] ? 'Yes' : 'No' 
				)
			)
			
		);
		
		return $data;
	}

	/**
	 * Plugin automatic update
	 */
	private function plugin_update(){
	    if( !class_exists( 'CBC_Theme_Compatibility_Update_Plugin' ) ) {
		    require_once plugin_dir_path( __FILE__ ) . 'includes/libs/class-update-plugin.php';
	    }

		$plugin 	= plugin_basename( __FILE__ );
		$slug 		= dirname( $plugin );

		new CBC_Theme_Compatibility_Update_Plugin( 'https://updates.wpythub.com/plugins/ccb-youtube-debug/?update_notification=1', __FILE__ );
		new CBC_Theme_Compatibility_Plugin_Update_Details( 'https://updates.wpythub.com/plugins/ccb-youtube-debug/?plugin_details=1', $plugin, $slug );
	}
	
}
new CBC_YT_Debug();