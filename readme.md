# Personal CRM System

A simple PHP-based Customer Relationship Management (CRM) system designed for managing customer interactions, contacts, and activity history.

## Features

- Customer Management
  - Add, edit, view, and delete customer records
  - Track company details, location, contact info, and status
  - Status tracking (Active, Inactive, Prospect)

- Contact Person Management
  - Manage multiple contact persons per customer
  - Track contact details including name, title, role, phone, and email

- Activity History
  - Log customer interactions and follow-ups
  - Track actions, responses, and next steps
  - Schedule follow-up activities

- Dashboard
  - Overview of total customers
  - Customer location statistics
  - Contact status tracking
  - Recent activities timeline
  - Upcoming follow-ups
  - Export functionality for history and follow-ups

## Requirements

- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web server (Apache/Nginx)
- PDO PHP Extension

## Installation

1. Clone or download this repository to your web server directory.

2. Create a new MySQL database and import the schema:
   ```sql
   mysql -u your_username -p your_database_name < database.sql
   ```

3. Configure the database connection:
   - Open `includes/config.php`
   - Update the following variables with your database credentials:
     ```php
     $host = 'localhost';
     $dbname = 'your_database_name';
     $username = 'your_username';
     $password = 'your_password';
     ```

4. Set appropriate permissions:
   ```bash
   chmod 755 -R /path/to/crm
   chmod 644 includes/config.php
   ```

5. Access the application through your web browser:
   ```
   http://your-domain/path-to-crm/
   ```

## Directory Structure

```
├── assets/
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── script.js
├── includes/
│   ├── config.php
│   └── functions.php
├── contact_form.php
├── customer_form.php
├── dashboard.php
├── history_form.php
├── index.php
└── README.md
```

## Security Notes

1. Make sure to update database credentials in `config.php`
2. Place the `includes` directory outside of web root if possible
3. Implement proper access control based on your requirements

## Contributing

Feel free to fork this repository and submit pull requests for any improvements.

## License

This project is licensed under the MIT License - see the LICENSE file for details.