# Rey CRM System

**Version 2.1.1** - A professional PHP-based Customer Relationship Management (CRM) system designed for managing customer interactions, contacts, and activity history. This comprehensive system helps businesses efficiently track customer relationships, follow-ups, and business activities with a clean, responsive interface.

## System Overview

Rey CRM is a comprehensive customer relationship management solution with multi-language support (Chinese/English), featuring powerful customer management, activity tracking, email integration, and data analysis capabilities. Built with modern web technologies, it provides an intuitive user interface and robust backend management functions.

**🎉 Version 2.1.1 Major Updates**: Enhanced customer management with improved assignment functionality and status transition fixes!

## Core Features

### Customer Management
- Complete customer lifecycle management with CRUD operations
- Detailed company profiles:
  - Company name and contact details
  - Location tracking (province/country)
  - Company type classification
  - Status tracking (prospect, lead, active, inactive customers, etc.)
- Smart location handling:
  - Province/country separation
  - Intelligent location display
  - Location-based filtering with N/A handling
  - Location statistics in dashboard
- Status indicators with visual markers
- Notes and comments support
- Automatic timestamp tracking
- Customer assignment management (user assignment support)
- **Customer Status Reports**:
  - Comprehensive status analysis
  - Status change tracking
  - Customer status timeline
  - Status distribution statistics

### Contact Management
- Multiple contacts per customer support
- Main contact automatic creation and protection:
  - Automatic main contact creation
  - Protection of main contacts from deletion
  - Role-based identification
- Contact information includes:
  - Name and position
  - Role display (in parentheses)
  - Contact phone
  - Email address
  - Custom notes
- Contact history tracking
- Contact statistics in dashboard

### Activity History and Follow-ups
- Comprehensive interaction recording:
  - Action details
  - Customer responses
  - Next step plans
  - Follow-up scheduling
- Contact person association
- Historical data access:
  - Access to past follow-ups
  - Historical activity tracking
  - Complete timeline view
- Timezone-aware datetime handling:
  - UTC storage, local display
  - Automatic timezone conversion
  - Configurable timezone settings
- CSV export functionality
- Activity timeline view
- Follow-up reminders
- Multiple contact channel support (phone, email, WeChat, LinkedIn, etc.)

### Dashboard Features
- Customer statistics:
  - Status distribution
  - Location breakdown
  - Contact rates
- Recent activity timeline
- Upcoming follow-ups
- Quick action features
- Contact status tracking
- Export functionality:
  - Activity history
  - Follow-up schedules
  - Custom date ranges

### System Settings
- Timezone configuration
- Custom items per page display
- User management:
  - Role-based access control
  - User creation and management
  - Profile management
  - **User data isolation**: Users can only view and edit their own customer data
- Email configuration:
  - SMTP settings
  - Email testing
  - Custom sender details
  - **Email history access control**: Users can only view their own email history
- Multi-language support:
  - Chinese/English interface
  - Language preference settings
  - Localized datetime formats

### User Interface
- Clean, modern design
- Responsive layout
- Smart navigation:
  - State persistence
  - Intelligent back handling
- Advanced filtering:
  - Combined search fields
  - Smart location filtering
  - Date range selection
- Visual status indicators
- Fixed-width datetime columns
- Form state persistence
- **Dark mode toggle** (in header)
- **Improved settings page layout** (card-based arrangement)

### Time and Location Handling
- Advanced timezone management:
  - Optimized timezone conversion pipeline
  - Database-level timezone handling (using CONVERT_TZ)
  - Simplified frontend datetime handling
  - Automatic timezone detection
  - Consistent datetime display across all forms
  - Smart UTC conversion handling
  - Enhanced datetime picker support
  - Improved follow-up scheduling accuracy

### Email System
- Complete SMTP email configuration
- Email project management
- Email history records
- Attachment support
- Email template functionality
- Bulk email sending
- Email testing functionality

### Administrative Features
- User performance analysis
- Customer assignment management
- System reports and exports
- Bulk operation support
- Detailed activity logs
- System health monitoring
- **Customer Status Report System**:
  - Status distribution analysis
  - Customer status timeline tracking
  - Status change trend analysis
  - Export status summary reports

### Docker Support
- Complete containerization support:
  - Nginx web server
  - PHP 8.3-FPM
  - MariaDB database
- Easy deployment:
  - Docker Compose configuration
  - Environment isolation
  - Volume persistence
  - Automatic container orchestration
- Development ready:
  - Hot reload support
  - Log volume mounting
  - Easy configuration through environment variables

## Technical Requirements

### Server Requirements
- PHP 8.0 or higher (8.3+ recommended)
- MySQL 5.7+ / MariaDB 10.2+
- Apache 2.4+ / Nginx 1.14+
- Required PHP extensions:
  - PDO (MySQL driver)
  - mbstring
  - date
  - session
  - json
  - openssl (for email functionality)

### Browser Requirements
- Modern web browser (Chrome, Firefox, Safari, Edge)
- JavaScript enabled
- Minimum display width: 768px
- Cookies enabled
- CSS Grid and Flexbox support

### Docker Requirements
- Docker Engine 20.10.0 or newer
- Docker Compose v2.0.0 or newer
- Minimum 2GB RAM
- 10GB disk space

## Installation Guide

### Traditional Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/luozongbao/rey-crm.git
   cd rey-crm
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure web server:
   - Configure Apache/Nginx to point to the project directory
   - Ensure web server has write permissions to `logs/` directory
   - Set document root to the project's root directory

4. Start installation:
   - Visit `http://your-domain/includes/install.php` in your browser
   - Enter database details in the installation form
   - The system will:
     - Create config.php with your settings
     - Set up database structure
     - Create your admin account

5. Initial setup:
   - Log in with admin credentials
   - Configure system timezone
   - Set up SMTP email settings
   - Customize items per page display
   - Add additional users as needed

6. Set file permissions:
   ```bash
   # Set correct ownership
   chown -R www-data:www-data /path/to/rey-crm
   
   # Set correct permissions for files and directories
   find /path/to/rey-crm -type f -exec chmod 644 {} \;
   find /path/to/rey-crm -type d -exec chmod 755 {} \;
   
   # Special permissions for writable directories
   chmod -R 775 logs/
   chmod -R 775 uploads/
   chmod 400 includes/config.php
   ```

7. Access the application:
   ```
   http://your-server/path-to-rey-crm/
   ```

### Docker Installation

1. Ensure Docker and Docker Compose are installed

2. Clone the repository:
   ```bash
   git clone https://github.com/luozongbao/rey-crm.git
   cd rey-crm
   ```

3. Configure environment (optional):
   ```bash
   cp .env.example .env
   # Edit .env file as needed
   ```

4. Start containers:
   ```bash
   docker-compose up -d
   ```

5. Access the application:
   ```
   http://localhost
   ```

6. Complete web installation:
   - Visit `http://localhost/includes/install.php`
   - Database configuration:
     - Host: db
     - Username: root
     - Password: password
     - Database: rey_crm

### Production Deployment Recommendations

1. **Security Configuration**:
   ```bash
   # Set secure file permissions
   chmod 600 includes/config.php
   chmod 700 logs/
   
   # Remove or rename install.php
   mv includes/install.php includes/install.php.bak
   ```

2. **Nginx Configuration Example**:
   ```nginx
   server {
       listen 80;
       server_name your-domain.com;
       root /var/www/rey-crm;
       index index.php;
       
       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }
       
       location ~ \.php$ {
           fastcgi_pass php:9000;
           fastcgi_index index.php;
           include fastcgi_params;
           fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
       }
       
       location ~ /\.ht {
           deny all;
       }
   }
   ```

3. **SSL Configuration**:
   ```nginx
   server {
       listen 443 ssl http2;
       ssl_certificate /path/to/certificate.crt;
       ssl_certificate_key /path/to/private.key;
       # Additional SSL settings...
   }
   ```

## Usage Guide

### Getting Started
1. **Initial Login**: Use the admin credentials created during installation
2. **System Configuration**: Set timezone, configure SMTP, and customize display preferences
3. **User Management**: Create user accounts for your team members
4. **Customer Data Import**: Begin adding customer information and contacts

### Daily Operations
- **Customer Management**: Add, edit, and track customer information
- **Activity Logging**: Record customer interactions and schedule follow-ups
- **Contact Management**: Maintain contact person details and relationships
- **Data Analysis**: Use dashboard and reports for business insights

### Advanced Features
- **Email Integration**: Configure SMTP for automated communications
- **Data Export**: Generate CSV reports for external analysis
- **Status Workflows**: Track customer lifecycle through status transitions
- **Timezone Management**: Handle global customers with proper timezone support

## Security Features

- **User Authentication**: Secure login system with password reset capability
- **Role-based Access**: Different permission levels for users and administrators
- **Data Isolation**: Users can only access their assigned customer data
- **Email Privacy**: Email history is protected per user
- **Session Management**: Secure session handling with timeout protection
- **Input Validation**: Comprehensive data validation and sanitization
- **SQL Injection Protection**: PDO prepared statements throughout
- **XSS Prevention**: Output escaping and content filtering

## Performance Optimization

- **Database Indexing**: Optimized database structure for fast queries
- **Query Optimization**: Efficient database queries with minimal overhead
- **Caching Strategy**: Smart caching for frequently accessed data
- **Resource Management**: Optimized asset loading and compression
- **Memory Efficiency**: Efficient memory usage for large datasets

## Troubleshooting

### Common Issues
1. **Installation Problems**: Check file permissions and database connectivity
2. **Email Not Working**: Verify SMTP settings and test email configuration
3. **Timezone Issues**: Ensure proper timezone configuration in system settings
4. **Performance Issues**: Check database indexes and server resources
5. **Login Problems**: Verify user credentials and session configuration

### Debug Mode
Enable debug mode by adding to config.php:
```php
define('DEBUG_MODE', true);
```

### Log Files
Check application logs in the `logs/` directory for detailed error information.

## Contributing

We welcome contributions to improve Rey CRM System. Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

### Development Setup
```bash
# Clone and setup development environment
git clone https://github.com/luozongbao/rey-crm.git
cd rey-crm
composer install --dev

# Start development server
php -S localhost:8000
```

## Support and Documentation

- **User Manual**: Comprehensive documentation in the `docs/` directory
- **API Reference**: Developer documentation for customization
- **Video Tutorials**: Step-by-step usage guides
- **Community Forum**: Discussion and support community

## Changelog

See [RELEASE.md](RELEASE.md) for detailed version history and changes.

## License

Rey CRM System is open-source software licensed under the [MIT License](LICENSE).

## Acknowledgments

Special thanks to all contributors and the open-source community for making this project possible.

---

**Rey CRM System v2.1.1** - Empowering businesses with efficient customer relationship management.
