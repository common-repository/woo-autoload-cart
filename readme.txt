=== WooCommerce Autoload Cart ===
Contributors: jamiechong
Tags: woocommerce, autoload, products, instant, express, quick, one click, checkout
Requires at least: 4.0
Tested up to: 4.3 
Stable tag: 1.2
License: GPLv2

Generate unique URLs that autoload products and/or coupons to the cart.

== Description ==

If you are running a promotion that highlights specific products, you may want to provide a link that automatically adds these products to the customer's shopping cart. 

This plugin allows you to do exactly that. Example: 

Following the link `your-domain.com/promo/summer-sale` would take the customer to `your-domain.com/cart` with 1 or more products auto loaded.

Settings to create your unique URLs can be found at
`WooCommerce > Settings > Checkout (tab) > Autoload Cart (bottom of the page)`

You can also automatically apply a coupon to instantly give a discount. 

**This a perfect plugin to use when linking to a promotion from Facebook, Twitter or an email newsletter.**


**Have an idea to improve this plugin?**
Please tell me about it in the Support Forum. 

**Found a bug, or it doesn't work for you?**
Please report it before giving a negative review!

== Installation ==

* Make sure the WooCommerce plugin is installed.
* Install this just like any other Wordpress plugin.

== Screenshots ==

1. The settings screen is found at WooCommerce > Settings > Checkout (tab) > Autoload Cart (bottom of the page)

== Frequently Asked Questions ==

= Does this work with Variable Products? =

Yes! When picking a product from settings, just choose the specific variation. 

= Why must there be a URL Prefix? =

The prefix is used to efficiently test if the incoming URL should be used to potentially auto load products. Without it, this plugin would have to do a database lookup for every single URL on your site, which could slow it down. The prefix is also used to reduce naming conflicts with other URLs on your site.


== Changelog ==

= 1.2 = 
Added the ability to autoload a coupon along with products.

= 1.1 =
* Release this plugin to the masses!

