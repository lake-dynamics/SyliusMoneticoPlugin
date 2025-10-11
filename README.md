# LakeDynamics Sylius Monetico Plugin

[![License](https://img.shields.io/github/license/lake-dynamics/sylius-monetico-plugin.svg)](LICENSE)

A Sylius plugin for integrating Monetico payment gateway into your e-commerce store.

## Features

- 💳 Complete Monetico payment gateway integration
- 🔒 Secure payment processing with MAC signature validation
- 🎨 Beautiful payment redirect page with loading animation
- 🌍 Support for both production and sandbox environments
- ✅ Full Sylius 2.0 Payment Request system compatibility
- 🔔 Automatic payment notification handling
- 📱 Responsive design

## Requirements

- PHP 8.2 or higher
- Sylius 2.0 or higher

## Installation

1. Install the plugin using Composer:

```bash
composer require lake-dynamics/sylius-monetico-plugin
```

2. Enable the plugin in your `config/bundles.php`:

```php
return [
    // ...
    LakeDynamics\SyliusMoneticoPlugin\LakeDynamicsSyliusMoneticoPlugin::class => ['all' => true],
];
```

3. Import the plugin configuration in `config/packages/_sylius.yaml`:

```yaml
imports:
    - { resource: "@LakeDynamicsSyliusMoneticoPlugin/config/config.yaml" }
```

4. Import the plugin routes in `config/routes.yaml`:

```yaml
lake_dynamics_sylius_monetico:
    resource: "@LakeDynamicsSyliusMoneticoPlugin/config/routes/shop.yaml"
```

5. Clear the cache:

```bash
bin/console cache:clear
```

## Configuration

### 1. Create Payment Method

1. Log in to the Sylius admin panel
2. Go to **Configuration > Payment methods**
3. Click **Create**
4. Fill in the general information:
   - **Code**: `monetico` (or your preferred code)
   - **Name**: `Monetico`
   - **Enabled**: Check this box
5. Select **Monetico Payment Gateway** as the gateway
6. Configure the Monetico settings:
   - **TPE (Terminal Payment Electronic)**: Your Monetico TPE number
   - **Company ID (Société)**: Your Monetico company identifier
   - **Production Key**: Your Monetico encryption key
   - **Use Production Environment**: Check for production, uncheck for sandbox/test

### 2. Generate Encryption Key

To encrypt sensitive payment configuration data:

```bash
bin/console sylius:payment:generate-key
```

### 3. Monetico Credentials

You'll receive the following credentials from Monetico:

- **TPE**: Your terminal identifier (e.g., `1234567`)
- **Company ID**: Your company code (e.g., `mycompany`)
- **Production Key**: A 40-character hexadecimal key for MAC signature generation

## How It Works

### Payment Flow

1. **Customer Checkout**: Customer selects Monetico as payment method
2. **Payment Initiation**: System creates a PaymentRequest with ACTION_CAPTURE
3. **Field Preparation**: Plugin prepares payment fields and generates MAC signature
4. **Redirect**: Customer is redirected to Monetico payment page via auto-submit form
5. **Payment Processing**: Customer completes payment on Monetico's secure portal
6. **Notification**: Monetico sends payment result to your notification URL
7. **Validation**: Plugin validates MAC signature and updates payment status
8. **Completion**: Order is marked as paid or failed based on result

### Security

- **MAC Signature**: All data exchanged with Monetico is signed using HMAC SHA1
- **Encryption**: Payment credentials are encrypted in database
- **Validation**: All incoming notifications are validated before processing
- **HTTPS**: Production environment requires HTTPS

### Technical Details

**Payment Fields Sent to Monetico:**
- TPE: Terminal identifier
- societe: Company identifier
- montant: Amount in EUR format (e.g., "10.50EUR")
- reference: Unique payment reference
- date: Payment date in GMT
- MAC: HMAC SHA1 signature
- texte-libre: Base64-encoded payment metadata (order ID, payment ID)
- contexte_commande: Base64-encoded customer and billing data
- url_retour_ok: Success return URL
- url_retour_err: Error return URL

**Valid Payment Status:**
- `paiement`: Successful payment (production)
- `payetest`: Successful payment (test/sandbox)

## Development

### Running Tests

```bash
# PHPUnit
vendor/bin/phpunit

# Behat (non-JS)
vendor/bin/behat --strict --tags="~@javascript&&~@mink:chromedriver"

# PHPStan
vendor/bin/phpstan analyse -c phpstan.neon -l max src/

# Coding Standards
vendor/bin/ecs check
```

### Docker Development

```bash
# Initialize environment
make init

# Initialize database
make database-init

# Load fixtures
make load-fixtures

# Run tests
make phpunit
make behat
make phpstan
make ecs
```

## Troubleshooting

### Payment Fails with "Invalid MAC signature"

**Problem**: Notification validation fails

**Solution**:
1. Verify your Production Key is correct (40 hex characters)
2. Ensure your server time is synchronized (GMT)
3. Check Monetico dashboard for the correct key
4. Verify notification URL is accessible from external networks

### Payment Page Doesn't Redirect

**Problem**: Auto-submit form doesn't work

**Solution**:
1. Check browser console for JavaScript errors
2. Verify payment fields are properly generated
3. Ensure Monetico URL is accessible
4. Check if Content Security Policy allows form submission

### Notification URL Not Receiving Callbacks

**Problem**: Monetico can't reach your notification endpoint

**Solution**:
1. Ensure your server is accessible from external networks
2. Verify URL in admin matches: `https://yourdomain.com/payment/monetico/notify/{hash}`
3. Check firewall rules allow Monetico IPs
4. Test notification URL manually with a valid hash

### Production vs Sandbox

**Sandbox Testing**:
- Use sandbox credentials from Monetico
- Uncheck "Use Production Environment" in payment method config
- Payment URL: `https://p.monetico-services.com/test/paiement.cgi`

**Production**:
- Use production credentials from Monetico
- Check "Use Production Environment"
- Payment URL: `https://p.monetico-services.com/paiement.cgi`
- **Requires HTTPS**

## Support

- **Documentation**: [Monetico Documentation](https://www.monetico-paiement.fr/)
- **Sylius Docs**: [docs.sylius.com](https://docs.sylius.com/)
- **Issues**: [GitHub Issues](https://github.com/lake-dynamics/sylius-monetico-plugin/issues)

## License

This plugin is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by [LakeDynamics](https://github.com/lake-dynamics)

Monetico is a trademark of Groupe BPCE.
