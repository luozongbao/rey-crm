# Rey CRM System

A modern PHP-based Customer Relationship Management (CRM) system designed for managing customer interactions, contacts, and activity history. This comprehensive system helps businesses track customer relationships, follow-ups, and business activities efficiently with a clean, responsive interface.

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

## Technical Requirements

- PHP 7.4+ recommended (7.0+ minimum)
- MySQL 5.7+ or MariaDB 10.2+
- Web server (Apache 2.4+/Nginx 1.14+)
- Required PHP Extensions:
  - PDO with MySQL driver
  - mbstring
  - json
  - session

### Browser Requirements
- Modern browsers (Chrome, Firefox, Safari, Edge)
- JavaScript enabled for enhanced features
- Minimum screen resolution: 768px width
- Cookies enabled for session management

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/personal-crm.git
   cd personal-crm
   ```

2. Import the database schema:
   ```bash
   mysql -u your_username -p your_database_name < database.sql
   ```

3. Configure database connection:
   - Edit `includes/config.php`
   ```php
   $host = 'localhost';
   $dbname = 'your_database_name';
   $username = 'your_username';
   $password = 'your_password';
   ```

4. Set file permissions:
   ```bash
   chmod 755 -R /path/to/crm
   chmod 644 includes/config.php
   ```

5. Access the application:
   ```
   http://your-server/path-to-crm/
   ```

## Directory Structure

```
├── assets/
│   ├── css/
│   │   └── style.css       # Main stylesheet with responsive design
│   └── js/
│       └── script.js       # Enhanced client-side functionality
├── includes/
│   ├── config.php         # Database and application configuration
│   └── functions.php      # Core PHP functions and utilities
├── all_activities.php    # Complete activity history view
├── all_followups.php     # Comprehensive follow-up tracking
├── contact_form.php      # Contact person management
├── customer_form.php     # Customer profile management
├── dashboard.php        # Analytics and statistics dashboard
├── history_form.php     # Activity logging and management
├── index.php           # Main customer listing and overview
├── database.sql        # Database schema and initial data
└── readme.md          # Project documentation
```

## Key Features & Improvements

### UI Enhancements
- Responsive table layouts with optimized column widths
- Fixed-width datetime columns (140px) for consistent display
- Dynamic status badges with color coding
- Adjustable status label width (140px) for better readability
- Modern, clean interface design

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

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/improvement`)
3. Commit your changes (`git commit -am 'Add new feature'`)
4. Push to the branch (`git push origin feature/improvement`)
5. Create a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and feature requests, please open an issue in the repository.
2. Implement user authentication/authorization
3. Use prepared statements for all database queries
4. Validate and sanitize all user inputs
5. Keep dependencies updated
6. Enable error logging
7. Use HTTPS

## License

This project is open source and available under the MIT License.

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request