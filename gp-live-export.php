<?php
/*
Plugin Name: GP Live Export
Description: Export translation file from GlotPress to the site language directory by dashboard UI.
Author:      Mayo Moriyama
Version:     0.1

*/

class GPLE_Options_Page {

	private $message = [];
	private $project_name = '';

  /**
   * Constructor.
   */
  function __construct() {

			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'init',       array( $this, 'export'     ) );

  }

  /**
   * Registers a new settings page under Settings.
   */
  function admin_menu() {
      add_options_page(
					esc_html__( 'GP Live Export', 'gp-live-export' ),
          esc_html__( 'GP Live Export', 'gp-live-export' ),
          'manage_options',
          'gp-live-export',
          array(
              $this,
              'page_rending'
          )
      );
  }
	/**
	 * Get a translation set for a project.
	 *
	 * @param string $project Project path
	 * @param string $locale Locale slug
	 * @param string $set Set slug
	 * @return GP_Translation_Set|WP_Error Translation set if available, error otherwise.
	 */
	protected function get_translation_set( $project, $locale, $set = 'default' ) {
		$this->project = GP::$project->by_path( $project );
		if ( ! $this->project ) {
			return new WP_Error( 'gp_set_no_project', __( 'Project not found!', 'glotpress' ) );
		}
		$this->locale = GP_Locales::by_slug( $locale );
		if ( ! $this->locale ) {
			return new WP_Error( 'gp_set_no_locale', __( 'Locale not found!', 'glotpress' ) );
		}
		$this->translation_set = GP::$translation_set->by_project_id_slug_and_locale( $this->project->id, $set, $this->locale->slug );
		if ( ! $this->translation_set ) {
			return new WP_Error( 'gp_set_not_found', __( 'Translation set not found!', 'glotpress' ) );
		}
		return $this->translation_set;
	}

  /**
   * Export translation file.
   */
  function export() {

			if ( isset( $_GET["page"] ) && $_GET["page"] == 'gp-live-export'
			  && isset( $_GET["project"] ) && isset( $_GET["locale"] ) ) {
				$project = $_GET["project"];
				$locale  = $_GET["locale"];
			}
			else {
				return;
			}

			$translation_set = $this->get_translation_set( $project, $locale );
			if ( is_wp_error( $translation_set ) ) {
				$this->message[] = array(
					'status'  => 'error',
					'content' => $translation_set->get_error_message(),
					'path'    => $project
				);
				add_action( 'admin_notices', array( $this, 'admin_notice' ) );
				return;
			}
			$filters = array( 'status' => 'current_or_waiting_or_fuzzy_or_untranslated' );
			$entries = GP::$translation->for_translation( $this->project, $translation_set, 'no-limit', $filters );

			$types = array( 'po', 'mo' );
			foreach( $types as $type ) {

				$format = gp_array_get( GP::$formats, $type, null );

				if ( ! $format ) {
					$this->message[] = array(
						'status'  => 'error',
						'content' => __( 'No such format.', 'glotpress' ),
						'path'    => $project
					);
					add_action( 'admin_notices', array( $this, 'admin_notice' ) );
				}
				else {
					$print = $format->print_exported_file( $this->project, $this->locale, $translation_set, $entries );
					$name  = $this->get_file_name( $project, $locale, $type );
					$path  = path_join( WP_LANG_DIR, $name );
					$save  = $this->file_save( $print, $path );
					if( is_wp_error( $save ) ) {
						echo $save->get_error_message();
						$this->message[] = array(
							'status'  => 'error',
							'content' => $save->get_error_message(),
							'path'    => $name
						);
						add_action( 'admin_notices', array( $this, 'admin_notice' ) );
					}
					else {
						$this->message[] = array(
							'status' => 'success',
							'path'   => $path
						);
						$this->project_name = $project;
						add_action( 'admin_notices', array( $this, 'admin_notice' ) );
					}

				}
			}
  }

	/**
	 * Get a translation set for a project.
	 *
	 * @param string $print File content
	 * @param string $path  Target file path
	 * @return GP_Translation_Set|WP_Error Translation set if available, error otherwise.
	 */
	function file_save ( $print = '', $path = '' ) {
		if ( empty( $print ) || empty( $path ) ) {
			return new WP_Error( 'gp_live_export_no_param', __( 'No content or file path.', 'gp-live-export' ) );
		}
		$file_path = path_join( WP_LANG_DIR, $path );

		if ( !$file = fopen( $path, 'w' ) ) {
			return new WP_Error( 'gp_live_export_not_open', __( 'Cannot open file', 'gp-live-export' ) );
		}

		if ( fwrite( $file, $print ) === FALSE ) {
			return new WP_Error( 'gp_live_export_not_rewritable', __( 'Cannot write to file', 'gp-live-export' ) );
    }


		return fclose( $file );

	}

	/**
	 * Generate a file path to save a file.
	 *
	 * @param string $name   File name
	 * @param string $locale File locale
	 * @param string type    File type
	 * @return $path File path to save
	 */
	function get_file_name ( $name = '', $locale = 'ja', $type = 'po' ) {
		$name = str_replace( 'wp-', '', $name);
		$name = "$name-$locale.$type";
		return $name;
	}

	/**
	 * Settings page display callback.
	 */
	function page_rending () {
		echo '<div class="wrap">'
				.'<h1 class="wp-heading-inline">';
				esc_html_e( 'GP Live Export', 'gp-live-export' );
		echo '</h1>';

		echo '<hr class="wp-header-end">';

			$projects = GP::$project->top_level();

			if ( !empty( $projects ) ) :

				echo '<table class="wp-list-table widefat"><thead><tr>';
				echo '<th>'. esc_html__( 'Parent', 'gp-live-export' ) .'</th>';
				echo '<th>'. esc_html__( 'Name',   'gp-live-export' ) .'</th>';
				echo '<th>'. esc_html__( 'Export', 'gp-live-export' ) .'</th>';
				echo '</tr></thead><tbody>';

				foreach ( $projects as $project ):

					echo '<tr><td>'.__( 'N/A', 'gp-live-export' ).'</td><td>';
					gp_link_project( $project->path, esc_html( $project->name ) );
					echo '</td><td>';
						$this->gp_link_export_project( $project );
					echo '</td></tr>';

					if ( $sub_projects = $project->sub_projects() ) {
						foreach ( $sub_projects as $sub_project ):
							echo
								'<tr>'
									.'<td>'.$project->name.'</td>'
									.'<td>'
									;
									gp_link_project( $sub_project->path, esc_html( $sub_project->name ) );
							echo
									'</td>'
									.'<td>';
									$this->gp_link_export_project ( $sub_project );
							echo '</td>'
								.'</tr>'
								;
						endforeach;
					}

				endforeach;

				echo '</tbody></table>';
			endif;
			echo '</div>';
	}

	/**
	 * Generate list of translation project.
	 */
	function gp_link_export_project ( $project ) {
		if ( $translation_sets = GP::$translation_set->by_project_id( $project->id ) ) :

			foreach ( $translation_sets as $translation_set ):
				$locale    = $translation_set->locale;
				$admin_url = sprintf (
					'options-general.php?page=gp-live-export&locale=%1$s&project=%2$s',
						$locale,
						$project->path
				);

				printf ( '<a href="%1$s" class="button">%2$s</a>',
				esc_url ( admin_url ( $admin_url ) ),
				$locale
			 );
			endforeach;

		else :
			esc_html_e( 'No translation set found.', 'gp-live-export' );

		endif;

	}

	/**
	 * Display notification on Admin screen.
	 */
	function admin_notice() {
		foreach ( $this->message as $message ) {
			?>
			<div class="notice notice-<?php echo $message['status']; ?> is-dismissible">
					<p><?php
					switch ( $message['status'] ) {
						case 'error':
							printf(
								/* Translators: %1$s: error sentence, %2$s: project name */
								esc_html__( '%1$s please try again: %2$s', 'gp-live-export' ),
								$message['content'],
								'<b>' . $message['path'] . '</b>'
							);
							break;
						case 'success':
							printf(
								esc_html__( "%s has been exported!", 'gp-live-export' ),
								'<b>' . $message['path'] . '</b>'
							);
							break;
					}
					?></p>
			</div>
			<?php
		}
	}
}

new GPLE_Options_Page;
