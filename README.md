# BPoint for WooCommerce

BPoint for WooCommerce is a payment gateway plugin that integrates the BPoint payment system into your WooCommerce store. It allows customers to securely pay using their credit cards directly on your website.

## Features

- Seamless integration with WooCommerce.
- Supports major credit card types (Visa, MasterCard, American Express, etc.).
- Real-time validation of credit card details.
- Secure payment processing using BPoint's API.
- Custom error handling and user-friendly error messages.

## Installation

1. Download the plugin files and upload them to the `/wp-content/plugins/bpoint-for-woocommerce` directory of your WordPress installation.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to WooCommerce > Settings > Payments and enable the BPoint payment gateway.

## Usage

1. Ensure the BPoint payment gateway is enabled in WooCommerce settings.
2. Configure the BPoint API credentials in the plugin settings.
3. During checkout, customers can select the BPoint payment method and enter their credit card details.
4. The plugin validates the card details and processes the payment securely.

## Development Notes

- The plugin uses jQuery for client-side validation and AJAX for server-side communication.
- Key functions include:
  - `bpointFormHandler`: Handles form submission and validation.
  - `validateCreditCardNumber`: Validates the credit card number using the Luhn algorithm.
  - `validateCVN`: Validates the card verification number (CVN).
  - `validateExpiryDate`: Ensures the card's expiry date is valid.
- Error messages are displayed in a user-friendly format using WooCommerce's error message styling.

## Support

For support or feature requests, please contact the plugin developer or submit an issue on the plugin's repository.

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html). You are free to use, modify, and distribute it under the terms of this license.