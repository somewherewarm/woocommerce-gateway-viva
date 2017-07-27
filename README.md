# WooCommerce Viva Wallet Payment Gateway

The WooCommerce Viva Wallet Payment Gateway provides a PCI compliant payment processing integration between WooCommerce and [Viva Wallet](https://www.vivawallet.com). Payments take place securely off-site on Viva's servers, providing an easy and secure payment integration.

This plugin implements the [Redirect Checkout](https://github.com/VivaPayments/API/wiki/Redirect-Checkout) method which sends the customer to the Viva Wallet website to enter payment details. This alleviates the security burden of PCI compliance since payment data is handled on Viva Wallet servers. Note that since your site never handles payment data, an SSL certificate is not needed.

### Installation

* Download a `.zip` file with the [latest version](https://github.com/somewherewarm/woocommerce-gateway-viva/releases).
* Go to **WordPress Admin > Plugins > Add New**.
* Click **Upload Plugin** at the top.
* **Choose File** and select the `.zip` file you downloaded in Step 1.
* Click **Install Now** and **Activate** the extension.

### Configuration

To accept payments using the **WooCommerce Viva Wallet** gateway, you must:

* Have a valid Viva Wallet business/merchant account.
* Link the gateway with your Viva Wallet business/merchant account by configuring the gateway settings at **WooCommerce > Settings > Checkout > Viva Wallet**.

To configure the Viva Wallet gateway settings, follow these steps:


#### 1. Configure Your Merchant ID, API Key and Payment Source

1. Log into your [Viva Wallet](https://www.vivawallet.com) business/merchant account panel.
2. Go to **Settings > API Access > General**.
3. Note down your **Merchant ID** and **API Key**.
4. Go to **My Sales > Payment Sources**.
5. Create a **New Website/App** source for your WooCommerce store by following this guide.
6. Note down the **Code** field of your Website/App source.
7. Log into your website Dashboard and go to **WooCommerce > Settings > Checkout > Viva Wallet**.
8. Fill in the **Merchant ID**, **API Key** and **Payment Source** fields.
9. Click **Save**.
10. A **Merchant ID and API Key validation successful** message should be displayed -- if not, go back to Step 8 and check that all details have been entered correctly.


#### 2. Set Up Viva Wallet Webhooks

Viva Wallet can be configured to notify your store each time a specific event takes place, e.g. a sucessful transaction. To enable these notifications, you need to log into your [Viva Wallet](https://www.vivawallet.com) account and configure **Wehooks**.

With **Webhooks** configured correctly, order status will automatically change:

* From _pending_ to _processing_ or _completed_ when a successful transaction is recorded.
* From _processing_ or _completed_ to _refunded_ when a full refund is issued from the **My Sales > Sales** page of your Viva Wallet account panel.

To configure **Webhooks**:

1. Log into your [Viva Wallet](https://www.vivawallet.com) business/merchant account panel.
2. Go to **Settings > API Access > Webhooks**.
3. Click **Create Webhook**.
4. In the **URL** field, enter your website URL, followed by `?wc-api=wc_gateway_viva`, e.g. `http://mysite.gr/?wc-api=wc_gateway_viva`.
5. Check the **Active** option.
6. Choose the **Transaction Payment Created** Event Type.
7. **Save** the Webhook. Now orders will change from _pending_ to _processing_ or _completed_ when a successful transaction takes place.
8. Go back to Step 3 and create another identical Webhook. This time, choose the **Transaction Reversal Created** Event Type.
9. **Save** the Webhook. Now orders will change from _processing_ or _completed_ to _refunded_ when a full refund is issued from the **My Sales > Sales** page.

