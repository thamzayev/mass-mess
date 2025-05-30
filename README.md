# MassMess - A Mass Emailing Web Application

A web application built with Laravel and Filament for sending personalized mass emails with custom attachments.

## Features

- **CSV Data Import**: Upload CSV files containing recipient data and use them in your emails
- **Dynamic Content**: Personalize emails using data columns from your CSV
- **Conditional Content**: Include conditional sections in your emails based on column values
- **Rich Text Editor**: Create professional-looking emails with a WYSIWYG editor
- **Personalized Attachments**: Generate custom attachments for each recipient
- **Static Attachments**: Include the same attachments for all recipients
- **Custom SMTP Configuration**: Configure your own SMTP settings for sending emails
- **Multiple Sender Addresses**: Send emails from different addresses
- **Batch Processing**: Create batches to manage and track your email campaigns

## Requirements

- PHP 8.1+
- Laravel 10.x
- Composer
- Node.js & NPM
- MySQL 5.7+ or PostgreSQL 9.6+

## Installation

```bash
# Clone the repository
git clone https://github.com/thamzayev/mass-mess.git
cd mass-email-app

# Install PHP dependencies
composer install

# Install NPM dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=mass_email_app
# DB_USERNAME=root
# DB_PASSWORD=

# Run migrations
php artisan migrate

# Create Admin User
php artisan make:filament-user

# Link public storage
php artisan storage:link

# Start the server
php artisan serve

# Start background worker. Necessary to generate and send emails
php artisan queue:work

```

## Usage

### Setting Up

1. **Login to the admin panel** at `http://your-app-url/admin`
2. **Configure SMTP Settings**: Add your SMTP connection details to send emails

### Creating an Email Campaign

1. **Create a Batch**: Go to the Batch section and create a new email batch
2. **Upload CSV**: Navigate to the CSV Import section and upload your data file
3. **Configure Recipients**: 
   - Set "To" email addresses using a column from your CSV
   - Optionally set "CC" and "BCC" recipients
4. **Compose Email**: Use the rich text editor to compose your email content
   - Use `[[column_name]]` to insert dynamic content from your CSV
   - Use conditional statements for personalized content:
     ```
     [[IF column_name=='Yes']] 
       This content will only appear if the column value is 'Yes'
     [[ENDIF]]
     ```
   - Supported conditions: `==` (equals), `!=` (not equals)
5. **Add Attachments**: 
   - Static attachments: Upload files that will be sent to all recipients
   - Dynamic attachments: Create templates with `[[column_name]]` placeholders (Header and footer will be added to each page of the file.)
6. **Generate and Send**: Click on "Generate Emails" to create individual emails and send them

### Example

If your CSV contains columns like `name`, `email`, and `subscription_type`, you can create an email like:

```
Hello [[name]],

Thank you for your interest in our services.

[[IF subscription_type=='Premium']]
As a premium subscriber, you have access to all of our features.
Please find attached your premium resource pack.
[[ENDIF]]

[[IF subscription_type!='Premium']]
Consider upgrading to our premium plan for additional benefits.
[[ENDIF]]

Best regards,
Your Company
```

## Batch Management

- **Create**: Set up a new email campaign
- **Generate**: Process the batch to create individual emails
- **Send**: Send all generated emails

## Configuration

### Environment Variables

Configure the following in your `.env` file:

```
# Application Settings
APP_NAME="Mass Mess"
APP_ENV=production
APP_DEBUG=false

# Mail Queue Settings
QUEUE_CONNECTION=database
MAIL_QUEUE=emails

# File Storage Settings
FILESYSTEM_DISK=local
```

### Custom SMTP Settings

Users can configure their own SMTP settings through the application interface, including:

- SMTP Host
- SMTP Port
- SMTP Username
- SMTP Password
- Encryption Type (TLS/SSL)
- From Email Address
- From Name

## Roadmap
 - Email tracking
 - Reporting
 ...

## Security

- All user inputs are validated and sanitized
- CSV data is processed securely
- SMTP credentials are encrypted in the database
- Rate limiting is implemented to prevent abuse

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a new branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Commit your changes (`git commit -m 'Add some amazing feature'`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

## Support

For support, please open an issue in the GitHub repository or contact the development team.
