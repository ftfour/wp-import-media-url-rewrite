<?php
/**
 * Plugin Name: WP Import Media URL Rewrite
 * Description: Rewrites image URLs to production during WordPress XML import, without importing media files. Intended for test and development environments. 
 * Version: 0.1.0
 * Requires PHP: 8.1
 * Requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

final class WP_Import_Images_Proxy {
	private static $attachment_urls  = array();
	private static $attachment_files = array();
	private static $prod_base_url;
	private static $test_hosts;
	private static $target_post_type;

	public static function bootstrap() {
		add_filter( 'import_allow_fetch_attachments', '__return_false', 100 );
		add_filter( 'wp_import_posts', array( __CLASS__, 'filter_posts' ), 10, 1 );
		add_filter( 'wp_import_post_data_raw', array( __CLASS__, 'filter_post_raw' ), 10, 1 );
		add_filter( 'wp_import_post_data_processed', array( __CLASS__, 'filter_post_processed' ), 10, 2 );
		add_filter( 'wp_import_post_meta', array( __CLASS__, 'filter_post_meta' ), 10, 3 );
	}

	public static function filter_posts( $posts ) {
		if ( ! self::ensure_import_context() || empty( $posts ) || ! is_array( $posts ) ) {
			return $posts;
		}

		foreach ( $posts as $post ) {
			self::capture_attachment( $post );
		}

		return $posts;
	}

	public static function filter_post_raw( $post ) {
		if ( ! self::ensure_import_context() || ! is_array( $post ) ) {
			return $post;
		}

		if ( ( $post['post_type'] ?? '' ) === 'attachment' ) {
			self::capture_attachment( $post );
			$post['status'] = 'auto-draft';
		}

		return $post;
	}

	public static function filter_post_processed( $postdata, $post ) {
		if ( ! self::ensure_import_context() || empty( $postdata ) || ! is_array( $postdata ) ) {
			return $postdata;
		}

		if ( ! self::is_target_post_type( $post ) || empty( self::$prod_base_url ) ) {
			return $postdata;
		}

		if ( ! empty( $postdata['post_content'] ) ) {
			$postdata['post_content'] = self::rewrite_content_images( $postdata['post_content'] );
		}

		return $postdata;
	}

	public static function filter_post_meta( $postmeta, $post_id, $post ) {
		if ( ! self::ensure_import_context() || ! self::is_target_post_type( $post ) ) {
			return $postmeta;
		}

		if ( ! is_array( $postmeta ) ) {
			$postmeta = array();
		}

		delete_post_meta( $post_id, '_thumbnail_id' );

		$new_meta = array();
		$thumb_id = 0;
		foreach ( $postmeta as $meta ) {
			if ( ! is_array( $meta ) || ! isset( $meta['key'] ) ) {
				$new_meta[] = $meta;
				continue;
			}
			if ( $meta['key'] === '_thumbnail_id' ) {
				$thumb_id = (int) $meta['value'];
				continue;
			}
			$new_meta[] = $meta;
		}

		if ( $thumb_id > 0 ) {
			$remote = self::resolve_attachment_url( $thumb_id );
			if ( $remote !== '' ) {
				$new_meta[] = array(
					'key'   => '_remote_thumbnail_url',
					'value' => $remote,
				);
			}
		}

		return $new_meta;
	}

	private static function capture_attachment( $post ) {
		if ( ! is_array( $post ) || ( $post['post_type'] ?? '' ) !== 'attachment' ) {
			return;
		}

		$attachment_id = isset( $post['post_id'] ) ? (int) $post['post_id'] : 0;
		if ( $attachment_id <= 0 ) {
			return;
		}

		$file = '';
		if ( ! empty( $post['postmeta'] ) && is_array( $post['postmeta'] ) ) {
			foreach ( $post['postmeta'] as $meta ) {
				if ( ! is_array( $meta ) || empty( $meta['key'] ) ) {
					continue;
				}
				if ( $meta['key'] === '_wp_attached_file' && ! empty( $meta['value'] ) ) {
					$file = (string) $meta['value'];
					break;
				}
			}
		}

		if ( $file !== '' ) {
			self::$attachment_files[ $attachment_id ] = $file;
		}

		$url = '';
		if ( ! empty( $post['attachment_url'] ) ) {
			$url = (string) $post['attachment_url'];
		} elseif ( ! empty( $post['guid'] ) ) {
			$url = (string) $post['guid'];
		} elseif ( $file !== '' ) {
			$url = $file;
		}

		$url = self::normalize_media_url( $url );
		if ( $url !== '' ) {
			self::$attachment_urls[ $attachment_id ] = $url;
		}
	}

	private static function resolve_attachment_url( $attachment_id ) {
		if ( isset( self::$attachment_urls[ $attachment_id ] ) ) {
			return self::$attachment_urls[ $attachment_id ];
		}

		if ( isset( self::$attachment_files[ $attachment_id ] ) ) {
			$url = self::normalize_media_url( self::$attachment_files[ $attachment_id ] );
			if ( $url !== '' ) {
				self::$attachment_urls[ $attachment_id ] = $url;
				return $url;
			}
		}

		return '';
	}

	private static function rewrite_content_images( $content ) {
		if ( $content === '' ) {
			return $content;
		}

		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$processor = new WP_HTML_Tag_Processor( $content );
			while ( $processor->next_tag( array( 'tag_name' => 'img' ) ) ) {
				$src = $processor->get_attribute( 'src' );
				if ( $src ) {
					$new_src = self::rewrite_image_url( $src );
					if ( $new_src !== $src ) {
						$processor->set_attribute( 'src', $new_src );
					}
				}

				$srcset = $processor->get_attribute( 'srcset' );
				if ( $srcset ) {
					$new_srcset = self::rewrite_srcset( $srcset );
					if ( $new_srcset !== $srcset ) {
						$processor->set_attribute( 'srcset', $new_srcset );
					}
				}
			}

			return $processor->get_updated_html();
		}

		return preg_replace_callback(
			'#<img[^>]+src=(["\'])([^"\']+)\1#i',
			function ( $matches ) {
				$new_src = self::rewrite_image_url( $matches[2] );
				return $new_src === $matches[2] ? $matches[0] : str_replace( $matches[2], $new_src, $matches[0] );
			},
			$content
		);
	}

	private static function rewrite_image_url( $url ) {
		$url = trim( (string) $url );
		return $url === '' ? $url : self::normalize_media_url( $url );
	}

	private static function rewrite_srcset( $srcset ) {
		$parts = array_map( 'trim', explode( ',', (string) $srcset ) );
		$new   = array();

		foreach ( $parts as $part ) {
			if ( $part === '' ) {
				continue;
			}
			$space_pos = strpos( $part, ' ' );
			if ( $space_pos === false ) {
				$new[] = self::rewrite_image_url( $part );
				continue;
			}
			$url        = substr( $part, 0, $space_pos );
			$descriptor = substr( $part, $space_pos );
			$new[]      = self::rewrite_image_url( $url ) . $descriptor;
		}

		return implode( ', ', $new );
	}

	private static function normalize_media_url( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}

		if ( preg_match( '#^(data:|blob:|mailto:|tel:|javascript:)#i', $url ) ) {
			return $url;
		}

		$prod_base = self::get_prod_base_url();
		if ( $prod_base === '' ) {
			return $url;
		}

		if ( strpos( $url, '//' ) === 0 ) {
			$url = 'https:' . $url;
		}

		$uploads_path = self::extract_uploads_path( $url );
		if ( $uploads_path !== '' ) {
			return self::build_prod_url( $uploads_path );
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			return self::is_test_host_url( $url ) ? self::replace_with_prod_host( $url ) : $url;
		}

		if ( strpos( $url, '/' ) === 0 ) {
			return self::build_prod_url( $url );
		}

		return self::build_prod_url( '/wp-content/uploads/' . ltrim( $url, '/' ) );
	}

	private static function extract_uploads_path( $url ) {
		if ( preg_match( '#(/wp-content/uploads/[^\s"\']+)#i', $url, $matches ) ) {
			return $matches[1];
		}
		return '';
	}

	private static function is_test_host_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false;
		}

		$host           = strtolower( $parts['host'] );
		$host_with_port = ! empty( $parts['port'] ) ? $host . ':' . (int) $parts['port'] : $host;
		foreach ( self::get_test_hosts() as $test_host ) {
			if ( $host === $test_host || $host_with_port === $test_host ) {
				return true;
			}
		}

		return false;
	}

	private static function replace_with_prod_host( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return $url;
		}

		$prod = wp_parse_url( self::get_prod_base_url() );
		if ( empty( $prod['host'] ) || empty( $prod['scheme'] ) ) {
			return $url;
		}

		$host = $prod['host'] . ( isset( $prod['port'] ) ? ':' . $prod['port'] : '' );
		$path = $parts['path'] ?? '';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';

		return $prod['scheme'] . '://' . $host . $path . $query . $fragment;
	}

	private static function build_prod_url( $path ) {
		$base = self::get_prod_base_url();
		return $base === '' ? $path : rtrim( $base, '/' ) . '/' . ltrim( $path, '/' );
	}

	private static function is_target_post_type( $post ) {
		return is_array( $post ) && ! empty( $post['post_type'] ) && $post['post_type'] === self::get_target_post_type();
	}

	private static function get_target_post_type() {
		if ( self::$target_post_type === null ) {
			self::$target_post_type = (string) apply_filters( 'wp_import_images_post_type', 'news' );
		}
		return self::$target_post_type;
	}

	private static function ensure_import_context() {
		if ( ! self::is_importing() ) {
			return false;
		}
		self::get_prod_base_url();
		self::get_test_hosts();
		self::get_target_post_type();
		return true;
	}

	private static function is_importing() {
		return defined( 'WP_IMPORTING' ) && WP_IMPORTING;
	}

	private static function get_prod_base_url() {
		if ( self::$prod_base_url !== null ) {
			return self::$prod_base_url;
		}

		$prod = '';
		if ( defined( 'WP_IMPORT_IMAGES_PROD_URL' ) ) {
			$prod = (string) WP_IMPORT_IMAGES_PROD_URL;
		} else {
			$prod = (string) get_option( 'wp_import_images_prod_url', '' );
		}

		$prod = trim( $prod );
		if ( $prod === '' ) {
			self::$prod_base_url = '';
			return self::$prod_base_url;
		}

		if ( ! preg_match( '#^https?://#i', $prod ) ) {
			$prod = 'https://' . $prod;
		}

		self::$prod_base_url = rtrim( $prod, '/' );
		return self::$prod_base_url;
	}

	private static function get_test_hosts() {
		if ( self::$test_hosts !== null ) {
			return self::$test_hosts;
		}

		$hosts = array();
		foreach ( array( home_url(), site_url() ) as $url ) {
			$parts = wp_parse_url( $url );
			if ( ! empty( $parts['host'] ) ) {
				$host    = strtolower( $parts['host'] );
				$hosts[] = $host;
				if ( ! empty( $parts['port'] ) ) {
					$hosts[] = $host . ':' . (int) $parts['port'];
				}
			}
		}

		self::$test_hosts = array_unique( array_filter( $hosts ) );
		return self::$test_hosts;
	}
}

add_action( 'plugins_loaded', array( 'WP_Import_Images_Proxy', 'bootstrap' ) );
