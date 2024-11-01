<?php
/**
 * Plugin Name: WooCommerce Autoload Cart
 * Plugin URI: http://wordpress.org/plugins/autoload-cart/
 * Description: Generate unique URLs that link to the cart that is auto-populated with products of your choosing.
 * Author: Jamie Chong
 * Version: 1.2
 * Author URI: http://jamiechong.ca
 *
 * Copyright (C) 2015 Jamie Chong
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
**/

if (!defined('ABSPATH')) die('Nope');

define('ICWOO_FUNDRAISER_DIR', dirname(__FILE__));
define('ICWOO_FUNDRAISER_URL', plugins_url('', __FILE__));
define('ICWOO_FUNDRAISER_BASENAME', plugin_basename(__FILE__));
require_once(ICWOO_FUNDRAISER_DIR.'/includes/class-icwoo.php');
register_activation_hook(__FILE__, array('ICWOO', 'activatePlugin'));
ICWOO::init();