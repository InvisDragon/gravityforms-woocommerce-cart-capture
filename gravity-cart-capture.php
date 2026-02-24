/*
 * Plugin Name:  Gravity Forms: WooCommerce Cart Capture
 * Plugin URI: https://github.com/lucasstark/gravityforms-woocommerce-cart-capture
 * Description:  Automatically capture the contents of a users cart when they submit a gravity form.  To use Set gravity form to pre-populate a field.  Set the key as woocommerce_cart.  The cart contents at the time of submission will automatically be captured.
 * Author:  Lucas Stark
 * Author URI:  http://www.github.com/lucasstark/
 * License: GPLv3
 */


class GFWC_Cart_Capture {
	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new GFWC_Cart_Capture();
		}
	}

	private function __construct() {
		add_filter( 'gform_field_value_woocommerce_cart', array($this, 'record_cart_item_meta') );
		add_filter( 'gform_entry_field_value', array($this, 'display_cart_item_meta'), 10, 4 );
		add_action( 'gform_pre_submission', array($this, 'gform_pre_submission') );
        add_filter( 'gform_merge_tag_filter', array($this, 'gform_merge_tag_filter'), 10, 4 );
	}

    public function gform_merge_tag_filter($field_value, $merge_tag, $options, $field) {
        if($field['inputName'] == 'woocommerce_cart') {
            return $this->display_cart_item_meta($field_value, $field, null, null);
        }
        return $field_value;
    }
	
	public function gform_pre_submission($form) {
	    foreach($form['fields'] as $field) {
	        if($field['inputName'] == 'woocommerce_cart') {
	            $_POST['input_' . $field['id']] = $this->record_cart_item_meta(null);
	        }
	    }
	}

	public function record_cart_item_meta( $value ) {
		$items = array();

		if(!WC()->cart) return $value;

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				$items[$cart_item_key] = array(
					'title' => apply_filters( 'woocommerce_cart_item_name', $_product->get_title(), $cart_item, $cart_item_key ),
					'quantity' => $cart_item['quantity'],
					'meta' => $this->get_cart_item_data($cart_item)
				);
			}
		}

		$value = base64_encode( serialize( $items ) );
		return $value;
	}

	public function display_cart_item_meta( $value, $field, $lead, $form ) {

		if ( isset( $field['inputName'] ) && $field['inputName'] == 'woocommerce_cart' ) {
			$output = '<ul>';
			$cart_items = unserialize( base64_decode( $value ) );
			if ( $cart_items && is_array( $cart_items ) ) :
				foreach ( $cart_items as $cart_item ) :
					$output .= '<li style="margin-bottom:10px;">';
					$output .= '<strong>' . $cart_item['title'] . '</strong>: ( qty ' . $cart_item['quantity'] . ' )';

					if ( isset( $cart_item['meta'] ) ) :
						$output .= '<ul style="margin-left:15px;margin-top:5px;">';
						foreach ( $cart_item['meta'] as $cart_item_meta ) :
							$output .= '<li><strong>' . $cart_item_meta['key'] . '</strong>: ' . $cart_item_meta['display'] . '</li>';
						endforeach;
						$output .= '</ul>';
					endif;

					$output .= '</li>';
					$output .= '<li><hr /></li>';
				endforeach;
			endif;

			$output .= '</ul>';
			return $output;
		} else {
			return $value;
		}
	}

	private function get_cart_item_data( $cart_item ) {

		$item_data = array();

		// Variation data
		if ( !empty( $cart_item['data']->variation_id ) && is_array( $cart_item['variation'] ) ) {

			foreach ( $cart_item['variation'] as $name => $value ) {

				if ( '' === $value ) {
					continue;
				}

				$taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_pa_', '', urldecode( $name ) ) );

				// If this is a term slug, get the term's nice name
				if ( taxonomy_exists( $taxonomy ) ) {
					$term = get_term_by( 'slug', $value, $taxonomy );
					if ( !is_wp_error( $term ) && $term && $term->name ) {
						$value = $term->name;
					}
					$label = wc_attribute_label( $taxonomy );

					// If this is a custom option slug, get the options name
				} else {
					$value = apply_filters( 'woocommerce_variation_option_name', $value );
					$product_attributes = $cart_item['data']->get_attributes();
					if ( isset( $product_attributes[str_replace( 'attribute_', '', $name )] ) ) {
						$label = $product_attributes[str_replace( 'attribute_', '', $name )];
						if(is_array($label)) {
							$label = wc_attribute_label($label['name']);
						}
					} else {
						$label = $name;
					}
				}

				$item_data[] = array(
					'key' => $label,
					'value' => $value
				);
			}
		}

		// Filter item data to allow 3rd parties to add more to the array
		$item_data = apply_filters( 'woocommerce_get_item_data', $item_data, $cart_item );

		// Format item data ready to display
		foreach ( $item_data as $key => $data ) {
			// Set hidden to true to not display meta on cart.
			if ( !empty( $data['hidden'] ) ) {
				unset( $item_data[$key] );
				continue;
			}
			$item_data[$key]['key'] = !empty( $data['key'] ) ? $data['key'] : $data['name'];
			$item_data[$key]['display'] = !empty( $data['display'] ) ? $data['display'] : $data['value'];
		}

		return $item_data;
	}

}

GFWC_Cart_Capture::register();
