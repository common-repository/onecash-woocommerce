=== OneCash ===
Contributors: OneCash
Tags: onecash, instalment, pay later, buy now pay later, payment gateway, woocommerce, e-commerce
Requires at least: 4
Tested up to: 4.8.2
Stable tag: 1.3.7
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==

OneCash is a “Buy Now, Pay Later” instalment plan for online purchases. It provides consumers with the ability to defer their immediately online purchases in 4 equal monthly instalments over 3 months. For merchants, there is zero risk as the full amount is paid to your account giving you the benefit to increase your conversion and basket size on your ecommerce store.

We accept Visa, MasterCard, American Express directly on your store with the OneCash payment gateway for WooCommerce mobile and desktop.

== PROVIDE INSTALMENT PLANS EASILY AND DIRECTLY ON YOUR STORE ==

The OneCash plugin extends WooCommerce allowing you to provide instalment payments directly on your store via OneCash’s API.

OneCash is only currently available in Singapore.

== WHY CHOOSE ONECASH? ==

OneCash has no setup fees, no monthly fees, no hidden costs: you only get charged when you earn money! Earnings are transferred to your bank account when you request for a pay-out.

OneCash also supports the auto debit system and re-using of cards. When a customer pays, they are set up in OneCash as a customer. If they create another order, they can check out using the same card. A massive timesaver for returning customers.

== Onecash WooCommerce Plugin Installation ==

* This section outlines the steps to install the OneCash plugin. As a pre-requisite ensure that Woo Commerce is installed and activated in the WordPress admin page.

* If you are upgrading to a new version of the OneCash Plugin, it is always a good practice to backup your existing plugin before you commence the installation steps.

== Wordpress Installation Folder ==

woocommerce-gateway-onecash
    ├── checkout (folder)
    ├── css (folder)
    ├── config (folder)
    ├── images (folder)
    ├── js (folder)
    └── woocommerce-onecash.php

== Uploading the Plugin ==

Upload via FTP / SSH (For developers): Upload the plugin folder (not the ZIP) and files to your WordPress server. Copy the folder 'woocommerce-gateway-onecash' folder into the path: [wordpress-installation-folder]/wp-content/plugins/

Upload via Wordpress Admin: Upload the ZIP folder coming with the plugin codes to your WordPress Admin plugin uploader (<em>Plugins - Add New - Upload ZIP file</em>). It should deploy the plugin automatically.


=== Configuring the Plugin ==

* Open and login to the WordPress Admin page in your browser, navigate to the plugins page by clicking the 'Plugins' item in the menu on the left side of the screen.

* Find the plugin 'WooCommerce OneCash Gateway' in the plugins list, click 'Activate' link below the plugin name.

* Navigate to 'WooCommerce' > 'Settings' page; select the 'Checkout' tab on the top.

* Scroll down to the 'Gateway Display Order' section, find 'OneCash' in the gateway list, and click 'Settings' to open the onecash woocommerce plugin settings page.

* Tick the first checkbox to 'Enable OneCash'.

* The 'Test mode' is selected by default. This affects the actual onecash api url addresses that the plugin will talk to.

* Enter the merchant id and secret key that OneCash has provided you for the selected Mode.
