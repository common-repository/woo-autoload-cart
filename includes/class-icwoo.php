<?php

if (!defined('ICWOO_FUNDRAISER_DIR')) die('Nope');


class ICWOO {

	public static $singleton = null;
	protected $postTypeId = 'icwoo-url';
	protected $metaPrefix = '_icwoo_';
	protected $products;
	protected $coupons;

	public function __construct() {
		if (self::woocommerceExists()) {
			add_action('init', array($this, 'actionInit'));
			add_action('parse_request', array($this, 'handleRequest'));
			
		}
	}
	
	public function handleRequest($query) {
		
		$prefix = get_option('icwoo_settings_prefix', 'promo');
		$prefix .= '/';
		
		if (strpos($query->request, $prefix) === 0) {
				
			$slug = str_replace($prefix, '', $query->request);
			$slug = trim(trim($slug), '/');
			
			$url = $this->getUrl($slug);
			
			
			if (!empty($url)) {
				$changesMade = false;
				
				if (!empty($url['products'])) {
					
					do_action('icwoo_before_add_products', $url);
					
					$qty = 1;
					for ($i=0; $i<count($url['products']); $i++) {
						
						$productId = $url['products'][$i];
						$variationId = 0;
						$variations = array();
						
						if (strpos($productId, '|') !== false) {
							list($productId, $variationId) = explode('|', $productId);
						}
						
						if ($variationId) {
							// we need to explicitly pass the attributes for this variation to add_to_cart()
							// so find them:
							$variations = $this->getProductVariationAttributes($productId, $variationId);
						}
						
						WC()->cart->add_to_cart($productId, $qty, $variationId, $variations);
					}
					
					$changesMade = true;
				}
				
				if (!empty($url['coupon'])) {
					if (!WC()->cart->has_discount($url['coupon'])) {
						WC()->cart->add_discount($url['coupon']);	
					}
					$changesMade = true;
				}
				
				do_action('icwoo_handle_request_changes', $url, $changesMade);
				
				if ($changesMade) {
					wp_safe_redirect(WC()->cart->get_cart_url());	
					exit;
				}
				
				// if we get here, then nothing changed, let WP handle the request (don't exit)
			}
		}
		return $query;	
	}
		
	public function actionInit() {
		add_filter('woocommerce_get_settings_checkout', array($this, 'settings'));
		add_action('woocommerce_admin_field_icwoo_urls', array($this, 'settingUrlList'));
		add_action('woocommerce_admin_field_icwoo_add_url', array($this, 'settingAddUrl'));
		add_action('woocommerce_settings_save_checkout', array($this, 'saveCheckoutUrl'));
		add_filter('plugin_action_links_' . ICWOO_FUNDRAISER_BASENAME, array($this, 'settingsLink'));
		
		$this->coupons = $this->getCoupons('post_title');		
		$this->products = $this->getProducts('ID');
		$this->postTypes();
	}
	
	public function generateUrl($slug) {
		$prefix = get_option('icwoo_settings_prefix', 'promo');
		return site_url('/'.$prefix.'/'.$slug);
	}
	
	public function getUrl($slug) {
		$slug = strtolower($slug);
		$posts = get_posts(array(
			'meta_key' => $this->metaPrefix.'urlSlug',
			'meta_value' => $slug,
			'post_type' => $this->postTypeId,
			'post_status' => 'publish',
			'posts_per_page' => -1
		));
		
		$params = null;
		
		if (!empty($posts)) {
			$url = $posts[0];
			$id = $posts[0]->ID;
			$params = $this->urlParams($id);
		}
		
		return $params;
	}
	
	public function urlParams($id) {
		return array(
			'slug'      => $this->meta($id, 'urlSlug'),
			'products'  => $this->meta($id, 'products'),
			'coupon'    => $this->meta($id, 'coupon'),
		);
	}
	
	public function createUrl($params) {
		
		$slug = sanitize_title($params['slug']);
		
		$postId = wp_insert_post(array(
			'post_title' => $slug,
			'post_status' => 'publish',
			'post_type' => $this->postTypeId,
		), true);
		
		$this->meta($postId, 'urlSlug',   $slug);
		$this->meta($postId, 'products',  $params['products']);
		$this->meta($postId, 'coupon',    sanitize_text_field($params['coupon']));		
		
		return $postId;
	}
	
	public function saveCheckoutUrl() {
		if (isset($_REQUEST['icwoo_newurl'])) {
			$params = $_REQUEST['icwoo_newurl'];
			
			if (!empty($params['slug'])) {
				
				if (!empty($params['products']) || !empty($params['coupon'])) {
					$this->createUrl($params);
				}
				
			}
		}
	}
	
	protected function meta($id, $key, $val=null) {
		if ($val !== null) {
			update_post_meta($id, $this->metaPrefix.$key, $val);
		} else {
			$val = get_post_meta($id, $this->metaPrefix.$key, true);
		}
		return $val;
	}
	
	protected function postTypes() {
	
		register_post_type($this->postTypeId,
			array(
				'labels'              => array(
					'name'               => __('Autoload URL', 'icwoo'),
				),
				'public'              => false,
				'exclude_from_search' => true,
				'hierarchical'        => false,
				'has_archive'         => false,
				'show_in_nav_menus'   => false,
				'supports'            => array('title')
			)
		);		
	}
	
	public function settings($settings) {
			
			$settings[] = array( 
				'name'     => __('Autoload Cart', 'icwoo'), 
				'type'     => 'title', 
				'id'       => 'icwoo_settings_title',
				'desc'     => __(apply_filters('icwoo_settings_title','Generate unique URLs that autoload products and/or coupons to the cart.<br/><a href="'.ICWOO_FUNDRAISER_URL.'/docs/" target="_blank">Instruction Manual</a>'), 'icwoo'),
			);
			
			$settings[] = array(
				'name'     => __('URL Prefix', 'icwoo'),
				'desc_tip' => __('When generating URLs, the slug you use will be prefixed with this value. If left blank it defaults to "promo". ex: domain.com/promo/[slug]', 'icwoo'),
				'id'       => 'icwoo_settings_prefix',
				'type'     => 'text',
				'default'  => 'promo',
			);

			$settings[] = array(
				'id'       => 'icwoo_settings_add_url',
				'type'     => 'icwoo_add_url',
			);
			
			$settings[] = array(
				'id'       => 'icwoo_settings_url_list',
				'type'     => 'icwoo_urls',
			);
			
			$settings[] = array(
				'type'     => 'sectionend', 
				'id'       => 'icwoo_settings',
			);
		
		return $settings;
	}
	
	public function settingAddUrl() {
		?>
		<style>
		
			.clearfix { *zoom: 1; }
			.clearfix:before, .clearfix:after {
				content: " ";
				display: table; 
			}
			.clearfix:after {
				clear: both; 
			}
		
			.icwoo_add_url dl {
				margin: 0;
			}
			.icwoo_add_url dl, .icwoo_add_url dt, .icwoo_add_url dd {
				-webkit-box-sizing: border-box;
				-moz-box-sizing: border-box;
				box-sizing: border-box;
			}
			.icwoo_add_url dt, .icwoo_add_url dd {
				line-height: 30px;
				padding: 5px;
			}
			.icwoo_add_url dt, .icwoo_add_url div.save {
				clear: both;
			}
			.icwoo_add_url dt {
				float: left;
				text-align: right;
				width: 100px;
			}
			.icwoo_add_url dd {
				float: left;
				margin: 0;
				width: 300px;
			}
			.icwoo_add_url input.slug-input { width: 100%; } 
		</style>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php _e('Create New URL', 'icwoo') ?></th>
			<td class="forminp icwoo_add_url">
				<dl class="clearfix">
					<dt>Slug:</dt>
					<dd><input type="text" class="slug-input" name="icwoo_newurl[slug]" /></dd>
					
					<dt>Products:</dt>
					<dd><?php $this->selectBox('icwoo_newurl[products]', esc_attr('Choose products&hellip;', 'icwoo'), $this->postsBy($this->products, 'ID', 'post_title', array($this, 'productValue')), true); ?></dd>

					<dt>Coupon:</dt>
					<dd><?php $this->selectBox('icwoo_newurl[coupon]', esc_attr('Choose coupon&hellip;', 'icwoo'), $this->postsBy($this->coupons, 'post_title', 'post_title'), false, true);?></dd>

					<?php
					do_action('icwoo_add_url_fields');
					?>
				</dl>
				
				<div class="save"><?php submit_button() ?></div>
			</td>
		</tr>
		<?php
	}
	
	public function settingUrlList() {
		
		$posts = get_posts(array(
			'post_type' => $this->postTypeId,
			'post_status' => 'any',
			'posts_per_page' => -1
		));
		
		?>
		<style>
		.icwoo_url_list td.icon a {
			display: inline-block;
			text-align: center;
			vertical-align: middle;			
		}
		.icwoo_url_list td.icon a:before {
			display: block;
			font-family: WooCommerce;
			font-size: 18px;
			height: 100%;
			text-align: center;
			width: 17px;
		}
		.icwoo_url_list td.link a:before {
			content: "\e00d";
		}
		.icwoo_url_list td.remove a:before {
			color: red;
			content: "\e013";
		}
		table.icwoo_url_list.wc_gateways tfoot tr td {
			background: white;
		}
		.icwoo_url_list tfoot td input.slug-input {
			margin: -4px 0 0;
			padding: 4px 8px 3px;
			width: 100%;
		}
		.icwoo_url_list tfoot td input[type=radio] {
			margin-top: 1px;
		}
		</style>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php _e('Active URLs', 'icwoo') ?></th>
		    <td class="forminp">
				<table class="icwoo_url_list wc_gateways widefat" cellspacing="0">
					<thead>
						<tr>
							<?php
								$columns = apply_filters('icwoo_url_list_columns', array(
									'slug'      => __('URL', 'icwoo'),
									'products'  => __('Products', 'icwoo'),
									'coupon'    => __('Coupon', 'icwoo'),
									'remove'    => '',
								));

								foreach ($columns as $key => $column) {
									echo '<th class="'.esc_attr($key).'">'.esc_html($column).'</th>';
								}
							?>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ($posts as $post) {
							$params = $this->urlParams($post->ID);
							$linkto = $this->generateUrl($params['slug']);
							echo '<tr>';
							foreach ( $columns as $key => $column ) {
								
								if ($key == 'slug') {
									echo '<td class="'.esc_attr($key).'"><a href="'.$linkto.'" target="_blank">'.str_replace(array('http://','https://'),'',$linkto).'</a></td>';
								} else if ($key == 'remove') {
									echo '<td width="1%" class="icon remove"><a href="'.get_delete_post_link($post->ID, '', true).'" onclick="return confirm(\'Are you sure you want to delete this?\');"></a></td>';
								} else if ($key == 'products') {
									echo '<td class="'.esc_attr($key).'">'.$this->linkedProductList($params[$key]).'</td>';	
								} else if ($key == 'coupon') {
									echo '<td class="'.esc_attr($key).'">'.$this->linkedCoupon($params[$key]).'</a></td>';	
								}
								
								do_action('icwoo_url_list_column', $key, $params[$key]);
							}
							echo '</tr>';
						}
						?>
					</tbody>
				</table>
			</td>
		</tr>
		<?php		
	}
	
	public function settingsLink($links) {
		array_unshift($links, '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">Settings</a>');
		return $links;
	}
	
	protected function linkedProductList($ids) {
		$list = array();

		if (!empty($ids)) {
			foreach ($ids as $id) {
				$productId = $id;
				$variationId = 0;
				
				if (strpos($id, '|') !== false) {
					list($productId, $variationId) = explode('|', $id);
				}

				$title = $this->products[$productId]->post_title;
				
				if ($variationId) {
					$atts = $this->getProductVariationAttributes($productId, $variationId);
					$title .= (' &mdash; '.implode(', ', $atts));				
				}

				$list[] = '<a href="'.get_edit_post_link($productId).'" target="_blank">'.$title.'</a>';
			}
		}
		return !empty($list) ? '<ul><li>'.implode('</li><li>', $list).'</li></ul>' : '';
	}
	
	protected function getProducts($key='') {
		$posts = get_posts(array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'order' => 'ASC',
			'orderby' => 'title',
		));

		return $this->reKey($posts, $key);
	}

	protected function getProductVariationAttributes($productId, $variationId) {
		$atts = array();
		$product = wc_get_product( $productId );
		$avs = $product->get_available_variations();

		foreach ($avs as $av) {
			if ($av['variation_id'] == $variationId) {
				$atts = $av['attributes'];
				break;
			}
		}
		return $atts;
	}	
	
	protected function reKey(&$a, $key) {
		$b = array();
		
		if (empty($key)) {
			$b = $a;
		} else {
			for ($i=0; $i<count($a); $i++) {
				$v = $a[$i];
				$b[$v->$key] = $v;
			}
		}
		return $b;
	}
	
	protected function productValue($post) {
		$product = wc_get_product($post->ID);
		$val = $title = $post->post_title;
		$val = '#'.$post->ID.' &ndash; '.$val;
		
		if ($product->product_type == 'variable') {
			$variations = $product->get_available_variations();
			
			$varOptions = array();
			foreach ($variations as $var) {
				$varOptions[$post->ID.'|'.$var['variation_id']] = '#'.$var['variation_id'].' &ndash; ' . $title .' &mdash; ' . implode(', ', array_values($var['attributes']));
			}
			asort($varOptions);
			
			$val = array(
				'label' => $val,
				'options' => $varOptions
			);
		} 
		
		return $val;
	}

	protected function postsBy(&$posts, $keyKey, $valueKey, $valueCallback=false) {
		$res = array();
		
		foreach ($posts as $post) {
			$res[$post->$keyKey] = $valueCallback !== false ? call_user_func($valueCallback, $post) : $post->$valueKey;
		}
		
		return $res;
	}
	
	protected function selectBox($name, $placeholder, $options=array(), $multi=false, $allowNone=false, $selections=array()) {
		?>
		<select <?php if ($multi):?>multiple="multiple"<?php endif;?>  name="<?php echo $name.($multi?'[]':'') ?>" data-placeholder="<?php echo $placeholder ?>" class="wc-enhanced-select-nostd">
			<?php
				if ($allowNone !== false) {
					echo '<option value=""></option>';
				}
				if (!empty($options)) {
					foreach ($options as $key => $val) {
						$label = $val;
						$attributes = '';
						
						if (!is_array($val)) {
							echo '<option value="' . esc_attr($key) .'" ' . selected($key==$selection, true, false).'>'.$val.'</option>';
						} else {
							echo '<optgroup label="' . esc_attr($val['label']) . '">';
							
							foreach ($val['options'] as $gkey => $gval) {
								echo '<option value="' . esc_attr($gkey) .'">'.$gval.'</option>';
							}
							
							echo '</optgroup>';
						}
					}
				}
			?>
		</select>
		<?php
	}
	
	protected function buildAttributes($attributes) {
		return join(' ', array_map(function($key) use ($attributes) {
			if(is_bool($attributes[$key])) {
				return $attributes[$key]?$key:'';
			}
			return $key.'="'.$attributes[$key].'"';
		}, array_keys($attributes)));
	}
	
	protected function linkedCoupon($slug) {
		$coupon = $this->coupons[$slug];
		return '<a href="'.get_edit_post_link($coupon->ID).'" target="_blank">'.$coupon->post_title.'</a>';
	}

	protected function getCoupons($key='') {
		$posts = get_posts(array(
			'post_type' => 'shop_coupon',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'order' => 'ASC',
			'orderby' => 'title',
		));
		
		return $this->reKey($posts, $key);
	}	

	public static function activatePlugin() {

	}

	public static function init() {
		return self::instance();
	}
	
	public static function instance() {
		if (null === self::$singleton) {
			self::$singleton = new self();
		}
		return self::$singleton;		
	}

	public static function woocommerceExists() {
		return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
	}
}