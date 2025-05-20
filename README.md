# Rey CRM System

A modern PHP-based Customer Relationship Management (CRM) system designed for managing customer interactions, contacts, and activity history. This comprehensive system helps businesses track customer relationships, follow-ups, and business activities efficiently with a clean, responsive interface. Features secure user authentication, role-based access control, and customizable settings.

## Core Features

### Time and Date Management
- Complete timezone support:
  - Configurable system timezone
  - UTC storage with local timezone display
  - Automatic conversion between UTC and local time
  - Timezone-aware datetime inputs
  - Consistent datetime handling across exports
- Smart datetime formatting:
  - Configurable date/time formats
  - Fixed-width datetime columns (140px)
  - Timezone indicator in relevant views

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
- Schedule follow-up activities with timezone support
- Link activities to specific contacts
- Automatic timezone conversion for:
  - Action timestamps
  - Follow-up schedules
  - Export data

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
  - Action history export to CSV with proper timezone handling
  - Follow-up schedule export to CSV
  - Custom date range filtering
- Enhanced UI features:
  - Responsive table layouts
  - Fixed-width datetime columns (140px)
  - Status labels with dynamic width support
  - Color-coded status indicators

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

### User Management and Settings
- Secure user authentication system
- Role-based access control
- Customizable system settings:
  - Timezone configuration
  - Items per page
  - Email settings
  - User preferences

## Technical Details

### Installation
1. Clone the repository
2. Configure your web server to point to the project directory
3. Import the database schema from `database/database.sql`
4. Copy `includes/config.php.example` to `includes/config.php` and configure your database settings
5. Set up your SMTP email settings in the admin panel
6. Configure your system timezone in settings

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- PHP Extensions:
  - PDO
  - mysqli
  - date
  - mbstring

### Security Features
- Password hashing using PHP's password_hash()
- CSRF protection
- XSS prevention
- SQL injection protection
- Secure session handling
- Role-based access control

## Contributing

Please read CONTRIBUTING.md for details on our code of conduct and the process for submitting pull requests.

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions, please open an issue in the GitHub repository.


---

© 2025 VIBE Coding | Rey CRM System | Last Updated: May 18, 2025
