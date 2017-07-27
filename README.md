# WooCommerce Viva Wallet Payment Gateway

The WooCommerce Viva Wallet Payment Gateway provides a PCI compliant payment processing integration between WooCommerce and [Viva Wallet](https://www.vivawallet.com). Payments take place securely off-site on Viva's servers, providing an easy and secure payment integration.

This plugin implements the [Redirect Checkout](https://github.com/VivaPayments/API/wiki/Redirect-Checkout) method which sends the customer to the Viva Wallet website to enter payment details. This alleviates the security burden of PCI compliance since payment data is handled on Viva Wallet servers. Note that since your site never handles payment data, an SSL certificate is not needed.

### Installation

* Download a `.zip` file with the [latest version](https://github.com/somewherewarm/woocommerce-gateway-viva/releases).
* Go to **WordPress Admin > Plugins > Add New**.
* Click **Upload Plugin** at the top.
* **Choose File** and select the `.zip` file you downloaded in Step 1.
* Click **Install Now** and **Activate** the extension.

