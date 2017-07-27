# WooCommerce Viva Wallet Payment Gateway

The WooCommerce Viva Wallet Payment Gateway provides a PCI compliant payment processing integration between WooCommerce and [Viva Wallet](https://www.vivawallet.com). Payments take place securely off-site on Viva's servers, providing an easy and secure payment integration.

This plugin implements the [Redirect Checkout](https://github.com/VivaPayments/API/wiki/Redirect-Checkout) method which sends the customer to the Viva Wallet website to enter payment details. This alleviates the security burden of PCI compliance since payment data is handled on Viva Wallet servers. Note that since your site never handles payment data, an SSL certificate is not needed.


### Is This Free?

Yes, it's free. But here's what you should _really_ care about:

* The codebase adheres to the [WordPress Coding Standards](https://codex.wordpress.org/WordPress_Coding_Standards) and follows WooCommerce best practices and conventions.
* The status of a successful transaction is verified. The gateway does not blindly trust the return url being called, or the content of a Webhook notification.
* Viva Wallet **Webhooks** are supported. If you [configure](#3-set-up-viva-wallet-webhooks) Webhook settings correctly, the status of an order will automatically change when: i) a successful transaction is recorded, or ii) a refund is issued from the **My Sales > Sales** page of your Viva Wallet account panel.
* The implementation supports the WooCommerce Refunds API. This means that you can [issue partial or full refunds](https://docs.woocommerce.com/document/woocommerce-refunds/) directly from within an order, without leaving your WooCommerce store.

In short, if you run a WooCommerce store and want to accept payments using Viva Wallet in the most simple and secure manner, look no further.


### What's the Catch?

This is a non-commercial plugin. As such:

* Development time for it is effectively being donated and is therefore, limited.
* Support inquiries may not be answered in a timely manner.
* Critical issues may not be resolved promptly.

If you:

* need help [setting up](#configuration) the gateway,
* have a customization/integration requirement, or
* want to see another feature added, e.g. support for **tokens** or **recurring payments**,

...then we'd love to [hear from you](http://somewherewarm.gr/about/)!

Please understand that:

* Our time is as limited (and precious) as yours. If you need something that requires some of our time, it's probably not going to be for free.
* This repository is not a place to ask for help. Use it to report bugs, propose improvements, or discuss new features.


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

#### 1. Create a Payment Source

To accept payments from your WooCommerce website, a Viva Wallet **Payment Source** must be linked to your store. To create a new **Payment Source** for your website:

1. Log into your [Viva Wallet](https://www.vivawallet.com) business/merchant account panel.
2. Go to **My Sales > Payment Sources**.
3. Click **New Website/App** to create a new source.
4. Fill in the **Domain Name** field, e.g. `mysite.gr`.
5. Ensure that **Redirection** is selected under **Integration Method**.
6. Enter `/?wc-api=wc_gateway_viva&result=success` in the **Success URL** field.
7. Enter `/?wc-api=wc_gateway_viva&result=failure` in the **Failure URL** field.

#### 2. Configure Your Merchant ID, API Key and Payment Source

1. Log into your [Viva Wallet](https://www.vivawallet.com) business/merchant account panel.
2. Go to **Settings > API Access > General**.
3. Note down your **Merchant ID** and **API Key**.
4. Go to **My Sales > Payment Sources**.
6. Note down the **Code** field of the Website/App source linked to your WooCommerce store. If you haven't done so already, [create](#1-create-a-payment-source) a **New Website/App** source for your WooCommerce store.
7. Log into your website Dashboard and go to **WooCommerce > Settings > Checkout > Viva Wallet**.
8. Fill in the **Merchant ID**, **API Key** and **Payment Source** fields.
9. Click **Save**.
10. A **Merchant ID and API Key validation successful** message should be displayed -- if not, go back to Step 8 and check that all details have been entered correctly.

#### 3. Set Up Viva Wallet Webhooks

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

