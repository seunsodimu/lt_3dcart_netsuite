# 3DCart to NetSuite Integration System

A comprehensive PHP-based integration system that automatically processes orders from 3DCart and creates corresponding sales orders in NetSuite, with customer management and email notifications.

## Features

- **Webhook Integration**: Automatically receives and processes orders from 3DCart
- **NetSuite Integration**: Creates sales orders and customers in NetSuite
- **Email Notifications**: Sends status updates via SendGrid
- **Manual Upload**: Supports manual order upload via CSV/Excel files
- **Connection Status**: Real-time monitoring of all integrations
- **Comprehensive Logging**: Detailed logs for debugging and monitoring

## Project Structure

```
laguna_3dcart_netsuite/
├── config/
│   ├── config.php              # Main configuration file
│   ├── database.php            # Database configuration
│   └── credentials.example.php # Example credentials file
├── src/
│   ├── Controllers/
│   │   ├── WebhookController.php
│   │   ├── OrderController.php
│   │   └── StatusController.php
│   ├── Services/
│   │   ├── ThreeDCartService.php
│   │   ├── NetSuiteService.php
│   │   └── EmailService.php
│   ├── Models/
│   │   ├── Order.php
│   │   └── Customer.php
│   └── Utils/
│       ├── Logger.php
│       └── Validator.php
├── public/
│   ├── index.php               # Main entry point
│   ├── webhook.php             # Webhook endpoint
│   ├── upload.php              # Manual upload interface
│   └── status.php              # Connection status dashboard
├── templates/
│   ├── upload.html
│   └── status.html
├── logs/
├── uploads/
├── docs/
│   ├── SETUP.md
│   ├── API_CREDENTIALS.md
│   └── DEPLOYMENT.md
├── composer.json
└── README.md
```

## Quick Start

1. **Clone and Setup**
   ```bash
   git clone <repository-url>
   cd laguna_3dcart_netsuite
   composer install
   ```

2. **Configure Credentials**
   ```bash
   cp config/credentials.example.php config/credentials.php
   # Edit config/credentials.php with your API credentials
   ```

3. **Setup Database**
   ```bash
   # Import database schema (if using database logging)
   mysql -u username -p database_name < database/schema.sql
   ```

4. **Configure Web Server**
   - Point your web server to the `public/` directory
   - Ensure PHP has write permissions to `logs/` and `uploads/` directories

5. **Test Connections**
   - Visit `/status.php` to verify all integrations are working

## API Endpoints

- `POST /webhook.php` - 3DCart webhook endpoint
- `GET /status.php` - Connection status dashboard
- `POST /upload.php` - Manual order upload

## Documentation

- [Setup Guide](docs/SETUP.md) - Detailed setup instructions
- [API Credentials](docs/API_CREDENTIALS.md) - How to obtain API credentials
- [Deployment Guide](docs/DEPLOYMENT.md) - Production deployment guide

## Requirements

- PHP 7.4 or higher
- cURL extension
- JSON extension
- OpenSSL extension
- Composer

## License

MIT License