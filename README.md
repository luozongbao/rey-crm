# Rey CRM System

A modern PHP-based Customer Relationship Management (CRM) system designed for managing customer interactions, contacts, and activity history. This comprehensive system helps businesses track customer rela### Key Features & Improvements

### Email Functionality
- Complete SMTP email integration
- Configurable email settings:
  - SMTP server configuration
  - Authentication options
  - TLS/SSL encryption
  - Custom sender name and email
- Email testing capability
- Reusable email sending API for developers
- HTML and plain text email support
- File attachment capabilities
- CC/BCC recipient support

### UI Enhancements
- Responsive table layouts with optimized column widths
- Fixed-width datetime columns (140px) for consistent display
- Dynamic status badges with color coding
- Adjustable status label width (140px) for better readability
- Modern, clean interface design with CSS variables
- Consistent color scheme with primary, success, danger, and warning variants
- Accessible typography with responsive font sizing
- Custom form styling with focus statesfollow-ups, and business activities efficiently with a clean, responsive interface. Features secure user authentication, role-based access control, and customizable settings.

## Core Features

### Customer Management
- Complete CRUD operations for customer records
- Comprehensive company tracking:
  - Company name and detailed location (Province, Country)
  - Company type and classification
  - Primary contact information
  - Website and online presence
  - Detailed notes and remarks
- Smart location handling:
  - Separate province and country fields
  - Intelligent location display (Province, Country, or single field)
  - Location-based filtering with N/A handling
  - Organized location dropdown in filters
- Dynamic status management:
  - Status options from database ENUM
  - Status types: Prospect, Qualified, Not Qualified, New Customer, Active Customer, Inactive Customer, Won Customer, Lost Customer
  - Visual status indicators with color-coded badges
- State preservation:
  - Remembers search, filter, and sort settings
  - Maintains page position when returning from forms
  - Smart navigation with state restoration

### Contact Person Management
- Multiple contacts per customer
- Contact details include:
  - Name and title
  - Role
  - Contact number and email
  - Custom notes

### Activity History
- Log all customer interactions
- Track responses and next steps
- Schedule follow-up activities
- Link activities to specific contacts

### Dashboard Analytics
- Comprehensive overview:
  - Total customer count with status breakdown
  - Customer location distribution
  - Contact status statistics
- Activity monitoring:
  - Recent activities timeline with detailed view
  - Upcoming follow-ups with datetime tracking
  - Activity response tracking
- Export capabilities:
  - Action history export to CSV
  - Follow-up schedule export to CSV
  - Custom date range filtering
- Enhanced UI features:
  - Responsive table layouts
  - Fixed-width datetime columns (140px)
  - Status labels with dynamic width support
  - Color-coded status indicators

### UI/UX Features
- Advanced Search and Filtering:
  - Combined search for company name and phone
  - Smart location filtering with proper handling of empty values
  - Persistent search state across navigation
- Improved Table Layout:
  - Responsive table design
  - Smart handling of location display (Province, Country)
  - Efficient pagination with state preservation
- Enhanced Navigation:
  - State preservation across form submissions
  - Intelligent back navigation
  - User-friendly filter reset options

### User Management
- Secure user authentication system
- Role-based access control:
  - Admin users with full system access
  - Regular users with restricted permissions
- User features:
  - Secure password hashing
  - Email verification
  - Self-service password reset
  - User profile management
  - Last login tracking
  - Session management
  - Automatic logout protection

### System Settings
- Configurable pagination:
  - Customizable items per page
  - Applied across all listing pages
- User management interface:
  - Add/Edit/Delete users
  - Assign user roles
  - Manage user permissions
- Email system configuration:
  - SMTP server settings
  - Email templates management
  - Test email functionality
  - From email and name customization
  - TLS/SSL encryption options
- System configuration:
  - Centralized settings management
  - Database-driven configuration
  - Easy-to-use admin interface

### Security Features
- Secure authentication:
  - Password hashing using PHP's password_hash
  - Protection against SQL injection
  - XSS prevention
  - CSRF protection
- Password management:
  - Secure password reset via email
  - Time-limited, single-use reset tokens
  - Rate limiting to prevent brute force attacks
  - Automatic expiration of unused tokens
- Session security:
  - HTTP-only cookies
  - Secure session handling
  - Session timeout management
- Access Control:
  - Role-based permissions
  - Protected admin routes
  - Secure form processing

## Technical Requirements

### Server Requirements
- PHP 7.4+ recommended (7.0+ minimum)
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache 2.4+/Nginx 1.14+)
- Required PHP Extensions:
  - PDO with MySQL driver
  - mbstring
  - json
  - session
- Composer (for PHPMailer installation)

### Browser Requirements
- Modern browsers (Chrome, Firefox, Safari, Edge)
- JavaScript enabled for enhanced features
- Minimum screen resolution: 768px width
- Cookies enabled for session management

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/VIBE-Coding/rey-crm.git
   cd rey-crm
   ```

2. Set up your web server (Apache/Nginx) to point to the project directory

3. Install required dependencies:
   ```bash
   composer install
   ```

4. Access the installation page:
   - Navigate to `http://your-domain/includes/install.php`
   - Follow the database configuration steps
   - Create your admin user when prompted

5. Initial Configuration:
   - Log in with your admin credentials via `login.php`
   - Configure system settings in the Settings page
   - Set up SMTP email settings for notifications
   - Set up additional users as needed

5. Set file permissions:
   ```bash
   # Set proper ownership
   chown -R www-data:www-data /path/to/rey-crm
   
   # Set proper permissions for files and directories
   find /path/to/rey-crm -type f -exec chmod 644 {} \;
   find /path/to/rey-crm -type d -exec chmod 755 {} \;
   
   # Special permissions for writable directories
   chmod -R 775 logs/
   chmod 400 includes/config.php
   ```

6. Access the application:
   ```
   http://your-server/path-to-rey-crm/
   ```

## Directory Structure

```
├── assets/
│   ├── css/
│   │   └── style.css       # Main stylesheet with responsive design
│   └── js/
│       └── script.js       # Enhanced client-side functionality
├── database/
│   └── database.sql        # Database schema and initial data
├── docs/
│   └── email_functionality.md # Email system documentation
├── includes/
│   ├── functions.php       # Core PHP functions and utilities
│   ├── header.php          # Shared header template
│   ├── footer.php          # Shared footer template
│   ├── email_test.php      # Email testing functionality
│   └── install.php         # Installation wizard
├── vendor/                 # Composer dependencies
│   ├── autoload.php        # Composer autoloader
│   ├── composer/           # Composer files
│   └── phpmailer/          # PHPMailer library
├── all_activities.php      # Complete activity history view
├── all_followups.php       # Comprehensive follow-up tracking
├── contact_form.php        # Contact person management
├── customer_form.php       # Customer profile management
├── customers.php           # Customer listing and management
├── dashboard.php           # Analytics and statistics dashboard
├── history_form.php        # Activity logging and management
├── index.php               # Main application entry point
├── login.php               # User authentication
├── logout.php              # User logout handler
├── settings.php            # System configuration interface
├── composer.json           # Composer configuration
└── README.md               # Project documentation
```

## Key Features & Improvements

### UI Enhancements
- Responsive table layouts with optimized column widths
- Fixed-width datetime columns (140px) for consistent display
- Dynamic status badges with color coding
- Adjustable status label width (140px) for better readability
- Modern, clean interface design with CSS variables
- Consistent color scheme with primary, success, danger, and warning variants
- Accessible typography with responsive font sizing
- Custom form styling with focus states

### Data Management
- Dynamic status options from database ENUM
- Automated main contact creation for new customers
- Comprehensive activity history tracking
- Detailed follow-up management system
- Flexible notes and comments system

### Security & Best Practices
1. Store sensitive configuration outside web root
2. Input validation and sanitization
3. Prepared SQL statements to prevent injection
4. XSS prevention through HTML escaping
5. CSRF protection on forms
6. Secure password storage with modern hashing

### Performance Optimizations
1. Optimized database queries
2. Efficient table indexing
3. Minimal JavaScript footprint
4. CSS optimizations for faster rendering
5. Responsive image handling

## Maintenance

### Database Management
```bash
# Backup database
mysqldump -u your_username -p your_database_name > backup_$(date +%Y%m%d).sql

# Restore database
mysql -u your_username -p your_database_name < backup_file.sql
```

### File Permissions
```bash
# Set proper ownership
sudo chown -R www-data:www-data /path/to/rey-crm

# Set proper permissions
find /path/to/rey-crm -type f -exec chmod 644 {} \;
find /path/to/rey-crm -type d -exec chmod 755 {} \;
chmod 400 includes/config.php
```

## Contributing

We welcome contributions to improve Rey CRM! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/improvement`)
3. Commit your changes (`git commit -am 'Add new feature'`)
4. Push to the branch (`git push origin feature/improvement`)
5. Create a Pull Request

### Development Guidelines
- Maintain consistent coding style
- Write meaningful commit messages
- Add comments for complex functionality
- Test thoroughly before submitting PRs
- Update documentation for new features

## License

This project is licensed under the MIT License - see the LICENSE file for details.

---

© 2025 VIBE Coding | Rey CRM System | Last Updated: May 18, 2025

## Support

For support and feature requests, please open an issue in the repository.

## Security Recommendations

1. Ensure HTTPS is configured for production
2. Keep dependencies updated
3. Review logs regularly for suspicious activity
4. Perform regular database backups
5. Implement rate limiting for login attempts
6. Enable error logging