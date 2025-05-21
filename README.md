# Rey CRM System

A professional PHP-based Customer Relationship Management (CRM) system designed for managing customer interactions, contacts, and activity history. This comprehensive system helps businesses track customer relationships, follow-ups, and business activities efficiently with a clean, responsive interface.

## Core Features

### Customer Management
- Complete customer lifecycle management with CRUD operations
- Detailed company profiles:
  - Company name and contact details
  - Location tracking (Province/Country)
  - Company type classification
  - Status tracking (Prospect, Active, Inactive, etc.)
- Smart location handling:
  - Province/Country separation
  - Intelligent location display
  - Location-based filtering with N/A handling
  - Location statistics in dashboard
- Status badges with visual indicators
- Notes and remarks support
- Automatic timestamp tracking

### Contact Person Management
- Multiple contacts per customer
- Main contact auto-creation
- Contact information includes:
  - Name and title
  - Role
  - Contact number
  - Email address
  - Custom notes
- Contact history tracking
- Contact statistics in dashboard

### Activity History & Follow-ups
- Comprehensive interaction logging:
  - Action details
  - Customer responses
  - Next steps planning
  - Follow-up scheduling
- Contact person association
- Timezone-aware datetime handling
  - UTC storage with local display
  - Automatic timezone conversion
  - Configurable timezone settings
- CSV export capabilities
- Activity timeline view
- Follow-up reminders

### Dashboard Features
- Customer statistics:
  - Status distribution
  - Location breakdown
  - Contact rates
- Recent activity timeline
- Upcoming follow-ups
- Quick action shortcuts
- Contact status tracking
- Export capabilities:
  - Activity history
  - Follow-up schedules
  - Custom date ranges

### System Settings
- Timezone configuration
- Items per page customization
- User management:
  - Role-based access control
  - User creation and management
  - Profile management
- Email configuration:
  - SMTP settings
  - Email testing
  - Custom sender details

### User Interface
- Clean, modern design
- Responsive layouts
- Smart navigation:
  - State preservation
  - Intelligent back handling
- Advanced filtering:
  - Combined search fields
  - Smart location filters
  - Date range selection
- Visual status indicators
- Fixed-width datetime columns
- Form state persistence
- **Dark mode toggle in header** (v1.5.3)
- **Improved settings page layout with arranged cards** (v1.5.3)

## Technical Requirements

### Server Requirements
- PHP 7.4 or higher
- MySQL 5.7+ / MariaDB 10.2+
- Apache 2.4+ / Nginx 1.14+
- Required PHP extensions:
  - PDO (with MySQL driver)
  - mbstring
  - date
  - session

### Browser Requirements
- Modern web browsers (Chrome, Firefox, Safari, Edge)
- JavaScript enabled
- Minimum display width: 768px
- Cookies enabled

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd rey-crm
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Set up web server:
   - Configure Apache/Nginx to point to the project directory
   - Ensure the web server has write permissions for `logs/` directory
   - Set document root to the project's root directory

4. Start installation:
   - Access `http://your-domain/includes/install.php` in your browser
   - Enter your database details in the installation form
   - The system will:
     - Create config.php with your settings
     - Set up the database structure
     - Create your admin account

5. First-time setup:
   - Log in with your admin credentials
   - Configure system timezone
   - Set up SMTP email settings
   - Customize items per page
   - Add additional users as needed

6. Set file permissions:
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

7. Access the application:
   ```
   http://your-server/path-to-rey-crm/
   ```

## Security Features

- Secure authentication
- Password hashing
- CSRF protection
- XSS prevention
- SQL injection protection
- Session security
- Role-based access control

## File Structure

```
/
├── assets/          # Static assets (CSS, JS)
├── database/        # Database schema
├── docs/           # Documentation
├── includes/       # Core PHP files
│   ├── config.php  # Configuration
│   └── functions.php # Helper functions
└── logs/           # System logs
```

## License

This project is licensed under the MIT License. See LICENSE file for details.

---

© 2025 VIBE Coding | Rey CRM System | Last Updated: May 21, 2025

## Support

For support and feature requests, please open an issue in the repository.

## Security Recommendations

1. Ensure HTTPS is configured for production
2. Keep dependencies updated
3. Review logs regularly for suspicious activity
4. Perform regular database backups
5. Implement rate limiting for login attempts
6. Enable error logging