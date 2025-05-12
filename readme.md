# Personal CRM System

A PHP-based Customer Relationship Management (CRM) system designed for managing customer interactions, contacts, and activity history. This system helps track customer relationships, follow-ups, and business activities in a simple yet effective way.

## Core Features

### Customer Management
- Create, view, edit, and delete customer records
- Track company details:
  - Company name and location
  - Company type and status
  - Contact information
  - Custom notes
- Status tracking (Active, Inactive, Prospect)

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
- Total customer count
- Customer location distribution
- Contact status statistics
- Recent activities timeline
- Upcoming follow-ups
- Export functionality
  - Action history to CSV
  - Follow-up schedule to CSV

## Technical Requirements

- PHP 7.0+
- MySQL 5.6+
- Web server (Apache/Nginx)
- PDO PHP Extension

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
│   │   └── style.css       # Main stylesheet
│   └── js/
│       └── script.js       # Client-side functionality
├── includes/
│   ├── config.php         # Database configuration
│   └── functions.php      # Core PHP functions
├── contact_form.php       # Contact person management
├── customer_form.php      # Customer management
├── dashboard.php         # Analytics dashboard
├── history_form.php      # Activity history management
├── index.php            # Main customer listing
└── readme.md
```

## Security Recommendations

1. Store sensitive configuration outside web root
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